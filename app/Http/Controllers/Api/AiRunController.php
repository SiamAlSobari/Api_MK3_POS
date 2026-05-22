<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\AiRecommendationAction;
use App\Models\AiPortfolioInsight;
use App\Models\BusyHourDailyForecast;
use App\Models\BusyHourHourlyPrediction;
use App\Models\BusyHourProductPrediction;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiRunController extends Controller
{
    private function checkPro($user): bool
    {
        return \App\Models\Subscription::where("user_id", $user->id)
            ->where("plan_name", "PRO")
            ->where("status", "ACTIVE")
            ->whereDate("end_date", ">=", \Carbon\Carbon::today())
            ->exists();
    }

    /**
     * Get latest AI run for STOCKS with recommendations and actions
     */
    public function latestStocks(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $aiRun = AiRun::where("user_id", $request->user()->id)
            ->where("type_ai", "STOCKS")
            ->orderBy("created_at", "desc")
            ->with([
                "aiRecommendations" => function ($query) {
                    $query->whereHas("product");
                },
                "aiRecommendations.product",
                "aiRecommendations.aiRecommendationActions",
            ])
            ->first();

        if (!$aiRun) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "No AI run found for STOCKS",
                    "data" => null,
                ],
                404,
            );
        }

        return response()->json([
            "success" => true,
            "message" => "Latest AI STOCKS run retrieved successfully",
            "data" => $aiRun,
        ]);
    }

    /**
     * Get latest AI run for BUSY hours with predictions
     */
    public function latestBusyHours(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $aiRun = AiRun::where("user_id", $request->user()->id)
            ->where("type_ai", "BUSY")
            ->orderBy("created_at", "desc")
            ->with([
                "busyHourDailyForecasts.hourlyPredictions.productPredictions" => function (
                    $query,
                ) {
                    $query->whereHas("product")->with("product");
                },
            ])
            ->first();

        if (!$aiRun) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "No AI run found for BUSY hours",
                    "data" => null,
                ],
                404,
            );
        }

        return response()->json([
            "success" => true,
            "message" => "Latest AI BUSY hours run retrieved successfully",
            "data" => $aiRun,
        ]);
    }

    /**
     * Get latest AI run for PORTFOLIO with insights
     */
    public function latestPortfolio(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $aiRun = AiRun::where("user_id", $request->user()->id)
            ->where("type_ai", "PORTFOLIO")
            ->orderBy("created_at", "desc")
            ->with("portfolioInsight")
            ->first();

        if (!$aiRun) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "No AI run found for PORTFOLIO insights",
                    "data" => null,
                ],
                404,
            );
        }

        return response()->json([
            "success" => true,
            "message" => "Latest AI PORTFOLIO run retrieved successfully",
            "data" => $aiRun,
        ]);
    }

    public function analyze(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $AI_URL = env("AI_URL");
        $AI_API_TOKEN = env("AI_API_TOKEN");
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API
            $response = \Illuminate\Support\Facades\Http::withToken(
                $AI_API_TOKEN,
            )->post(
                $AI_URL . "/predict/restock/summary?include_seasonal=true",
                [
                    "data" => $transactions,
                    "forecast_days" => 14,
                ],
            );

            if ($response->successful()) {
                $responseData = $response->json();

                // Create AiRun instance with seasonal insight & total products
                $aiRun = AiRun::create([
                    "user_id" => $request->user()->id,
                    "type_ai" => "STOCKS",
                    "status" => "COMPLETED",
                    "generated_at" => now(),
                    "seasonal_insight" =>
                        $responseData["seasonal_insight"] ?? null,
                    "total_products" =>
                        $responseData["total_products"] ??
                        count($responseData["data"] ?? []),
                ]);

                // Store each recommendation into the database
                foreach ($responseData["data"] as $item) {
                    $restockRec = $item["restock_recommendation"] ?? [];
                    $seasonalRestock = $item["seasonal_restock"] ?? [];

                    AiRecommendation::create([
                        "ai_run_id" => $aiRun->id,
                        "product_id" => $item["product_id"],
                        "product_name" => $item["product_name"] ?? null,
                        "product_price" => $item["product_price"] ?? null,
                        "current_stock" => $item["current_stock"],
                        "avg_daily_sales" => $item["avg_daily_sales"] ?? null,
                        "recommed_restok_qty" =>
                            $restockRec["max"] ??
                            ($item["recommended_restock_qty"] ?? 0),
                        "restock_min" => $restockRec["min"] ?? null,
                        "restock_max" => $restockRec["max"] ?? null,
                        "restock_label" => $restockRec["label"] ?? null,
                        "target_days_coverage" =>
                            $restockRec["target_days_coverage"] ?? null,
                        "risk_level" => $item["urgency_level"] ?? null,
                        "urgency_description" =>
                            $item["urgency_description"] ?? null,
                        "days_until_emty" => $item["days_until_empty"] ?? null,
                        "estimated_emty_date" =>
                            $item["estimated_empty_date"] ?? null,
                        "risk" => $item["risk"] ?? null,
                        "description" => $item["urgency_description"] ?? null,
                        "risk_point" => $item["risk_point"] ?? 0,
                        "stock_timeline" => $item["stock_timeline"] ?? null,
                        // Seasonal fields
                        "seasonal_min" => $seasonalRestock["min"] ?? null,
                        "seasonal_max" => $seasonalRestock["max"] ?? null,
                        "seasonal_label" => $seasonalRestock["label"] ?? null,
                        "seasonal_holiday" =>
                            $seasonalRestock["holiday"] ?? null,
                        "seasonal_reason" => $seasonalRestock["reason"] ?? null,
                    ]);
                }

                return response()->json([
                    "success" => true,
                    "message" => "AI run started successfully",
                    "data" => $aiRun->load("aiRecommendations"),
                ]);
            }

            // Failed response from API
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "STOCKS",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $response->body(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to fetch AI recommendations",
                ],
                $response->status(),
            );
        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "STOCKS",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $e->getMessage(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "An error occurred during AI analysis: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function analyzeBusyHours(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $AI_URL = env("AI_URL");
        $AI_API_TOKEN = env("AI_API_TOKEN");
        $transactions = Transaction::with(["items.product.stocks"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API
            $response = Http::withToken($AI_API_TOKEN)->post(
                $AI_URL . "/predict/busy-hours",
                [
                    "data" => $transactions,
                    "forecast_days" => 14,
                ],
            );

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData["data"];

                // Create AiRun instance
                $aiRun = AiRun::create([
                    "user_id" => $request->user()->id,
                    "type_ai" => "BUSY",
                    "status" => "COMPLETED",
                    "generated_at" => now(),
                ]);

                // Store daily forecasts with range-based data
                foreach ($aiData["daily_forecasts"] ?? [] as $daily) {
                    $estTrx = $daily["estimated_transactions"] ?? [];
                    $estRev = $daily["estimated_revenue"] ?? [];

                    $dailyForecast = BusyHourDailyForecast::create([
                        "ai_run_id" => $aiRun->id,
                        "forecast_date" => $daily["date"],
                        "day_name" => $daily["day_name"],
                        "day_of_week" => $daily["day_of_week"],
                        "is_weekend" => $daily["is_weekend"],
                        "total_predicted_trx" =>
                            $daily["total_predicted_transactions"] ??
                            ($estTrx["max"] ?? 0),
                        "est_trx_min" => $estTrx["min"] ?? null,
                        "est_trx_max" => $estTrx["max"] ?? null,
                        "est_trx_label" => $estTrx["label"] ?? null,
                        "total_predicted_revenue" =>
                            $daily["total_predicted_revenue"] ??
                            ($estRev["max"] ?? 0),
                        "est_revenue_min" => $estRev["min"] ?? null,
                        "est_revenue_max" => $estRev["max"] ?? null,
                        "est_revenue_label" => $estRev["label"] ?? null,
                        "peak_hour" => $daily["peak_hour"],
                        "peak_hour_label" => $daily["peak_hour_label"] ?? null,
                        "peak_hour_trx" =>
                            $daily["peak_hour_transactions"] ?? 0,
                        "busy_hours_count" => $daily["busy_hours_count"],
                    ]);

                    // Store hourly predictions with range-based data
                    foreach ($daily["hourly_breakdown"] as $hourly) {
                        $hTrx = $hourly["estimated_transactions"] ?? [];
                        $hRev = $hourly["estimated_revenue"] ?? [];

                        $hourlyPrediction = BusyHourHourlyPrediction::create([
                            "daily_forecast_id" => $dailyForecast->id,
                            "hour" => $hourly["hour"],
                            "predicted_transactions" =>
                                $hourly["predicted_transactions"] ??
                                ($hTrx["max"] ?? 0),
                            "est_trx_min" => $hTrx["min"] ?? null,
                            "est_trx_max" => $hTrx["max"] ?? null,
                            "est_trx_label" => $hTrx["label"] ?? null,
                            "predicted_revenue" =>
                                $hourly["predicted_revenue"] ??
                                ($hRev["max"] ?? 0),
                            "est_revenue_min" => $hRev["min"] ?? null,
                            "est_revenue_max" => $hRev["max"] ?? null,
                            "est_revenue_label" => $hRev["label"] ?? null,
                            "busy_level" => $hourly["busy_level"],
                            "busy_label" => $hourly["busy_label"] ?? null,
                            "emoji" => $hourly["emoji"] ?? "",
                            "what_to_prepare" =>
                                $hourly["what_to_prepare"] ?? null,
                        ]);

                        // Store product predictions for each hour
                        $products = $hourly["predicted_products"] ?? [];
                        foreach ($products as $product) {
                            BusyHourProductPrediction::create([
                                "hourly_prediction_id" => $hourlyPrediction->id,
                                "product_id" => $product["product_id"],
                                "product_name" => $product["product_name"],
                                "probability" => $product["probability"],
                                "estimated_qty" => $product["estimated_qty"],
                                "estimated_revenue" =>
                                    $product["estimated_revenue"],
                            ]);
                        }
                    }
                }

                // Load relationships for response
                $aiRun->load([
                    "busyHourDailyForecasts.hourlyPredictions.productPredictions",
                ]);

                return response()->json([
                    "success" => true,
                    "message" => "Busy hour AI run completed successfully",
                    "data" => [
                        "ai_run" => $aiRun,
                        "summary" => [
                            "analysis_date" =>
                                $aiData["analysis_date"] ??
                                now()->toDateTimeString(),
                            "forecast_days" => $aiData["forecast_days"] ?? 14,
                            "busiest_day" => $aiData["busiest_day"] ?? null,
                            "quietest_day" => $aiData["quietest_day"] ?? null,
                            "total_peak_hours" =>
                                $aiData["total_peak_hours"] ?? 0,
                            "top_peak_hours" => $aiData["top_peak_hours"] ?? [],
                        ],
                    ],
                ]);
            }

            // Failed response from API
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "BUSY",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $response->body(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to fetch busy hour predictions",
                ],
                $response->status(),
            );
        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "BUSY",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $e->getMessage(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "An error occurred during busy hour analysis: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Generate AI portfolio insights from transaction data
     */
    public function generatePortfolio(Request $request): JsonResponse
    {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $AI_URL = env("AI_URL");
        $AI_API_TOKEN = env("AI_API_TOKEN");
        $transactions = Transaction::with(["items.product"])
            ->where("user_id", $request->user()->id)
            ->get();

        try {
            // Hit external AI API
            $response = Http::withToken($AI_API_TOKEN)->post(
                $AI_URL . "/insights/generate",
                [
                    "data" => $transactions,
                ],
            );

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData["data"];
                $summary = $aiData["summary"] ?? [];

                // Create AiRun instance
                $aiRun = AiRun::create([
                    "user_id" => $request->user()->id,
                    "type_ai" => "PORTFOLIO",
                    "status" => "COMPLETED",
                    "generated_at" => now(),
                ]);

                // Create AiPortfolioInsight
                AiPortfolioInsight::create([
                    "ai_run_id" => $aiRun->id,
                    "user_id" => $request->user()->id,
                    "insight" => $aiData["insight"] ?? null,
                    "tanggal_laporan" => $summary["tanggal_laporan"] ?? null,
                    "periode" => $summary["periode"] ?? null,
                    "total_omset_minggu_ini" =>
                        $summary["total_omset_minggu_ini"] ?? 0,
                    "total_transaksi" => $summary["total_transaksi"] ?? 0,
                    "rata_rata_transaksi_per_hari" =>
                        $summary["rata_rata_transaksi_per_hari"] ?? 0,
                    "rata_rata_omset_per_hari" =>
                        $summary["rata_rata_omset_per_hari"] ?? 0,
                    "bintang_warung" => $summary["bintang_warung"] ?? null,
                    "hari_ramai_tanggal" =>
                        $summary["hari_paling_ramai"]["tanggal"] ?? null,
                    "hari_ramai_omset" =>
                        $summary["hari_paling_ramai"]["omset"] ?? null,
                    "produk_kurang_laku" =>
                        $summary["produk_kurang_laku"] ?? null,
                    "source" => $aiData["source"] ?? null,
                    "generated_at" => $aiData["generated_at"] ?? now(),
                    "valid_until" => $aiData["valid_until"] ?? null,
                ]);

                // Load relationships for response
                $aiRun->load("portfolioInsight");

                return response()->json([
                    "success" => true,
                    "message" => "Weekly portfolio generated successfully",
                    "data" => $aiRun,
                ]);
            }

            // Failed response from API
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "PORTFOLIO",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $response->body(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" => "Failed to generate weekly portfolio",
                ],
                $response->status(),
            );
        } catch (\Exception $e) {
            // Error connecting to API or inserting to DB
            AiRun::create([
                "user_id" => $request->user()->id,
                "type_ai" => "PORTFOLIO",
                "status" => "FAILED",
                "generated_at" => now(),
                "error_message" => $e->getMessage(),
            ]);

            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "An error occurred during portfolio generation: " .
                        $e->getMessage(),
                ],
                500,
            );
        }
    }

    /**
     * Update action for AI recommendation
     */
    public function updateAction(
        Request $request,
        int $recommendationId,
    ): JsonResponse {
        if (!$this->checkPro($request->user())) {
            return response()->json(
                [
                    "success" => false,
                    "message" =>
                        "This feature requires an active PRO subscription.",
                ],
                403,
            );
        }

        $request->validate([
            "action_type" => "required|in:DONE,IGNORE",
        ]);

        $recommendation = AiRecommendation::find($recommendationId);

        if (!$recommendation) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "AI recommendation not found",
                ],
                404,
            );
        }

        // Create or update action
        $action = AiRecommendationAction::updateOrCreate(
            ["ai_recommendation_id" => $recommendationId],
            [
                "action_type" => $request->action_type,
                "action_at" => now(),
            ],
        );

        return response()->json([
            "success" => true,
            "message" => "Action updated successfully",
            "data" => $action,
        ]);
    }
}
