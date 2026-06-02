<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AiRun;
use App\Models\AiPortfolioInsight;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeneratePortfolioJob implements ShouldQueue
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
        Log::info("Starting queued portfolio generation for user {$user->id} ({$user->name})");

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        try {
            // Retrieve transactions
            $transactions = Transaction::with(['items.product'])
                ->where('user_id', $user->id)
                ->get();

            if ($transactions->isEmpty()) {
                Log::warning("No transactions found for user {$user->id} during queued portfolio generation.");
                return;
            }

            // Call external AI API
            $response = Http::timeout(300)
                ->withToken($AI_API_TOKEN)
                ->post($AI_URL . '/insights/generate', [
                    'data' => $transactions,
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                $aiData = $responseData['data'] ?? [];
                $summary = $aiData['summary'] ?? [];

                // Create AiRun
                $aiRun = AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'PORTFOLIO',
                    'status' => 'COMPLETED',
                    'generated_at' => now(),
                ]);

                // Store portfolio insight
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

                Log::info("Successfully generated queued portfolio for user {$user->id}");
            } else {
                AiRun::create([
                    'user_id' => $user->id,
                    'type_ai' => 'PORTFOLIO',
                    'status' => 'FAILED',
                    'generated_at' => now(),
                    'error_message' => $response->body(),
                ]);
                Log::error("API error during queued portfolio generation for user {$user->id}: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Exception in queued portfolio generation for user {$user->id}: " . $e->getMessage());
            AiRun::create([
                'user_id' => $user->id,
                'type_ai' => 'PORTFOLIO',
                'status' => 'FAILED',
                'generated_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
