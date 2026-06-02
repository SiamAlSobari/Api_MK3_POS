<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AiRun;
use App\Models\AiPortfolioInsight;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GeneratePortfolios extends Command
{
    protected $signature = 'ai:generate-portfolios';
    protected $description = 'Generate AI weekly portfolio insights for active PRO users';

    public function handle()
    {
        $this->info('Starting AI portfolio generation for PRO users...');

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
            // 2. Cek kapan analisis PORTFOLIO terakhir untuk user ini
            $lastRun = AiRun::where('user_id', $user->id)
                ->where('type_ai', 'PORTFOLIO')
                ->latest('generated_at')
                ->first();

            // Jika belum pernah ada, atau sudah lebih dari 7 hari, jalankan analisis baru
            if (!$lastRun || Carbon::parse($lastRun->generated_at)->diffInDays(now()) >= 7) {
                $this->info("Processing portfolio for user: {$user->id} - {$user->name}");
                
                try {
                    // 3. Ambil data transaksi user
                    $transactions = Transaction::with(['items.product'])
                        ->where('user_id', $user->id)
                        ->get();

                    if ($transactions->isEmpty()) {
                        $this->warn("No transactions found for user {$user->id}. Skipping.");
                        continue;
                    }

                    // 4. Panggil API AI untuk generate portfolio
                    $response = Http::timeout(300)
                        ->withToken($AI_API_TOKEN)
                        ->post($AI_URL . '/insights/generate', [
                            'data' => $transactions,
                        ]);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        $aiData = $responseData['data'] ?? [];
                        $summary = $aiData['summary'] ?? [];

                        // Simpan hasil ke tabel AiRun
                        $aiRun = AiRun::create([
                            'user_id' => $user->id,
                            'type_ai' => 'PORTFOLIO',
                            'status' => 'COMPLETED',
                            'generated_at' => now(),
                        ]);

                        // Simpan rincian portfolio insight
                        AiPortfolioInsight::create([
                            'ai_run_id' => $aiRun->id,
                            'user_id' => $user->id,
                            'insight' => $aiData['insight'] ?? null,
                            'tanggal_laporan' => $summary['tanggal_laporan'] ?? null,
                            'periode' => $summary['periode'] ?? null,
                            'total_omset_minggu_ini' => $summary['total_omset_minggu_ini'] ?? 0,
                            'total_transaksi' => $summary['total_transaksi'] ?? 0,
                            'rata_rata_transaksi_per_hari' => $summary['rata_rata_transaksi_per_hari'] ?? 0,
                            'rata_rata_omset_per_hari' => $summary['rata_rata_omset_per_hari'] ?? 0,
                            'bintang_warung' => $summary['bintang_warung'] ?? null,
                            'hari_ramai_tanggal' => $summary['hari_paling_ramai']['tanggal'] ?? null,
                            'hari_ramai_omset' => $summary['hari_paling_ramai']['omset'] ?? null,
                            'produk_kurang_laku' => $summary['produk_kurang_laku'] ?? null,
                            'source' => $aiData['source'] ?? null,
                            'generated_at' => $aiData['generated_at'] ?? now(),
                            'valid_until' => $aiData['valid_until'] ?? null,
                        ]);

                        $this->info("Successfully generated portfolio for user {$user->id}");
                    } else {
                        AiRun::create([
                            'user_id' => $user->id,
                            'type_ai' => 'PORTFOLIO',
                            'status' => 'FAILED',
                            'generated_at' => now(),
                            'error_message' => $response->body(),
                        ]);
                        $this->error("API error for user {$user->id}: " . $response->body());
                    }

                } catch (\Exception $e) {
                    Log::error("Cron portfolio generation error for user {$user->id}: " . $e->getMessage());
                    AiRun::create([
                        'user_id' => $user->id,
                        'type_ai' => 'PORTFOLIO',
                        'status' => 'FAILED',
                        'generated_at' => now(),
                        'error_message' => $e->getMessage(),
                    ]);
                    $this->error("Exception for user {$user->id}: " . $e->getMessage());
                }
            } else {
                $this->line("Skipping portfolio for user {$user->id}, last run was recent (less than 7 days).");
            }
        }

        $this->info('AI portfolio generation run finished.');
    }
}
