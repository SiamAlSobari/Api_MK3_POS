<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class AiRunController extends Controller
{
    /**
     * Get latest AI run with recommendations and actions
     */
    public function latest(Request $request): JsonResponse
    {
        $aiRun = AiRun::with([
            'aiRecommendations.product',
            'aiRecommendations.aiRecommendationActions'
        ])
        ->where('user_id', $request->user()->id)
        ->orderBy('created_at', 'desc')
        ->first();

        if (!$aiRun) {
            return response()->json([
                'success' => false,
                'message' => 'No AI run found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest AI run retrieved successfully',
            'data' => $aiRun,
        ]);
    }

    public function analyze( Request $request): Jsonresponse
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