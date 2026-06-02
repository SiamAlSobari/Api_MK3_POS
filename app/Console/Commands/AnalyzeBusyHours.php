<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Jobs\AnalyzeBusyHoursJob;

class AnalyzeBusyHours extends Command
{
    protected $signature = 'ai:analyze-busy-hours';
    protected $description = 'Run AI busy hours analysis for all active PRO users via queue';

    public function handle()
    {
        $this->info('Starting AI busy hours analysis dispatching for PRO users...');

        // 1. Ambil semua user dengan langganan PRO aktif secara chunk untuk hemat memori
        User::whereHas('subscriptions', function ($query) {
            $query->where('plan_name', 'PRO')
                  ->where('status', 'ACTIVE')
                  ->whereDate('end_date', '>=', \Carbon\Carbon::today());
        })->chunk(100, function ($users) {
            foreach ($users as $user) {
                $this->info("Dispatching busy hours analysis job for user: {$user->id} - {$user->name}");
                AnalyzeBusyHoursJob::dispatch($user);
            }
        });

        $this->info('AI busy hours analysis jobs dispatched successfully.');
    }
}

