# Database Refactoring for AI Features
Sesuai dengan `AI_API_SPEC.md`, beberapa struktur tabel dan controller pada backend aplikasi Kasir telah disesuaikan agar format output dan penyimpanan database lebih sinkron dengan API berbasis Python.

## 1. Migrasi Database (Tabel Baru dan Pembaruan)
Sebuah migration file telah dibuat `2026_05_19_080000_refactor_ai_tables_for_api_spec.php` yang melakukan perubahan berikut:

### a. `ai_runs`
- Menambahkan nilai `PORTFOLIO` pada kolom enum `type_ai`.
- Menambahkan kolom `seasonal_insight` (JSON) untuk menyimpan insight terkait hari libur terdekat dari LLM.
- Menambahkan kolom `total_products` (integer) untuk rekap cepat.

### b. `ai_recommendations`
- Denormalisasi data produk (untuk menghindari join berulang) dengan menambah: `product_name`, `product_price`.
- Menambahkan kolom historikal: `avg_daily_sales`.
- Mengubah format restock rekomendasi yang awalnya angka statis menjadi *range* dan informasi label dengan menambah: `restock_min`, `restock_max`, `restock_label`, dan `target_days_coverage`.
- Menyimpan teks urgensi berbasis emoji dari LLM dengan menambah `urgency_description`.
- Menyimpan rincian proyeksi harian dengan `stock_timeline` (JSON).

### c. `busy_hour_daily_forecasts`
- Mengubah format estimasi transaksi dan omset (revenue) menjadi *range* agar user lebih mudah membaca probabilitas dengan menambahkan:
  - `est_trx_min`, `est_trx_max`, `est_trx_label`
  - `est_revenue_min`, `est_revenue_max`, `est_revenue_label`
- Menambahkan label khusus waktu padat dengan kolom `peak_hour_label`.

### d. `busy_hour_hourly_predictions`
- Sama seperti daily forecast, menambahkan estimasi transaksi dan omset menjadi berbasis range:
  - `est_trx_min`, `est_trx_max`, `est_trx_label`
  - `est_revenue_min`, `est_revenue_max`, `est_revenue_label`
- Menambahkan `busy_label` (teks seperti "Sepi Santai") dan `what_to_prepare` (teks nasehat untuk persiapan).

### e. Tabel Baru: `ai_portfolio_insights`
Tabel ini khusus untuk menampung response dari endpoint `POST /api/insights/generate` (Weekly Portfolio).
Menyimpan rangkuman bisnis 7 hari terakhir serta insight/nasehat yang digenerate oleh AI. Termasuk data seperti total omset, transaksi, bintang warung (best selling), dan produk kurang laku.

---

## 2. Model Eloquent (Penyesuaian di `app\Models`)
- Pembaruan terhadap atribut `$fillable` dan `$casts` di model `AiRun`, `AiRecommendation`, `BusyHourDailyForecast`, dan `BusyHourHourlyPrediction` sesuai field-field di atas.
- Pembuatan model baru: `AiPortfolioInsight.php` untuk mapping tabel `ai_portfolio_insights`.
- Relasi Eloquent dari `AiRun` `hasOne` `AiPortfolioInsight`.

---

## 3. Controller (Penyesuaian di `app\Http\Controllers\Api\AiRunController.php`)
- **Restock AI (`analyze`):** Logika disesuaikan untuk mengambil insight terkait `seasonal_insight` dari response Python AI, serta meng-ekstrak field range data (`restock_min`, `restock_max`, `restock_label`, dsb) dan memasukannya ke model.
- **Busy Hours AI (`analyzeBusyHours`):** Dirombak agar memetakan output array JSON berbasis-range (`estimated_transactions.min`, dsb) dari Python AI ke dalam kolom baru (seperti `est_trx_min`, `est_trx_max`).
- **Endpoint Baru Portfolio:** 
  - Ditambahkan method `generatePortfolio` untuk mengirim data ke `POST /insights/generate` di Python AI, kemudian menyimpannya ke `AiPortfolioInsight`.
  - Ditambahkan method `latestPortfolio` untuk memberikan data insight portfolio (evaluasi penjualan mingguan) kepada Mobile Frontend.

---

## 4. API Routes (Penyesuaian di `routes\api.php`)
- Ditambahkan rute baru untuk Portfolio API di dalam auth sanctum:
  - `GET /ai/runs/latest/portfolio` 
  - `POST /ai/runs/generate-portfolio`
