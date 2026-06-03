<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class BillingController extends Controller
{
    private static array $plans = [
        'PRO' => [
            'price' => 29000,
            'duration_days' => 30,
        ],
    ];

    #[OA\Post(
        path: "/billing/subscribe",
        summary: "Subscribe to PRO plan via Midtrans",
        tags: ["Billing"],
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/BillingSubscribeRequest")
        ),
        responses: [
            new OA\Response(response: 201, description: "Subscription created", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "subscription", ref: "#/components/schemas/Subscription"),
                    new OA\Property(property: "payment", ref: "#/components/schemas/Payment"),
                    new OA\Property(property: "payment_url", type: "string"),
                ],
                type: "object"
            )),
            new OA\Response(response: 422, description: "Validation error"),
        ]
    )]
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_name' => 'required|string|in:PRO',
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

    #[OA\Post(
        path: "/billing/webhook",
        summary: "Midtrans payment webhook (public)",
        tags: ["Billing"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: "application/json",
                schema: new OA\Schema(properties: [
                    new OA\Property(property: "order_id", type: "string"),
                    new OA\Property(property: "transaction_status", type: "string"),
                    new OA\Property(property: "payment_type", type: "string"),
                    new OA\Property(property: "transaction_time", type: "string"),
                    new OA\Property(property: "gross_amount", type: "string"),
                ], type: "object")
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Webhook processed"),
            new OA\Response(response: 400, description: "Invalid notification"),
        ]
    )]
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
            if ($status === 'EXPIRE')
                $status = 'EXPIRED';
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
    // Note: webhookTest is registered separately in routes
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

    #[OA\Get(
        path: "/billing/active",
        summary: "Get active subscription",
        tags: ["Billing"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Active subscription data", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", ref: "#/components/schemas/Subscription"),
                ],
                type: "object"
            )),
        ]
    )]
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
