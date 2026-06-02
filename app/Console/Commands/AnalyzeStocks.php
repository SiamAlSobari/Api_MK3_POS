<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Jobs\AnalyzeStocksJob;

class AnalyzeStocks extends Command
{
    protected $signature = 'ai:analyze-stocks';
    protected $description = 'Run AI stock analysis for all active PRO users via queue';

    public function handle()
    {
        $this->info('Starting AI stock analysis dispatching for PRO users...');

        // 1. Ambil semua user yang memiliki langganan PRO aktif secara chunk untuk hemat memori
        User::whereHas('subscriptions', function ($query) {
            $query->where('plan_name', 'PRO')
                  ->where('status', 'ACTIVE')
                  ->whereDate('end_date', '>=', \Carbon\Carbon::today());
        })->chunk(100, function ($users) {
            foreach ($users as $user) {
                $this->info("Dispatching stock analysis job for user: {$user->id} - {$user->name}");
                AnalyzeStocksJob::dispatch($user);
            }
        });

        $this->info('AI stock analysis jobs dispatched successfully.');
    }
}

