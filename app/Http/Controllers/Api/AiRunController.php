<?php

// =============================================================================
// FILE: AiRunController.php
// DESKRIPSI: Controller untuk fitur AI / Machine Learning.
//            Mengintegrasikan backend Laravel dengan API AI eksternal (FastAPI/Python)
//            untuk analisis prediktif: rekomendasi restock stok dan prediksi jam sibuk.
//
// KONSEP PENTING:
// - Fitur ini HANYA untuk user dengan langganan PRO_MAX yang aktif
// - Laravel backend mengirim data transaksi ke API AI Python (FastAPI)
// - API AI memproses data dengan Machine Learning dan mengembalikan prediksi
// - Laravel menyimpan hasil prediksi ke database untuk ditampilkan di frontend
//
// 2 TIPE ANALISIS AI:
// 1. STOCKS (Rekomendasi Restock)
//    -> Memprediksi kapan stok akan habis dan berapa yang harus di-restock
// 2. BUSY (Prediksi Jam Sibuk)
//    -> Memprediksi jam-jam sibuk, produk yang terjual, dan pendapatan
//
// STRUKTUR DATABASE AI:
// - AiRun = sesi analisis (kapan dijalankan, status, tipe)
// - AiRecommendation = hasil rekomendasi restock per produk
// - AiRecommendationAction = tindakan user terhadap rekomendasi (DONE/IGNORE)
// - BusyHourDailyForecast = prediksi per hari
// - BusyHourHourlyPrediction = prediksi per jam dalam sehari
// - BusyHourProductPrediction = prediksi produk terjual per jam
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\BusyHourDailyForecast;
use App\Models\BusyHourHourlyPrediction;
use App\Models\BusyHourProductPrediction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiRunController extends Controller
{
    // =========================================================================
    // METHOD: checkProMax (Private Helper)
    // FUNGSI: Mengecek apakah user memiliki langganan PRO_MAX yang masih aktif.
    //         Method ini dipanggil di SETIAP method publik sebagai "gatekeeper".
    //
    // CARA KERJA:
    // - Cari di tabel subscriptions:
    //   * user_id cocok
    //   * plan_name = 'PRO_MAX'
    //   * status = 'ACTIVE'
    //   * end_date >= hari ini (belum expired)
    // - exists() = return true/false (lebih efisien daripada first() karena
    //   tidak perlu mengambil seluruh data, cukup cek ada atau tidak)
    //
    // RETURN: true jika user punya PRO_MAX aktif, false jika tidak
    // =========================================================================
    private function checkProMax($user): bool
    {
        return \App\Models\Subscription::where('user_id', $user->id)
            ->where('plan_name', 'PRO_MAX')
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', '>=', \Carbon\Carbon::today())
            ->exists();
    }

    // =========================================================================
    // METHOD: latestStocks
    // URL: GET /api/ai/runs/latest/stocks
    // FUNGSI: Mengambil hasil analisis AI RESTOCK terbaru milik user.
    //
    // ALUR KERJA:
    // 1. Cek langganan PRO_MAX -> tolak 403 jika tidak punya
    // 2. Query AiRun terbaru (type_ai = 'STOCKS') beserta relasi:
    //    - aiRecommendations = daftar rekomendasi restock per produk
    //    - aiRecommendations.product = data produk yang direkomendasikan
    //    - aiRecommendations.aiRecommendationActions = tindakan user (DONE/IGNORE)
    // 3. whereHas('product') = filter hanya rekomendasi yang produknya masih ada
    //    (tidak menampilkan rekomendasi untuk produk yang sudah dihapus)
    // 4. Return data atau 404 jika belum pernah menjalankan analisis
    //
    // CATATAN ELOQUENT:
    // - with() = Eager Loading (muat relasi dalam query yang efisien)
    // - orderBy('created_at', 'desc') = ambil yang terbaru
    // - first() = ambil 1 record pertama atau null
    // =========================================================================
    /**
     * Get latest AI run for STOCKS with recommendations and actions
     */
    public function latestStocks(Request $request): JsonResponse
    {
        // Cek apakah user punya langganan PRO_MAX aktif
        if (!$this->checkProMax($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires an active PRO_MAX subscription.',
            ], 403); // 403 Forbidden = tidak punya akses
        }

        // Ambil AiRun terbaru bertipe STOCKS beserta semua relasinya
        $aiRun = AiRun::where('user_id', $request->user()->id)
            ->where('type_ai', 'STOCKS')
            ->orderBy('created_at', 'desc') // Terbaru dulu
            ->with([
                // Eager load rekomendasi, tapi hanya yang produknya masih ada
                'aiRecommendations' => function ($query) {
                    $query->whereHas('product'); // Filter: produk harus masih exist
                },
                'aiRecommendations.product', // Data produk yang direkomendasikan
                'aiRecommendations.aiRecommendationActions' // Tindakan user (DONE/IGNORE)
            ])
            ->first();

        // Jika belum pernah menjalankan analisis STOCKS
        if (!$aiRun) {
            return response()->json([
                'success' => false,
                'message' => 'No AI run found for STOCKS',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest AI STOCKS run retrieved successfully',
            'data' => $aiRun,
        ]);
    }

    // =========================================================================
    // METHOD: latestBusyHours
    // URL: GET /api/ai/runs/latest/busy-hours
    // FUNGSI: Mengambil hasil prediksi JAM SIBUK terbaru milik user.
    //
    // ALUR KERJA: Sama seperti latestStocks, tapi untuk tipe 'BUSY'
    //
    // STRUKTUR RELASI (bertingkat 3 level):
    // AiRun
    //   └── BusyHourDailyForecast (prediksi per hari, 14 hari ke depan)
    //         └── BusyHourHourlyPrediction (prediksi per jam, 0-23)
    //               └── BusyHourProductPrediction (produk yang diprediksi terjual)
    //
    // CATATAN: Relasi ditulis dengan dot notation untuk eager loading bertingkat
    //          'busyHourDailyForecasts.hourlyPredictions.productPredictions'
    // =========================================================================
    /**
     * Get latest AI run for BUSY hours with predictions
     */
    public function latestBusyHours(Request $request): JsonResponse
    {
        if (!$this->checkProMax($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires an active PRO_MAX subscription.',
            ], 403);
        }

        // Ambil AiRun terbaru bertipe BUSY beserta semua prediksi bertingkat
        $aiRun = AiRun::where('user_id', $request->user()->id)
            ->where('type_ai', 'BUSY')
            ->orderBy('created_at', 'desc')
            ->with([
                // Eager load 3 level relasi sekaligus:
                // DailyForecast -> HourlyPrediction -> ProductPrediction
                'busyHourDailyForecasts.hourlyPredictions.productPredictions' => function ($query) {
                    // Hanya tampilkan prediksi produk yang masih ada di database
                    $query->whereHas('product')->with('product');
                }
            ])
            ->first();

        if (!$aiRun) {
            return response()->json([
                'success' => false,
                'message' => 'No AI run found for BUSY hours',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest AI BUSY hours run retrieved successfully',
            'data' => $aiRun,
        ]);
    }

    // =========================================================================
    // METHOD: analyze
    // URL: POST /api/ai/runs/analyze
    // FUNGSI: Menjalankan analisis AI untuk REKOMENDASI RESTOCK produk.
    //
    // ALUR KERJA LENGKAP:
    // 1. Cek langganan PRO_MAX
    // 2. Ambil semua transaksi milik user (beserta item, produk, dan stok)
    // 3. Kirim data transaksi ke API AI eksternal (FastAPI Python):
    //    - URL: {AI_URL}/predict/restock/summary
    //    - Method: POST
    //    - Body: {data: transaksi, forecast_days: 14}
    //    - Auth: Bearer token (AI_API_TOKEN)
    // 4. Jika AI berhasil:
    //    a. Buat record AiRun (status: COMPLETED)
    //    b. Loop setiap rekomendasi dari AI -> simpan ke tabel AiRecommendation
    //       (berisi: stok saat ini, qty restock, level urgensi, risiko, dll)
    //    c. Return data AiRun beserta semua rekomendasi
    // 5. Jika AI gagal:
    //    a. Buat record AiRun (status: FAILED, simpan error_message)
    //    b. Return error ke frontend
    // 6. Jika error koneksi/exception:
    //    a. Buat record AiRun (status: FAILED)
    //    b. Return error 500
    //
    // CATATAN:
    // - Http::withToken() = menambahkan header "Authorization: Bearer <token>"
    // - env() = mengambil nilai dari file .env
    // - try-catch = menangkap error yang tidak terduga
    // =========================================================================
    public function analyze( Request $request): JsonResponse
    {
        if (!$this->checkProMax($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires an active PRO_MAX subscription.',
            ], 403);
        }

        // Ambil konfigurasi API AI dari file .env
        $AI_URL = env('AI_URL');       // URL API AI Python, misal: http://localhost:8000
        $AI_API_TOKEN = env('AI_API_TOKEN'); // Token autentikasi untuk API AI

        // Ambil semua transaksi user beserta relasi detail (items -> product -> stocks)
        // Data ini yang akan dikirim ke API AI untuk dianalisis
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API (FastAPI Python)
            // Kirim data transaksi ke endpoint restock summary
            // withToken() = Authorization: Bearer <token>
            // forecast_days: 14 = prediksi untuk 14 hari ke depan
            $response = \Illuminate\Support\Facades\Http::withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/restock/summary', [
                    'data' => $transactions,
                    'forecast_days' => 14
                ]);

            // Jika response dari AI berhasil (HTTP 200)
            if ($response->successful()) {
                $responseData = $response->json(); // Parse JSON response

                // Create AiRun instance (record sesi analisis)
                $aiRun = AiRun::create([
                    'user_id' => $request->user()->id,
                    'type_ai' => 'STOCKS',     // Tipe: analisis stok
                    'status' => 'COMPLETED', // Changed to match migration Enum
                    'generated_at' => now(),
                ]);

                // Store each recommendation into the database
                // Loop setiap item rekomendasi dari AI dan simpan ke database
                foreach ($responseData['data'] as $item) {
                    AiRecommendation::create([
                        'ai_run_id'           => $aiRun->id,
                        'product_id'          => $item['product_id'],
                        'current_stock'       => $item['current_stock'],        // Stok saat ini
                        'recommed_restok_qty' => $item['recommended_restock_qty'], // Jumlah restock yang disarankan
                        'risk_level'          => $item['urgency_level'],        // Level urgensi (CRITICAL/MEDIUM/NORMAL)
                        'days_until_emty'     => $item['days_until_empty'],     // Hari sampai stok habis
                        'estimated_emty_date' => $item['estimated_empty_date'], // Tanggal estimasi stok habis
                        'risk'                => $item['risk'],                 // Skor risiko
                        'description'         => $item['urgency_description'], // Deskripsi urgensi
                        'risk_point'          => $item['risk_point'],          // Poin risiko (untuk sorting)
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'AI run started successfully',
                    // load() = lazy eager load rekomendasi setelah create
                    'data' => $aiRun->load('aiRecommendations'),
                ]);
            }

            // Failed response from API (HTTP bukan 200)
            // Tetap catat ke database bahwa analisis GAGAL beserta pesan error-nya
            AiRun::create([
                'user_id' => $request->user()->id,
                'type_ai' => 'STOCKS',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $response->body(), // Simpan body response sebagai error
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch AI recommendations',
            ], $response->status()); // Forward status code dari AI API

        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
            // Tangkap error yang tidak terduga (koneksi gagal, timeout, dll)
            AiRun::create([
                'user_id' => $request->user()->id,
                'type_ai' => 'STOCKS',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during AI analysis: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // METHOD: analyzeBusyHours
    // URL: POST /api/ai/runs/analyze-busy-hours
    // FUNGSI: Menjalankan analisis AI untuk PREDIKSI JAM SIBUK.
    //
    // ALUR KERJA LENGKAP:
    // 1. Cek langganan PRO_MAX
    // 2. Ambil semua transaksi user
    // 3. Kirim ke API AI: {AI_URL}/predict/busy-hours
    // 4. Jika berhasil, simpan hasil ke 3 level tabel:
    //    a. BusyHourDailyForecast = prediksi per hari (14 hari)
    //       Berisi: tanggal, nama hari, total transaksi/revenue prediksi,
    //               jam puncak, apakah weekend
    //    b. BusyHourHourlyPrediction = prediksi per jam (0-23)
    //       Berisi: jam, prediksi transaksi/revenue, level kesibukan, emoji
    //    c. BusyHourProductPrediction = produk yang diprediksi terjual per jam
    //       Berisi: produk, probabilitas, estimasi qty & revenue
    // 5. Return data lengkap + summary dari AI
    //
    // STRUKTUR HIERARKI DATA:
    //   AiRun (1 sesi)
    //     └── 14x DailyForecast (1 per hari)
    //           └── 24x HourlyPrediction (1 per jam)
    //                 └── Nx ProductPrediction (produk yang diprediksi terjual)
    // =========================================================================
    public function analyzeBusyHours(Request $request): JsonResponse
    {
        if (!$this->checkProMax($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires an active PRO_MAX subscription.',
            ], 403);
        }

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        // Ambil semua transaksi user untuk dikirim ke AI
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API untuk prediksi jam sibuk
            $response = Http::withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/busy-hours', [
                    'data' => $transactions,
                    'forecast_days' => 14 // Prediksi 14 hari ke depan
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData['data']; // Data prediksi dari AI

                // Create AiRun instance (sesi analisis)
                $aiRun = AiRun::create([
                    'user_id' => $request->user()->id,
                    'type_ai' => 'BUSY', // Tipe: prediksi jam sibuk
                    'status' => 'COMPLETED',
                    'generated_at' => now(),
                ]);

                // ================================================================
                // SIMPAN DATA PREDIKSI BERTINGKAT (3 level nested loop)
                //
                // Level 1: Daily Forecast (prediksi per hari)
                //   Level 2: Hourly Prediction (prediksi per jam)
                //     Level 3: Product Prediction (produk per jam)
                // ================================================================
                // Store daily forecasts
                foreach ($aiData['daily_forecasts'] as $daily) {
                    // Level 1: Simpan prediksi per hari
                    $dailyForecast = BusyHourDailyForecast::create([
                        'ai_run_id' => $aiRun->id,
                        'forecast_date' => $daily['date'],
                        'day_name' => $daily['day_name'],            // Nama hari (Senin, Selasa, dll)
                        'day_of_week' => $daily['day_of_week'],      // 0=Senin, 6=Minggu
                        'is_weekend' => $daily['is_weekend'],        // true jika Sabtu/Minggu
                        'total_predicted_trx' => $daily['total_predicted_transactions'],
                        'total_predicted_revenue' => $daily['total_predicted_revenue'],
                        'peak_hour' => $daily['peak_hour'],          // Jam paling sibuk (misal: 12)
                        'peak_hour_trx' => $daily['peak_hour_transactions'],
                        'busy_hours_count' => $daily['busy_hours_count'], // Jumlah jam sibuk
                    ]);

                    // Store hourly predictions for each day
                    // Level 2: Simpan prediksi per jam (0-23) untuk setiap hari
                    foreach ($daily['hourly_breakdown'] as $hourly) {
                        $hourlyPrediction = BusyHourHourlyPrediction::create([
                            'daily_forecast_id' => $dailyForecast->id,
                            'hour' => $hourly['hour'],                          // Jam (0-23)
                            'predicted_transactions' => $hourly['predicted_transactions'],
                            'predicted_revenue' => $hourly['predicted_revenue'],
                            'busy_level' => $hourly['busy_level'],              // Level: QUIET/NORMAL/BUSY/PEAK
                            'emoji' => $hourly['emoji'],                        // Emoji visual (🟢🟡🔴🔥)
                        ]);

                        // Store product predictions for each hour
                        // Level 3: Simpan prediksi produk yang akan terjual per jam
                        foreach ($hourly['predicted_products'] as $product) {
                            BusyHourProductPrediction::create([
                                'hourly_prediction_id' => $hourlyPrediction->id,
                                'product_id' => $product['product_id'],
                                'product_name' => $product['product_name'],
                                'probability' => $product['probability'],        // Probabilitas terjual (0-1)
                                'estimated_qty' => $product['estimated_qty'],    // Estimasi jumlah terjual
                                'estimated_revenue' => $product['estimated_revenue'],
                            ]);
                        }
                    }
                }

                // Load relationships for response
                // Muat semua relasi bertingkat untuk dikembalikan ke frontend
                $aiRun->load([
                    'busyHourDailyForecasts.hourlyPredictions.productPredictions'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Busy hour AI run completed successfully',
                    'data' => [
                        'ai_run' => $aiRun,
                        'summary' => [
                            // Ringkasan hasil analisis dari AI
                            'accuracy_percent' => $aiData['accuracy_percent'] ?? 0,     // Akurasi model ML (%)
                            'training_samples' => $aiData['training_samples'] ?? 0,     // Jumlah data training
                            'data_range' => $aiData['data_range'] ?? null,              // Rentang data yang dianalisis
                            'busiest_day' => $aiData['busiest_day'] ?? null,            // Hari tersibuk
                            'quietest_day' => $aiData['quietest_day'] ?? null,          // Hari paling sepi
                            'avg_daily_transactions' => $aiData['avg_daily_transactions'] ?? 0,
                            'avg_daily_revenue' => $aiData['avg_daily_revenue'] ?? 0,
                            'total_peak_hours' => $aiData['total_peak_hours'] ?? 0,
                            'top_peak_hours' => $aiData['top_peak_hours'] ?? [],        // Jam-jam puncak tersibuk
                        ]
                    ]
                ]);
            }

            // Failed response from API
            // Catat kegagalan ke database
            AiRun::create([
                'user_id' => $request->user()->id,
                'type_ai' => 'BUSY',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch busy hour predictions',
            ], $response->status());

        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
            AiRun::create([
                'user_id' => $request->user()->id,
                'type_ai' => 'BUSY',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during busy hour analysis: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================================
    // METHOD: updateAction
    // URL: PATCH /api/ai/recommendations/{recommendationId}/action
    // FUNGSI: Menandai rekomendasi AI sebagai DONE (sudah dilakukan)
    //         atau IGNORE (diabaikan).
    //
    // ALUR KERJA:
    // 1. Cek langganan PRO_MAX
    // 2. Validasi input: action_type harus DONE atau IGNORE
    // 3. Cari AiRecommendation berdasarkan ID
    // 4. Buat atau update AiRecommendationAction menggunakan updateOrCreate:
    //    - Jika belum ada action -> CREATE baru
    //    - Jika sudah ada action -> UPDATE yang existing
    //    updateOrCreate(kondisi_cari, data_update)
    //
    // CONTOH PENGGUNAAN:
    // - User melihat rekomendasi "Restock Sabun 20 pcs"
    // - User sudah beli sabun -> tekan tombol DONE
    // - User tidak mau -> tekan tombol IGNORE
    // =========================================================================
    /**
     * Update action for AI recommendation
     */
    public function updateAction(Request $request, int $recommendationId): JsonResponse
    {
        if (!$this->checkProMax($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'This feature requires an active PRO_MAX subscription.',
            ], 403);
        }

        // Validasi: action_type harus DONE atau IGNORE
        $request->validate([
            'action_type' => 'required|in:DONE,IGNORE',
        ]);

        // Cari rekomendasi berdasarkan ID
        $recommendation = AiRecommendation::find($recommendationId);

        if (!$recommendation) {
            return response()->json([
                'success' => false,
                'message' => 'AI recommendation not found',
            ], 404);
        }

        // Create or update action
        // updateOrCreate() = cari berdasarkan parameter pertama,
        //   jika ketemu -> update dengan parameter kedua
        //   jika tidak ketemu -> buat baru dengan gabungan parameter pertama + kedua
        $action = AiRecommendationAction::updateOrCreate(
            ['ai_recommendation_id' => $recommendationId], // Kondisi pencarian
            [
                'action_type' => $request->action_type,     // Data yang di-update/create
                'action_at' => now(),                        // Waktu tindakan
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Action updated successfully',
            'data' => $action,
        ]);
    }
}