<?php

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
    private static array $plans = [
        'PRO' => [
            'price' => 12000,
            'duration_days' => 30,
        ],
        'PRO_MAX' => [
            'price' => 79000,
            'duration_days' => 30,
        ],
    ];

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_name' => 'required|string|in:PRO,PRO_MAX',
        ]);

        $planName = strtoupper($data['plan_name']);
        $plan = self::$plans[$planName];
        $startDate = Carbon::today();
        $endDate = $startDate->copy()->addDays($plan['duration_days']);

        $subscription = Subscription::create([
            'user_id' => $request->user()->id,
            'plan_name' => $planName,
            'price' => $plan['price'],
            'duration_days' => $plan['duration_days'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => 'PENDING',
        ]);

        $orderId = Str::orderedUuid()->toString();

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'order_id' => $orderId,
            'gross_amount' => $plan['price'],
            'payment_type' => 'MIDTRANS',
            'transaction_time' => null,
            'status' => 'PENDING',
        ]);

        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

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
            $paymentUrl = \Midtrans\Snap::createTransaction($params)->redirect_url;
            return response()->json([
                'message' => 'Subscription created. Please complete payment.',
                'subscription' => $subscription,
                'payment' => $payment,
                'payment_url' => $paymentUrl,
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);

        try {
            $notification = new \Midtrans\Notification();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $payment = Payment::where('order_id', $notification->order_id)->first();
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $status = 'PENDING';
        if ($notification->transaction_status == 'settlement' || $notification->transaction_status == 'capture') {
            $status = 'SETTLEMENT';
        } elseif (in_array($notification->transaction_status, ['cancel', 'deny', 'expire'])) {
            $status = strtoupper($notification->transaction_status);
            if ($status === 'EXPIRE') $status = 'EXPIRED';
        }

        $payment->update([
            'status' => $status,
            'transaction_time' => $notification->transaction_time ? Carbon::parse($notification->transaction_time) : now(),
            'payment_type' => $notification->payment_type,
        ]);

        $subscription = $payment->subscription;

        if ($status === 'SETTLEMENT') {
            $subscription->update(['status' => 'ACTIVE']);
        } elseif (in_array($status, ['EXPIRED', 'CANCEL', 'DENY'], true)) {
            $subscription->update(['status' => 'PENDING']);
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'payment' => $payment,
            'subscription' => $subscription,
        ]);
    }

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

    public function active(Request $request): JsonResponse
    {
        $activeSubscription = Subscription::with(['payments'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', '>=', Carbon::today())
            ->latest()
            ->first();

        return response()->json([
            'message' => 'Active billing data retrieved successfully.',
            'data' => $activeSubscription,
        ]);
    }
}
