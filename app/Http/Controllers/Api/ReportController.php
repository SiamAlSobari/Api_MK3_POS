<?php

// =============================================================================
// FILE: ReportController.php
// DESKRIPSI: Controller untuk menghasilkan LAPORAN penjualan.
//            Menyediakan ringkasan data penjualan berdasarkan berbagai periode
//            waktu (hari ini, minggu ini, bulan ini, tahun ini, sepanjang masa).
//
// KONSEP PENTING:
// - Setiap periode menampilkan: total pendapatan, jumlah transaksi,
//   rata-rata keranjang, tren penjualan, produk terlaris, dan transaksi terakhir.
// - Data periode sebelumnya juga diambil untuk menghitung persentase perubahan
//   (misal: pendapatan naik 10% dibanding kemarin).
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // =========================================================================
    // METHOD: index
    // URL: GET /api/reports
    // FUNGSI: Mengambil ringkasan laporan penjualan untuk SEMUA periode waktu
    //         sekaligus dalam satu request.
    //
    // RETURN: Objek JSON berisi 5 periode laporan:
    // - hari_ini: laporan hari ini
    // - minggu_ini: laporan minggu ini (Senin - Minggu)
    // - bulan_ini: laporan bulan ini (tanggal 1 - akhir bulan)
    // - tahun_ini: laporan tahun ini (Januari - Desember)
    // - sepanjang_masa: laporan dari awal sampai sekarang
    // =========================================================================
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Ambil laporan untuk setiap periode menggunakan helper method getReportData()
        $data = [
            'hari_ini' => $this->getReportData($userId, 'hari_ini'),
            'minggu_ini' => $this->getReportData($userId, 'minggu_ini'),
            'bulan_ini' => $this->getReportData($userId, 'bulan_ini'),
            'tahun_ini' => $this->getReportData($userId, 'tahun_ini'),
            'sepanjang_masa' => $this->getReportData($userId, 'sepanjang_masa'),
        ];

        return response()->json([
            'message' => 'Report data retrieved successfully.',
            'data' => $data,
        ]);
    }

    // =========================================================================
    // METHOD: getReportData (Private Helper)
    // FUNGSI: Mengambil semua metrik laporan untuk satu periode tertentu.
    //
    // PARAMETER:
    // - $userId: ID user yang sedang login
    // - $period: String periode ('hari_ini', 'minggu_ini', dll)
    //
    // RETURN: Array berisi 7 metrik:
    // 1. total_pendapatan: Total semua transaksi dalam periode
    // 2. pendapatan_vs_sebelumnya: Perbandingan dengan periode sebelumnya
    //    (misal: hari ini vs kemarin, minggu ini vs minggu lalu)
    // 3. total_transaksi: Jumlah transaksi dalam periode
    // 4. rata_rata_keranjang: Rata-rata nilai per transaksi
    //    (total pendapatan / jumlah transaksi)
    // 5. tren_penjualan: Penjualan per hari dalam periode (untuk grafik)
    // 6. produk_terlaris: 5 produk dengan penjualan terbanyak
    // 7. transaksi_terakhir: 5 transaksi penjualan terbaru
    // =========================================================================
    private function getReportData(int $userId, string $period): array
    {
        // Dapatkan rentang tanggal untuk periode saat ini dan sebelumnya
        $dateRange = $this->getDateRange($period);
        $previousRange = $this->getPreviousDateRange($period);

        // =====================================================================
        // 1. TOTAL PENDAPATAN (Revenue)
        // Query: SUM(total_amount) dari semua transaksi dalam periode
        // sum() = fungsi aggregate SQL yang menjumlahkan semua nilai di kolom
        // =====================================================================
        $totalRevenue = Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->sum('total_amount');

        // Hitung pendapatan periode sebelumnya untuk perbandingan
        $previousRevenue = $previousRange ? Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$previousRange['start'], $previousRange['end']])
            ->sum('total_amount') : 0;

        // Hitung persentase perubahan pendapatan dibanding periode sebelumnya
        // Rumus: ((saat ini - sebelumnya) / sebelumnya) * 100
        // Contoh: (150000 - 100000) / 100000 * 100 = 50% (naik 50%)
        $revenueChange = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        // =====================================================================
        // 2. TOTAL TRANSAKSI
        // count() = hitung jumlah record yang cocok
        // =====================================================================
        $totalTransactions = Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->count();

        // =====================================================================
        // 3. RATA-RATA KERANJANG (Average Basket)
        // = Total pendapatan / Jumlah transaksi
        // Contoh: Rp 500.000 / 10 transaksi = Rp 50.000 rata-rata per transaksi
        // =====================================================================
        $avgBasket = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // =====================================================================
        // 4. TREN PENJUALAN PER HARI (untuk grafik di frontend)
        // selectRaw() = menulis query SQL mentah untuk SELECT
        // groupBy('date') = kelompokkan berdasarkan tanggal
        // pluck('total', 'date') = hasilkan array key-value {tanggal: total}
        // Contoh: {"2026-05-01": 150000, "2026-05-02": 200000, ...}
        // =====================================================================
        $salesTrend = Transaction::selectRaw('DATE(trx_date) as date, SUM(total_amount) as total')
            ->where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        // =====================================================================
        // 5. PRODUK TERLARIS (Top 5)
        // Join 3 tabel: transaction_items + transactions + products
        // SUM(quantity) = total barang terjual per produk
        // limit(5) = ambil 5 teratas saja
        //
        // SQL yang dihasilkan kurang lebih:
        // SELECT products.name, SUM(transaction_items.quantity) as total_quantity
        // FROM transaction_items
        // JOIN transactions ON transaction_items.transaction_id = transactions.id
        // JOIN products ON transaction_items.product_id = products.id
        // WHERE transactions.user_id = ? AND trx_date BETWEEN ? AND ?
        // GROUP BY products.id, products.name
        // ORDER BY total_quantity DESC
        // LIMIT 5
        // =====================================================================
        $topProducts = TransactionItem::selectRaw('products.name, SUM(transaction_items.quantity) as total_quantity')
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.user_id', $userId)
            ->whereBetween('transactions.trx_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->toArray();

        // =====================================================================
        // 6. 5 TRANSAKSI PENJUALAN TERAKHIR
        // Hanya mengambil transaksi bertipe SALE (penjualan)
        // orderByDesc('trx_date') = urutkan dari yang terbaru
        // with(['items.product']) = sertakan detail item dan produknya
        // =====================================================================
        $recentTransactions = Transaction::with(['items.product'])
            ->where('user_id', $userId)
            ->where('trx_type', 'SALE')
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->orderByDesc('trx_date')
            ->limit(5)
            ->get()
            ->toArray();

        // Kembalikan semua metrik dalam satu array
        return [
            'total_pendapatan' => $totalRevenue,
            'pendapatan_vs_sebelumnya' => [
                'nilai_sebelumnya' => $previousRevenue,
                'persentase_perubahan' => round($revenueChange, 2),
            ],
            'total_transaksi' => $totalTransactions,
            'rata_rata_keranjang' => round($avgBasket, 2),
            'tren_penjualan' => $salesTrend,
            'produk_terlaris' => $topProducts,
            'transaksi_terakhir' => $recentTransactions,
        ];
    }

    // =========================================================================
    // METHOD: getDateRange (Private Helper)
    // FUNGSI: Menghitung rentang tanggal (start & end) untuk periode tertentu.
    //
    // CONTOH OUTPUT:
    // - 'hari_ini' -> ['start' => '2026-05-13', 'end' => '2026-05-13']
    // - 'minggu_ini' -> ['start' => '2026-05-11', 'end' => '2026-05-17']
    // - 'bulan_ini' -> ['start' => '2026-05-01', 'end' => '2026-05-31']
    // - 'sepanjang_masa' -> ['start' => '1970-01-01', 'end' => '2026-05-13']
    //
    // Carbon = Library PHP populer untuk manipulasi tanggal/waktu
    // =========================================================================
    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'hari_ini':
                return ['start' => $now->toDateString(), 'end' => $now->toDateString()];
            case 'minggu_ini':
                return ['start' => $now->startOfWeek()->toDateString(), 'end' => $now->endOfWeek()->toDateString()];
            case 'bulan_ini':
                return ['start' => $now->startOfMonth()->toDateString(), 'end' => $now->endOfMonth()->toDateString()];
            case 'tahun_ini':
                return ['start' => $now->startOfYear()->toDateString(), 'end' => $now->endOfYear()->toDateString()];
            case 'sepanjang_masa':
                // Dari 1 Januari 1970 (epoch) sampai hari ini
                return ['start' => '1970-01-01', 'end' => $now->toDateString()];
            default:
                return ['start' => $now->toDateString(), 'end' => $now->toDateString()];
        }
    }

    // =========================================================================
    // METHOD: getPreviousDateRange (Private Helper)
    // FUNGSI: Menghitung rentang tanggal PERIODE SEBELUMNYA untuk perbandingan.
    //
    // CONTOH:
    // - Jika period = 'hari_ini' -> return kemarin
    // - Jika period = 'minggu_ini' -> return minggu lalu
    // - Jika period = 'bulan_ini' -> return bulan lalu
    // - Jika period = 'sepanjang_masa' -> return null (tidak ada pembanding)
    //
    // CATATAN: copy() penting agar objek Carbon asli tidak berubah
    //          (Carbon bersifat mutable, jadi tanpa copy() akan mengubah $now)
    // =========================================================================
    private function getPreviousDateRange(string $period): ?array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'hari_ini':
                // Periode sebelumnya = kemarin
                $yesterday = $now->copy()->subDay();
                return ['start' => $yesterday->toDateString(), 'end' => $yesterday->toDateString()];
            case 'minggu_ini':
                // Periode sebelumnya = minggu lalu
                $lastWeek = $now->copy()->subWeek();
                return ['start' => $lastWeek->startOfWeek()->toDateString(), 'end' => $lastWeek->endOfWeek()->toDateString()];
            case 'bulan_ini':
                // Periode sebelumnya = bulan lalu
                $lastMonth = $now->copy()->subMonth();
                return ['start' => $lastMonth->startOfMonth()->toDateString(), 'end' => $lastMonth->endOfMonth()->toDateString()];
            case 'tahun_ini':
                // Periode sebelumnya = tahun lalu
                $lastYear = $now->copy()->subYear();
                return ['start' => $lastYear->startOfYear()->toDateString(), 'end' => $lastYear->endOfYear()->toDateString()];
            case 'sepanjang_masa':
                return null; // No previous for all time
            default:
                return null;
        }
    }

    // =========================================================================
    // METHOD: salesHistory
    // URL: GET /api/reports/sales-history
    // FUNGSI: Mengambil riwayat transaksi penjualan (SALE) dengan PAGINATION.
    //
    // QUERY PARAMETERS (dari URL):
    // - period: Filter periode ('hari_ini'/'today' atau 'semua')
    // - per_page: Jumlah data per halaman (default 10, max 100)
    //
    // CONTOH URL: /api/reports/sales-history?period=hari_ini&per_page=15
    //
    // PAGINATION: Daripada mengirim semua data sekaligus (bisa ribuan),
    //             data dipecah jadi halaman-halaman (misal 10 per halaman).
    //             paginate() otomatis menambahkan info halaman di response:
    //             current_page, last_page, total, next_page_url, prev_page_url
    // =========================================================================
    // Fungsi tambahan untuk mendapatkan riwayat transaksi penjualan (SALE) dengan pagination
    public function salesHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Ambil parameter query dari URL, default 'semua' dan 10 per halaman
        $period = $request->query('period', 'semua');
        $perPage = (int) $request->query('per_page', 10);
        // Batasi per_page minimal 1 dan maksimal 100 untuk keamanan performa
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        // Query dasar: ambil transaksi SALE milik user + relasi items dan user
        $salesQuery = Transaction::with(['items.product', 'user'])
            ->where('user_id', $userId)
            ->where('trx_type', 'SALE');

        // Filter berdasarkan periode jika diminta
        if (in_array($period, ['hari_ini', 'today'], true)) {
            // Hanya ambil transaksi hari ini
            $salesQuery->whereDate('trx_date', Carbon::today());
        }

        // Eksekusi query dengan pagination
        $sales = $salesQuery
            ->latest('trx_date') // Urutkan dari yang terbaru
            ->paginate($perPage); // Menampilkan data per halaman

        return response()->json([
            'message' => 'Riwayat transaksi penjualan (SALE) berhasil diambil',
            'data' => $sales,
        ]);
    }
}