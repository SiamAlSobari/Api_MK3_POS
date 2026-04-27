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
            'payment_type' => 'nullable|string|in:GOPAY,QRIS,BCA,BNI',
        ]);

        $planName = strtoupper($data['plan_name']);
        $plan = self::$plans[$planName];
        $paymentType = $data['payment_type'] ?? 'QRIS';
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

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'order_id' => Str::orderedUuid()->toString(),
            'gross_amount' => $plan['price'],
            'payment_type' => $paymentType,
            'transaction_time' => null,
            'status' => 'PENDING',
        ]);

        return response()->json([
            'message' => 'Subscription created. Please complete payment.',
            'subscription' => $subscription,
            'payment' => $payment,
        ], 201);
    }

    public function webhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|string|exists:payments,order_id',
            'status' => 'required|string|in:PENDING,SETTLEMENT,EXPIRED,CANCEL,DENY',
            'transaction_time' => 'nullable|date',
        ]);

        $payment = Payment::where('order_id', $data['order_id'])->first();

        $payment->update([
            'status' => $data['status'],
            'transaction_time' => $data['transaction_time'] ? Carbon::parse($data['transaction_time']) : now(),
        ]);

        $subscription = $payment->subscription;

        if ($data['status'] === 'SETTLEMENT') {
            $subscription->update(['status' => 'ACTIVE']);
        } elseif (in_array($data['status'], ['EXPIRED', 'CANCEL', 'DENY'], true)) {
            $subscription->update(['status' => 'PENDING']);
        }

        return response()->json([
            'message' => 'Webhook processed.',
            'payment' => $payment,
            'subscription' => $subscription,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $activeSubscriptions = Subscription::with(['payments'])
            ->where('user_id', $request->user()->id)
            ->where('status', 'ACTIVE')
            ->whereDate('end_date', '>=', Carbon::today())
            ->get();

        return response()->json([
            'message' => 'Active billing data retrieved successfully.',
            'data' => $activeSubscriptions,
        ]);
    }
}
