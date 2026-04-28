<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\AiRecommendationAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRunController extends Controller
{
    /**
     * Get latest AI run with recommendations and actions
     */
    public function latest(): JsonResponse
    {
        $aiRun = AiRun::with([
            'aiRecommendations.product',
            'aiRecommendations.aiRecommendationActions'
        ])
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