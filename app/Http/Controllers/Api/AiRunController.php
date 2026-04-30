<?php

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
    /**
     * Get latest AI run for STOCKS with recommendations and actions
     */
    public function latestStocks(Request $request): JsonResponse
    {
        $aiRun = AiRun::where('user_id', $request->user()->id)
            ->where('type_ai', 'STOCKS')
            ->orderBy('created_at', 'desc')
            ->with([
                'aiRecommendations' => function ($query) {
                    $query->whereHas('product');
                },
                'aiRecommendations.product',
                'aiRecommendations.aiRecommendationActions'
            ])
            ->first();

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

    /**
     * Get latest AI run for BUSY hours with predictions
     */
    public function latestBusyHours(Request $request): JsonResponse
    {
        $aiRun = AiRun::where('user_id', $request->user()->id)
            ->where('type_ai', 'BUSY')
            ->orderBy('created_at', 'desc')
            ->with([
                'busyHourDailyForecasts.hourlyPredictions.productPredictions' => function ($query) {
                    $query->whereHas('product');
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

    public function analyze( Request $request): JsonResponse
    {
        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API
            $response = \Illuminate\Support\Facades\Http::withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/restock/summary', [
                    'data' => $transactions,
                    'forecast_days' => 14
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Create AiRun instance
                $aiRun = AiRun::create([
                    'user_id' => $request->user()->id,
                    'type_ai' => 'STOCKS',
                    'status' => 'COMPLETED', // Changed to match migration Enum
                    'generated_at' => now(),
                ]);

                // Store each recommendation into the database
                foreach ($responseData['data'] as $item) {
                    AiRecommendation::create([
                        'ai_run_id'           => $aiRun->id,
                        'product_id'          => $item['product_id'],
                        'current_stock'       => $item['current_stock'],
                        'recommed_restok_qty' => $item['recommended_restock_qty'],
                        'risk_level'          => $item['urgency_level'],
                        'days_until_emty'     => $item['days_until_empty'],
                        'estimated_emty_date' => $item['estimated_empty_date'],
                        'risk'                => $item['risk'],
                        'description'         => $item['urgency_description'],
                        'risk_point'          => $item['risk_point'],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'AI run started successfully',
                    'data' => $aiRun->load('aiRecommendations'),
                ]);
            }

            // Failed response from API
            AiRun::create([
                'user_id' => $request->user()->id,
                'type_ai' => 'STOCKS',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch AI recommendations',
            ], $response->status());

        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
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

    public function analyzeBusyHours(Request $request): JsonResponse
    {
        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API
            $response = Http::withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/busy-hours', [
                    'data' => $transactions,
                    'forecast_days' => 14
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData['data'];

                // Create AiRun instance
                $aiRun = AiRun::create([
                    'user_id' => $request->user()->id,
                    'type_ai' => 'BUSY',
                    'status' => 'COMPLETED',
                    'generated_at' => now(),
                ]);

                // Store daily forecasts
                foreach ($aiData['daily_forecasts'] as $daily) {
                    $dailyForecast = BusyHourDailyForecast::create([
                        'ai_run_id' => $aiRun->id,
                        'forecast_date' => $daily['date'],
                        'day_name' => $daily['day_name'],
                        'day_of_week' => $daily['day_of_week'],
                        'is_weekend' => $daily['is_weekend'],
                        'total_predicted_trx' => $daily['total_predicted_transactions'],
                        'total_predicted_revenue' => $daily['total_predicted_revenue'],
                        'peak_hour' => $daily['peak_hour'],
                        'peak_hour_trx' => $daily['peak_hour_transactions'],
                        'busy_hours_count' => $daily['busy_hours_count'],
                    ]);

                    // Store hourly predictions for each day
                    foreach ($daily['hourly_breakdown'] as $hourly) {
                        $hourlyPrediction = BusyHourHourlyPrediction::create([
                            'daily_forecast_id' => $dailyForecast->id,
                            'hour' => $hourly['hour'],
                            'predicted_transactions' => $hourly['predicted_transactions'],
                            'predicted_revenue' => $hourly['predicted_revenue'],
                            'busy_level' => $hourly['busy_level'],
                            'emoji' => $hourly['emoji'],
                        ]);

                        // Store product predictions for each hour
                        foreach ($hourly['predicted_products'] as $product) {
                            BusyHourProductPrediction::create([
                                'hourly_prediction_id' => $hourlyPrediction->id,
                                'product_id' => $product['product_id'],
                                'product_name' => $product['product_name'],
                                'probability' => $product['probability'],
                                'estimated_qty' => $product['estimated_qty'],
                                'estimated_revenue' => $product['estimated_revenue'],
                            ]);
                        }
                    }
                }

                // Load relationships for response
                $aiRun->load([
                    'busyHourDailyForecasts.hourlyPredictions.productPredictions'
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Busy hour AI run completed successfully',
                    'data' => [
                        'ai_run' => $aiRun,
                        'summary' => [
                            'accuracy_percent' => $aiData['accuracy_percent'],
                            'training_samples' => $aiData['training_samples'],
                            'data_range' => $aiData['data_range'],
                            'busiest_day' => $aiData['busiest_day'],
                            'quietest_day' => $aiData['quietest_day'],
                            'avg_daily_transactions' => $aiData['avg_daily_transactions'],
                            'avg_daily_revenue' => $aiData['avg_daily_revenue'],
                            'total_peak_hours' => $aiData['total_peak_hours'],
                            'top_peak_hours' => $aiData['top_peak_hours'],
                        ]
                    ]
                ]);
            }

            // Failed response from API
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

    /**
     * Update action for AI recommendation
     */
    public function updateAction(Request $request, int $recommendationId): JsonResponse
    {
        $request->validate([
            'action_type' => 'required|in:DONE,IGNORE',
        ]);

        $recommendation = AiRecommendation::find($recommendationId);

        if (!$recommendation) {
            return response()->json([
                'success' => false,
                'message' => 'AI recommendation not found',
            ], 404);
        }

        // Create or update action
        $action = AiRecommendationAction::updateOrCreate(
            ['ai_recommendation_id' => $recommendationId],
            [
                'action_type' => $request->action_type,
                'action_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Action updated successfully',
            'data' => $action,
        ]);
    }
}