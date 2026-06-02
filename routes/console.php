<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Analisis Stok: Setiap hari jam 1 pagi (01:00)
Schedule::command('ai:analyze-stocks')->dailyAt('01:00');

// 2. Analisis Jam Sibuk: Setiap hari jam 4 sore (16:00)
Schedule::command('ai:analyze-busy-hours')->dailyAt('16:00');

// 3. Rangkuman Portofolio Mingguan: Setiap hari (logika internal akan menyaring agar berjalan 7 hari sekali)
Schedule::command('ai:generate-portfolios')->daily();
