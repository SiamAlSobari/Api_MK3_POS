<?php

// =============================================================================
// FILE: BillingController.php
// DESKRIPSI: Controller untuk mengelola LANGGANAN (Subscription) dan
//            PEMBAYARAN (Payment) menggunakan Midtrans sebagai Payment Gateway.
//
// KONSEP PENTING:
// - Payment Gateway (Midtrans) = Pihak ketiga yang memproses pembayaran
//   online. User diarahkan ke halaman pembayaran Midtrans, lalu Midtrans
//   akan mengirim notifikasi (webhook) ke server kita saat pembayaran selesai.
//
// - Webhook = URL endpoint di server kita yang dipanggil otomatis oleh
//   Midtrans untuk memberitahu status pembayaran. Webhook ini PUBLIK
//   (tanpa auth) karena dipanggil oleh server Midtrans, bukan oleh user.
//
// - Subscription = Data langganan user (plan, durasi, status)
// - Payment = Data pembayaran untuk subscription tertentu
//
// ALUR PEMBAYARAN:
// 1. User pilih plan -> frontend hit POST /api/billing/subscribe
// 2. Backend buat Subscription + Payment -> hit API Midtrans -> dapat payment_url
// 3. Frontend redirect user ke payment_url Midtrans
// 4. User bayar di halaman Midtrans
// 5. Midtrans kirim notifikasi ke POST /api/billing/webhook
// 6. Backend update status Payment & Subscription berdasarkan notifikasi
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    // =========================================================================
    // PROPERTY: $plans (Static)
    // FUNGSI: Daftar paket langganan yang tersedia beserta harga dan durasi.
    //         Disimpan sebagai static array agar bisa diakses tanpa instance.
    //
    // - PRO = Rp 50.000 / 30 hari
    // - PRO_MAX = Rp 39.000 / 30 hari (lebih murah, fitur AI)
    // =========================================================================
    private static array $plans = [
        'PRO' => [
            'price' => 50000,
            'duration_days' => 30,
        ],
        'PRO_MAX' => [
            'price' => 39000,
            'duration_days' => 30,
        ],
    ];

    // =========================================================================
    // METHOD: subscribe
    // URL: POST /api/billing/subscribe
    // FUNGSI: Membuat langganan baru dan menghasilkan link pembayaran Midtrans.
    //
    // ALUR KERJA:
    // 1. Validasi input: plan_name harus PRO atau PRO_MAX
    // 2. Hitung tanggal mulai (hari ini) dan tanggal berakhir (+30 hari)
    // 3. Buat record Subscription dengan status PENDING
    // 4. Buat record Payment dengan order_id unik (UUID)
    // 5. Konfigurasi Midtrans SDK
    // 6. Hit API Midtrans Snap untuk mendapatkan payment_url
    // 7. Kembalikan data subscription + payment + payment_url ke frontend
    //
    // CATATAN: Midtrans Snap = layanan Midtrans yang menyediakan halaman
    //          pembayaran siap pakai (tidak perlu buat form pembayaran sendiri)
    // =========================================================================
    public function subscribe(Request $request): JsonResponse
    {
        // Validasi: plan_name harus salah satu dari PRO atau PRO_MAX
        $data = $request->validate([
            'plan_name' => 'required|string|in:PRO,PRO_MAX',
        ]);

        // Ambil nama plan (uppercase) dan data plan dari array $plans
        $planName = strtoupper($data['plan_name']);
        $plan = self::$plans[$planName];

        // Hitung tanggal mulai dan berakhir langganan
        $startDate = Carbon::today(); // Hari ini
        $endDate = $startDate->copy()->addDays($plan['duration_days']); // +30 hari

        // Buat record Subscription di database dengan status PENDING
        // Status akan berubah ke ACTIVE setelah pembayaran berhasil (via webhook)
        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'plan_name' => $planName,
            'price' => $plan['price'],
            'duration_days' => $plan['duration_days'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => 'PENDING', // Masih menunggu pembayaran
        ]);

        // Generate order_id unik menggunakan Ordered UUID
        // UUID = Universally Unique Identifier, dijamin unik secara global
        $orderId = Str::orderedUuid()->toString();

        // Buat record Payment di database
        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'order_id' => $orderId, // ID unik untuk Midtrans
            'gross_amount' => $plan['price'],
            'payment_type' => 'MIDTRANS',
            'transaction_time' => null, // Belum bayar
            'status' => 'PENDING',
        ]);

        // =====================================================================
        // KONFIGURASI MIDTRANS SDK
        // Kredensial diambil dari file .env
        // $isProduction = false berarti menggunakan mode Sandbox (testing)
        // $isSanitized = true berarti data di-sanitize otomatis
        // $is3ds = true berarti menggunakan 3D Secure (keamanan kartu kredit)
        // =====================================================================
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Parameter yang dikirim ke API Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $plan['price'],
            ],
            'customer_details' => [
                'first_name' => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ];

        try {
            // Hit API Midtrans Snap untuk membuat transaksi pembayaran
            // createTransaction() mengembalikan objek yang berisi redirect_url
            // redirect_url = halaman pembayaran Midtrans yang akan dibuka user
            $paymentUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
            return response()->json([
                'message' => 'Subscription created. Please complete payment.',
                'subscription' => $subscription,
                'payment' => $payment,
                'payment_url' => $paymentUrl, // Frontend redirect user ke URL ini
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // METHOD: webhook
    // URL: POST /api/billing/webhook
    // FUNGSI: Menerima notifikasi pembayaran dari server Midtrans.
    //         Route ini TIDAK pakai middleware auth karena dipanggil oleh
    //         server Midtrans, bukan oleh user/frontend.
    //
    // ALUR KERJA:
    // 1. Konfigurasi Midtrans SDK
    // 2. Parse notifikasi dari Midtrans (otomatis validasi signature)
    // 3. Cari Payment berdasarkan order_id
    // 4. Tentukan status berdasarkan transaction_status dari Midtrans:
    //    - settlement/capture = SETTLEMENT (pembayaran berhasil)
    //    - cancel/deny/expire = status sesuai (pembayaran gagal)
    // 5. Update status Payment di database
    // 6. Update status Subscription:
    //    - SETTLEMENT -> Subscription jadi ACTIVE
    //    - EXPIRED/CANCEL/DENY -> Subscription tetap PENDING
    //
    // STATUS MIDTRANS:
    // - settlement = pembayaran berhasil (untuk transfer/VA)
    // - capture = pembayaran berhasil (untuk kartu kredit)
    // - pending = belum bayar
    // - cancel = dibatalkan
    // - deny = ditolak
    // - expire = kedaluwarsa
    // =========================================================================
    public function webhook(Request $request): JsonResponse
    {
        // Konfigurasi Midtrans untuk verifikasi notifikasi
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);

        try {
            // Parse notifikasi dari Midtrans
            // Midtrans\Notification() otomatis membaca body request
            // dan memvalidasi signature key-nya untuk keamanan
            $notification = new \Midtrans\Notification();
        } catch (\Exception $e) {
            // Jika notifikasi tidak valid (misal: signature salah)
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        // Cari Payment berdasarkan order_id dari notifikasi Midtrans
        $payment = Payment::where('order_id', $notification->order_id)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Tentukan status Payment berdasarkan transaction_status dari Midtrans
        $status = 'PENDING';
        if ($notification->transaction_status == 'settlement' || $notification->transaction_status == 'capture') {
            // settlement (transfer/VA) atau capture (kartu kredit) = BERHASIL
            $status = 'SETTLEMENT';
        } elseif (in_array($notification->transaction_status, ['cancel', 'deny', 'expire'])) {
            // cancel/deny/expire = GAGAL (simpan status sesuai notifikasi)
            $status = strtoupper($notification->transaction_status);
            if ($status === 'EXPIRE') $status = 'EXPIRED'; // Sesuaikan penamaan
        }

        // Update data Payment di database
        $payment->update([
            'status' => $status,
            'transaction_time' => $notification->transaction_time ? Carbon::parse($notification->transaction_time) : now(),
            'payment_type' => $notification->payment_type, // misal: bank_transfer, gopay, dll
        ]);

        // Ambil Subscription yang terhubung dengan Payment ini
        $subscription = $payment->subscription;

        // Update status Subscription berdasarkan status pembayaran
        if ($status === 'SETTLEMENT') {
            // Pembayaran berhasil -> aktifkan langganan
            $subscription->update(['status' => 'ACTIVE']);
        } elseif (in_array($status, ['EXPIRED', 'CANCEL', 'DENY'], true)) {
            // Pembayaran gagal -> kembalikan ke PENDING
            $subscription->update(['status' => 'PENDING']);
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'payment' => $payment,
            'subscription' => $subscription,
        ]);
    }

    // =========================================================================
    // KODE DI-COMMENT: webhookTest
    // FUNGSI: Versi testing webhook yang bisa dipanggil via Postman
    //         tanpa validasi signature Midtrans. Digunakan saat development
    //         untuk mensimulasikan notifikasi pembayaran.
    //         Saat ini di-comment karena tidak dipakai di production.
    // =========================================================================
    // TAMBAHAN: Webhook khusus untuk test di Postman
    // public function webhookTest(Request $request): JsonResponse
    // {
    //     // Langsung ambil data dari body JSON Postman
    //     $notification = (object) $request->all();

    //     $payment = Payment::where('order_id', $notification->order_id)->first();
    //     if (!$payment) {
    //         return response()->json(['message' => 'Payment not found'], 404);
    //     }

    //     $status = 'PENDING';
    //     if ($notification->transaction_status == 'settlement' || $notification->transaction_status == 'capture') {
    //         $status = 'SETTLEMENT';
    //     } elseif (in_array($notification->transaction_status, ['cancel', 'deny', 'expire'])) {
    //         $status = strtoupper($notification->transaction_status);
    //         if ($status === 'EXPIRE') $status = 'EXPIRED';
    //     }

    //     $payment->update([
    //         'status' => $status,
    //         'transaction_time' => isset($notification->transaction_time) ? Carbon::parse($notification->transaction_time) : now(),
    //         'payment_type' => $notification->payment_type ?? 'POSTMAN_MOCK',
    //     ]);

    //     $subscription = $payment->subscription;

    //     if ($status === 'SETTLEMENT') {
    //         $subscription->update(['status' => 'ACTIVE']);
    //     } elseif (in_array($status, ['EXPIRED', 'CANCEL', 'DENY'], true)) {
    //         $subscription->update(['status' => 'PENDING']);
    //     }

    //     return response()->json([
    //         'message' => 'Mock Webhook processed.',
    //         'payment' => $payment,
    //         'subscription' => $subscription,
    //     ]);
    // }

    // =========================================================================
    // METHOD: active
    // URL: GET /api/billing/active
    // FUNGSI: Mengecek apakah user memiliki langganan yang masih aktif.
    //
    // CARA KERJA:
    // - Cari Subscription milik user yang:
    //   * status = 'ACTIVE'
    //   * end_date >= hari ini (belum expired)
    // - with(['payments']) = sertakan data pembayaran terkait
    // - latest() = ambil yang terbaru (jika ada lebih dari 1)
    // - first() = ambil 1 record atau null
    //
    // RETURN: Data subscription aktif atau null jika tidak ada
    // =========================================================================
    public function active(Request $request): JsonResponse
    {
        // Cari langganan aktif milik user yang belum expired
        $activeSubscription = Subscription::with(['payments'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', '>=', Carbon::today()) // Belum expired
            ->latest() // Urutkan dari terbaru
            ->first(); // Ambil 1 saja

        return response()->json([
            'message' => 'Active billing data retrieved successfully.',
            'data' => $activeSubscription, // null jika tidak ada langganan aktif
        ]);
    }
}
