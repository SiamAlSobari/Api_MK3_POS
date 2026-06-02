<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AiRun;
use App\Jobs\GeneratePortfolioJob;
use Carbon\Carbon;

class GeneratePortfolios extends Command
{
    protected $signature = 'ai:generate-portfolios';
    protected $description = 'Generate AI weekly portfolio insights for active PRO users via queue';

    public function handle()
    {
        $this->info('Starting AI portfolio generation dispatching for PRO users...');

        // 1. Ambil semua user dengan langganan PRO aktif secara chunk untuk hemat memori
        User::whereHas('subscriptions', function ($query) {
            $query->where('plan_name', 'PRO')
                  ->where('status', 'ACTIVE')
                  ->whereDate('end_date', '>=', \Carbon\Carbon::today());
        })->chunk(100, function ($users) {
            foreach ($users as $user) {
                // 2. Cek kapan analisis PORTFOLIO terakhir untuk user ini
                $lastRun = AiRun::where('user_id', $user->id)
                    ->where('type_ai', 'PORTFOLIO')
                    ->latest('generated_at')
                    ->first();

                // Jika belum pernah ada, atau sudah lebih dari 7 hari, jalankan analisis baru dengan mendispatch job
                if (!$lastRun || Carbon::parse($lastRun->generated_at)->diffInDays(now()) >= 7) {
                    $this->info("Dispatching portfolio generation job for user: {$user->id} - {$user->name}");
                    GeneratePortfolioJob::dispatch($user);
                } else {
                    $this->line("Skipping portfolio for user {$user->id}, last run was recent (less than 7 days).");
                }
            }
        });

        $this->info('AI portfolio generation jobs dispatched successfully.');
    }
}

