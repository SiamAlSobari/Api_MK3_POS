# Panduan Pengaturan Cron Job untuk Analisis AI

Dokumen ini menjelaskan cara membuat dan menjadwalkan tugas otomatis (cron job) untuk menjalankan analisis AI (Busy Hour, Stock, dan Portfolio) untuk semua pengguna PRO.

## Langkah 1: Buat Artisan Command

Kita akan membuat tiga file *command* terpisah di dalam direktori `app/Console/Commands`. Setiap command akan menangani satu jenis analisis AI. Buka terminal Anda dan jalankan perintah berikut:

```bash
php artisan make:command AnalyzeBusyHours
php artisan make:command AnalyzeStocks
php artisan make:command GeneratePortfolios
```

Perintah ini akan membuat tiga file baru:
- `app/Console/Commands/AnalyzeBusyHours.php`
- `app/Console/Commands/AnalyzeStocks.php`
- `app/Console/Commands/GeneratePortfolios.php`

## Langkah 2: Implementasi Logika pada Setiap Command

Sekarang, kita akan mengisi logika untuk setiap command yang telah dibuat. Logikanya akan mirip dengan yang ada di `AiRunController`, tetapi disesuaikan untuk berjalan di server bagi semua pengguna PRO.

### 1. Command untuk Analisis Stok (`AnalyzeStocks.php`)

Buka file `app/Console/Commands/AnalyzeStocks.php` dan modifikasi seperti di bawah ini. Command ini akan mencari semua pengguna PRO dan memicu analisis stok untuk mereka.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeStocks extends Command
{
    protected $signature = 'ai:analyze-stocks';
    protected $description = 'Run AI stock analysis for all PRO users';

    public function handle()
    {
        $this->info('Starting AI stock analysis for PRO users...');

        // 1. Cari semua user dengan langganan PRO yang aktif
        $proUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active'); // Asumsi status langganan aktif
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
                $transactions = \App\Models\Transaction::with(['items.product.stocks'])
                    ->where('user_id', $user->id)
                    ->get();

                if ($transactions->isEmpty()) {
                    $this->warn("No transactions found for user {$user->id}. Skipping.");
                    continue;
                }

                // 3. Panggil API AI (sama seperti di AiRunController@analyze)
                Http::timeout(300)->withToken($AI_API_TOKEN)->post(
                    $AI_URL . '/predict/restock/summary?include_seasonal=true',
                    [
                        'data' => $transactions,
                        'forecast_days' => 14,
                        'user_id' => $user->id, // Tambahkan user_id untuk logging di sisi AI
                    ]
                );

                $this->info("Successfully triggered stock analysis for user: {$user->id}");

            } catch (\Exception $e) {
                Log::error("Failed to process stock analysis for user {$user->id}: " . $e->getMessage());
                $this->error("Failed for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info('AI stock analysis run finished.');
    }
}
```

### 2. Command untuk Analisis Jam Sibuk (`AnalyzeBusyHours.php`)

Buka `app/Console/Commands/AnalyzeBusyHours.php`. Logikanya hampir sama, hanya URL endpoint AI yang berbeda.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyzeBusyHours extends Command
{
    protected $signature = 'ai:analyze-busy-hours';
    protected $description = 'Run AI busy hours analysis for all PRO users';

    public function handle()
    {
        $this->info('Starting AI busy hours analysis for PRO users...');
        $proUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active');
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
                $transactions = \App\Models\Transaction::with(['items.product.stocks'])
                    ->where('user_id', $user->id)
                    ->get();

                if ($transactions->isEmpty()) {
                    $this->warn("No transactions found for user {$user->id}. Skipping.");
                    continue;
                }

                // Panggil endpoint /predict/busy-hours
                Http::timeout(300)->withToken($AI_API_TOKEN)->post(
                    $AI_URL . '/predict/busy-hours',
                    [
                        'data' => $transactions,
                        'forecast_days' => 14,
                        'user_id' => $user->id,
                    ]
                );

                $this->info("Successfully triggered busy hours analysis for user: {$user->id}");

            } catch (\Exception $e) {
                Log::error("Failed to process busy hours analysis for user {$user->id}: " . $e->getMessage());
                $this->error("Failed for user {$user->id}: " . $e->getMessage());
            }
        }
        $this->info('AI busy hours analysis run finished.');
    }
}
```

### 3. Command untuk Generate Portfolio (`GeneratePortfolios.php`)

Buka `app/Console/Commands/GeneratePortfolios.php`. Command ini memiliki logika tambahan untuk memeriksa kapan terakhir kali portfolio dibuat.

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\AiRun;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GeneratePortfolios extends Command
{
    protected $signature = 'ai:generate-portfolios';
    protected $description = 'Generate AI portfolio for PRO users if it is older than 7 days';

    public function handle()
    {
        $this->info('Starting AI portfolio generation for PRO users...');
        $proUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('status', 'active');
        })->get();

        if ($proUsers->isEmpty()) {
            $this->info('No active PRO users found.');
            return;
        }

        $AI_URL = env('AI_URL');
        $AI_API_TOKEN = env('AI_API_TOKEN');

        foreach ($proUsers as $user) {
            // Cek kapan AiRun terakhir untuk PORTFOLIO
            $lastRun = AiRun::where('user_id', $user->id)
                ->where('type_ai', 'PORTFOLIO')
                ->latest('generated_at')
                ->first();

            // Jika belum pernah ada, atau sudah lebih dari 7 hari, jalankan.
            if (!$lastRun || Carbon::parse($lastRun->generated_at)->diffInDays(now()) >= 7) {
                $this->info("Processing portfolio for user: {$user->id} - {$user->name}");
                try {
                    $transactions = \App\Models\Transaction::with(['items.product'])
                        ->where('user_id', $user->id)
                        ->get();

                    if ($transactions->isEmpty()) {
                        $this->warn("No transactions found for user {$user->id}. Skipping.");
                        continue;
                    }

                    // Panggil endpoint /insights/generate
                    Http::timeout(300)->withToken($AI_API_TOKEN)->post(
                        $AI_URL . '/insights/generate',
                        ['data' => $transactions, 'user_id' => $user->id]
                    );

                    $this->info("Successfully triggered portfolio generation for user: {$user->id}");

                } catch (\Exception $e) {
                    Log::error("Failed to process portfolio for user {$user->id}: " . $e->getMessage());
                    $this->error("Failed for user {$user->id}: " . $e->getMessage());
                }
            } else {
                $this->line("Skipping portfolio for user {$user->id}, last run was recent.");
            }
        }
        $this->info('AI portfolio generation run finished.');
    }
}
```

## Langkah 3: Jadwalkan Command

Setelah command dibuat, kita perlu menjadwalkannya agar berjalan secara otomatis. Buka file `app/Console/Kernel.php` dan tambahkan jadwal di dalam method `schedule`.

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        // Pastikan command Anda terdaftar di sini atau di-discover secara otomatis
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // 1. Analisis Stok: Setiap hari jam 1 pagi.
        $schedule->command('ai:analyze-stocks')->dailyAt('01:00');

        // 2. Analisis Jam Sibuk: Setiap hari jam 4 sore (16:00).
        $schedule->command('ai:analyze-busy-hours')->dailyAt('16:00');

        // 3. Generate Portfolio: Setiap hari (logika internal akan cek > 7 hari).
        $schedule->command('ai:generate-portfolios')->daily();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
```

