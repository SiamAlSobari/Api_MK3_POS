<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiRunController extends Controller
{
    /**
     * Get the latest AI run for the authenticated user.
     * Includes aiRecommendations and aiRecommendationActions.
     */
    public function latest(Request $request): JsonResponse
    {
        $aiRun = AiRun::with([
                'aiRecommendations.aiRecommendationActions',
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if (!$aiRun) {
            return response()->json([
                'message' => 'No AI run found.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Latest AI run retrieved successfully.',
            'data' => $aiRun,
        ]);
    }
}

