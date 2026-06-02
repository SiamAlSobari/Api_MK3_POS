<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AiRun;
use App\Models\BusyHourDailyForecast;
use App\Models\BusyHourHourlyPrediction;
use App\Models\BusyHourProductPrediction;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeBusyHoursJob implements ShouldQueue
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
        Log::info("Starting queued busy hours analysis for user {$user->id} ({$user->name})");

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        try {
            // Retrieve transactions
            $transactions = Transaction::with(['items.product.stocks'])
                ->where('user_id', $user->id)
                ->get();

            if ($transactions->isEmpty()) {
                Log::warning("No transactions found for user {$user->id} during queued busy hours analysis.");
                return;
            }

            // Call external AI API
            $response = Http::timeout(300)
                ->withToken($AI_API_TOKEN)
                ->post($AI_URL . '/predict/busy-hours', [
                    'data' => $transactions,
                    'forecast_days' => 14,
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData['data'] ?? [];

                // Create AiRun
                $aiRun = AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'BUSY',
                    'status' => 'COMPLETED',
                    'generated_at' => now(),
                ]);

                // Store daily forecasts
                foreach ($aiData['daily_forecasts'] ?? [] as $daily) {
                    $estTrx = $daily['estimated_transactions'] ?? [];
                    $estRev = $daily['estimated_revenue'] ?? [];

                    $dailyForecast = BusyHourDailyForecast::create([
                        'ai_run_id' => $aiRun->id,
                        'forecast_date' => $daily['date'],
                        'day_name' => $daily['day_name'],
                        'day_of_week' => $daily['day_of_week'],
                        'is_weekend' => $daily['is_weekend'],
                        'total_predicted_trx' => $daily['total_predicted_transactions'] ?? ($estTrx['max'] ?? 0),
                        'est_trx_min' => $estTrx['min'] ?? null,
                        'est_trx_max' => $estTrx['max'] ?? null,
                        'est_trx_label' => $estTrx['label'] ?? null,
                        'total_predicted_revenue' => $daily['total_predicted_revenue'] ?? ($estRev['max'] ?? 0),
                        'est_revenue_min' => $estRev['min'] ?? null,
                        'est_revenue_max' => $estRev['max'] ?? null,
                        'est_revenue_label' => $estRev['label'] ?? null,
                        'peak_hour' => $daily['peak_hour'],
                        'peak_hour_label' => $daily['peak_hour_label'] ?? null,
                        'peak_hour_trx' => $daily['peak_hour_transactions'] ?? 0,
                        'busy_hours_count' => $daily['busy_hours_count'],
                    ]);

                    // Store hourly breakdown
                    foreach ($daily['hourly_breakdown'] ?? [] as $hourly) {
                        $hTrx = $hourly['estimated_transactions'] ?? [];
                        $hRev = $hourly['estimated_revenue'] ?? [];

                        $hourlyPrediction = BusyHourHourlyPrediction::create([
                            'daily_forecast_id' => $dailyForecast->id,
                            'hour' => $hourly['hour'],
                            'predicted_transactions' => $hourly['predicted_transactions'] ?? ($hTrx['max'] ?? 0),
                            'est_trx_min' => $hTrx['min'] ?? null,
                            'est_trx_max' => $hTrx['max'] ?? null,
                            'est_trx_label' => $hTrx['label'] ?? null,
                            'predicted_revenue' => $hourly['predicted_revenue'] ?? ($hRev['max'] ?? 0),
                            'est_revenue_min' => $hRev['min'] ?? null,
                            'est_revenue_max' => $hRev['max'] ?? null,
                            'est_revenue_label' => $hRev['label'] ?? null,
                            'busy_level' => $hourly['busy_level'],
                            'busy_label' => $hourly['busy_label'] ?? null,
                            'emoji' => $hourly['emoji'] ?? '',
                            'what_to_prepare' => $hourly['what_to_prepare'] ?? null,
                        ]);

                        // Store product predictions
                        foreach ($hourly['predicted_products'] ?? [] as $product) {
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

                Log::info("Successfully ran and saved queued busy hours analysis for user {$user->id}");
            } else {
                AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'BUSY',
                    'status' => 'FAILED',
                    'generated_at' => now(),
                    'error_message' => $response->body(),
                ]);
                Log::error("API error during queued busy hours analysis for user {$user->id}: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Exception in queued busy hours analysis for user {$user->id}: " . $e->getMessage());
            AiRun::create([
                'user_id' => $user->id,
                'type_ai' => 'BUSY',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
