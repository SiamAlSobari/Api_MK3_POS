<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AiRun;
use App\Models\AiRecommendation;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeStocks extends Command
{
    protected $signature = 'ai:analyze-stocks';
    protected $description = 'Run AI stock analysis for all active PRO users';

    public function handle()
    {
        $this->info('Starting AI stock analysis for PRO users...');

        // 1. Ambil semua user yang memiliki langganan PRO aktif
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
                // 2. Ambil data transaksi user beserta relasi produk dan stoknya
                $transactions = Transaction::with(['items.product.stocks'])
                    ->where('user_id', $user->id)
                    ->get();

                if ($transactions->isEmpty()) {
                    $this->warn("No transactions found for user {$user->id}. Skipping.");
                    continue;
                }

                // 3. Panggil API AI untuk analisis stok
                $response = Http::timeout(300)
                    ->withToken($AI_API_TOKEN)
                    ->post($AI_URL . '/predict/restock/summary?include_seasonal=true', [
                        'data' => $transactions,
                        'forecast_days' => 14,
                    ]);

                if ($response->successful()) {
                    $responseData = $response->json();

                    // Simpan hasil ke tabel AiRun
                    $aiRun = AiRun::create([
                        'user_id' => $user->id,
                        'type_ai' => 'STOCKS',
                        'status' => 'COMPLETED',
                        'generated_at' => now(),
                        'seasonal_insight' => $responseData['seasonal_insight'] ?? null,
                        'total_products' => $responseData['total_products'] ?? count($responseData['data'] ?? []),
                    ]);

                    // Simpan rekomendasi per produk
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

                    $this->info("Successfully ran and saved stock analysis for user {$user->id}");
                } else {
                    // Jika API AI mengembalikan response gagal
                    AiRun::create([
                        'user_id' => $user->id,
                        'type_ai' => 'STOCKS',
                        'status' => 'FAILED',
                        'generated_at' => now(),
                        'error_message' => $response->body(),
                    ]);
                    $this->error("API error for user {$user->id}: " . $response->body());
                }

            } catch (\Exception $e) {
                Log::error("Cron stock analysis error for user {$user->id}: " . $e->getMessage());
                AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'STOCKS',
                    'status' => 'FAILED',
                    'generated_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
                $this->error("Exception for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('AI stock analysis run finished.');
    }
}
