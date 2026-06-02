<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AiRun;
use App\Models\BusyHourDailyForecast;
use App\Models\BusyHourHourlyPrediction;
use App\Models\BusyHourProductPrediction;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeBusyHours extends Command
{
    protected $signature = 'ai:analyze-busy-hours';
    protected $description = 'Run AI busy hours analysis for all active PRO users';

    public function handle()
    {
        $this->info('Starting AI busy hours analysis for PRO users...');

        // 1. Ambil semua user dengan langganan PRO aktif
        $proUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('plan_name', 'PRO')
                  ->where('status', 'ACTIVE')
                  ->whereDate('end_date', '>=', \Carbon\Carbon::today());
        })->get();

        if ($proUsers->isEmpty()) {
            $this->info('No active PRO users found.');
            return;
        }

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        foreach ($proUsers as $user) {
            $this->info("Processing user: {$user->id} - {$user->name}");

            try {
                // 2. Ambil data transaksi user
                $transactions = Transaction::with(['items.product.stocks'])
                    ->where('user_id', $user->id)
                    ->get();

                if ($transactions->isEmpty()) {
                    $this->warn("No transactions found for user {$user->id}. Skipping.");
                    continue;
                }

                // 3. Panggil API AI untuk analisis jam sibuk
                $response = Http::timeout(300)
                    ->withToken($AI_API_TOKEN)
                    ->post($AI_URL . '/predict/busy-hours', [
                        'data' => $transactions,
                        'forecast_days' => 14,
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();
                    $aiData = $responseData['data'] ?? [];

                    // Simpan ke tabel AiRun
                    $aiRun = AiRun::create([
                        'user_id' => $user->id,
                        'type_ai' => 'BUSY',
                        'status' => 'COMPLETED',
                        'generated_at' => now(),
                    ]);

                    // Simpan data forecast harian
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

                        // Simpan breakdown per jam
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

                            // Simpan prediksi produk terlaris per jam
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

                    $this->info("Successfully ran and saved busy hours analysis for user {$user->id}");
                } else {
                    AiRun::create([
                        'user_id' => $user->id,
                        'type_ai' => 'BUSY',
                        'status' => 'FAILED',
                        'generated_at' => now(),
                        'error_message' => $response->body(),
                    ]);
                    $this->error("API error for user {$user->id}: " . $response->body());
                }

            } catch (\Exception $e) {
                Log::error("Cron busy hours analysis error for user {$user->id}: " . $e->getMessage());
                AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'BUSY',
                    'status' => 'FAILED',
                    'generated_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Exception for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('AI busy hours analysis run finished.');
    }
}