## Langkah 4: Konfigurasi Cron di Server

Langkah terakhir adalah menambahkan satu entri cron di server Anda (misalnya, menggunakan `crontab -e` di Linux). Entri ini akan memanggil scheduler Laravel setiap menit. Scheduler Laravel kemudian akan menentukan command mana yang harus dijalankan sesuai jadwalnya.

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

- Ganti `/path-to-your-project` dengan path absolut ke direktori root proyek Laravel Anda (misalnya, `/var/www/pos` atau `C:/laragon/www/pos`).
- Perintah ini akan berjalan setiap menit untuk memeriksa apakah ada scheduler yang harus didispatch.

## Langkah 5: Jalankan Queue Worker (Sangat Penting)

Karena proses analisis AI di atas sekarang dijalankan secara **asinkron (asynchronous)** menggunakan Queue Jobs untuk mencegah timeout dan memori habis (OOM), Anda **wajib** menjalankan worker queue agar tugas-tugas tersebut diproses:

### Di Lingkungan Development (Lokal)
Jalan perintah berikut di terminal Anda:
```bash
php artisan queue:work --tries=1
```
*(Catatan: Jika Anda menggunakan perintah `npm run dev`, queue worker `php artisan queue:listen` sudah berjalan otomatis).*

### Di Lingkungan Production (Server Linux)
Sangat direkomendasikan untuk menggunakan **Supervisor** guna menjaga proses queue worker tetap berjalan terus-menerus di background. Contoh konfigurasi file Supervisor (`/etc/supervisor/conf.d/laravel-worker.conf`):

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pos/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pos/storage/logs/worker.log
stopwaitsecs=3600
```

Dengan konfigurasi ini:
1. Cron job hanya bertugas memasukkan (dispatch) tugas analisis ke dalam antrean database dalam hitungan milidetik.
2. Queue worker akan memproses satu per satu tugas analisis AI di background secara asinkron tanpa mengganggu stabilitas scheduler utama.

