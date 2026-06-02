<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeStocksJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 360;

    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->user;
        Log::info("Starting queued stock analysis for user {$user->id} ({$user->name})");

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        try {
            // Retrieve user transactions with relations
            $transactions = Transaction::with(['items.product.stocks'])
                ->where('user_id', $user->id)
                ->get();

            if ($transactions->isEmpty()) {
                Log::warning("No transactions found for user {$user->id} during queued stock analysis.");
                return;
            }

            // Call external AI API
            $response = Http::timeout(300)
                ->withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/restock/summary?include_seasonal=true', [
                    'data' => $transactions,
                    'forecast_days' => 14,
                ]);

            if ($response->successful()) {
                $responseData = $response->json();

                // Create AiRun
                $aiRun = AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'STOCKS',
                    'status' => 'COMPLETED',
                    'generated_at' => now(),
                    'seasonal_insight' => $responseData['seasonal_insight'] ?? null,
                    'total_products' => $responseData['total_products'] ?? count($responseData['data'] ?? []),
                ]);

                // Store recommendations
                foreach ($responseData['data'] ?? [] as $item) {
                    $restockRec = $item['restock_recommendation'] ?? [];
                    $seasonalRestock = $item['seasonal_restock'] ?? [];

                    $recommendation = AiRecommendation::create([
                        'ai_run_id' => $aiRun->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'] ?? null,
                        'product_price' => $item['product_price'] ?? null,
                        'current_stock' => $item['current_stock'],
                        'avg_daily_sales' => $item['avg_daily_sales'] ?? null,
                        'recommed_restok_qty' => $restockRec['max'] ?? ($item['recommended_restock_qty'] ?? 0),
                        'restock_min' => $restockRec['min'] ?? null,
                        'restock_max' => $restockRec['max'] ?? null,
                        'restock_label' => $restockRec['label'] ?? null,
                        'target_days_coverage' => $restockRec['target_days_coverage'] ?? null,
                        'risk_level' => $item['urgency_level'] ?? null,
                        'urgency_description' => $item['urgency_description'] ?? null,
                        'days_until_emty' => $item['days_until_empty'] ?? null,
                        'estimated_emty_date' => $item['estimated_empty_date'] ?? null,
                        'risk' => $item['risk'] ?? null,
                        'description' => $item['urgency_description'] ?? null,
                        'risk_point' => $item['risk_point'] ?? 0,
                        'stock_timeline' => $item['stock_timeline'] ?? null,
                    ]);

                    if (!empty($seasonalRestock)) {
                        $recommendation->seasonalRecommendation()->create([
                            'min' => $seasonalRestock['min'] ?? null,
                            'max' => $seasonalRestock['max'] ?? null,
                            'label' => $seasonalRestock['label'] ?? null,
                            'holiday' => $seasonalRestock['holiday'] ?? null,
                            'reason' => $seasonalRestock['reason'] ?? null,
                        ]);
                    }
                }

                Log::info("Successfully ran and saved queued stock analysis for user {$user->id}");
            } else {
                AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'STOCKS',
                    'status' => 'FAILED',
                    'generated_at' => now(),
                    'error_message' => $response->body(),
                ]);
                Log::error("API error during queued stock analysis for user {$user->id}: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Exception in queued stock analysis for user {$user->id}: " . $e->getMessage());
            AiRun::create([
                'user_id' => $user->id,
                'type_ai' => 'STOCKS',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
