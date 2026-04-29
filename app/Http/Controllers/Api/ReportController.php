<?php

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
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

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

    private function getReportData(int $userId, string $period): array
    {
        $dateRange = $this->getDateRange($period);
        $previousRange = $this->getPreviousDateRange($period);

        // Total Pendapatan
        $totalRevenue = Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->sum('total_amount');

        $previousRevenue = $previousRange ? Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$previousRange['start'], $previousRange['end']])
            ->sum('total_amount') : 0;

        $revenueChange = $previousRevenue > 0 ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        // Total Transaksi
        $totalTransactions = Transaction::where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->count();

        // Rata-rata Keranjang
        $avgBasket = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Tren Penjualan (sum per hari dalam periode)
        $salesTrend = Transaction::selectRaw('DATE(trx_date) as date, SUM(total_amount) as total')
            ->where('user_id', $userId)
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        // Produk Terlaris
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

        // 5 Transaksi Terakhir
        $recentTransactions = Transaction::with(['items.product'])
            ->where('user_id', $userId)
            ->where('trx_type', 'SALE')
            ->whereBetween('trx_date', [$dateRange['start'], $dateRange['end']])
            ->orderByDesc('trx_date')
            ->limit(5)
            ->get()
            ->toArray();

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
                return ['start' => '1970-01-01', 'end' => $now->toDateString()];
            default:
                return ['start' => $now->toDateString(), 'end' => $now->toDateString()];
        }
    }

    private function getPreviousDateRange(string $period): ?array
    {
        $now = Carbon::now();

        switch ($period) {
            case 'hari_ini':
                $yesterday = $now->copy()->subDay();
                return ['start' => $yesterday->toDateString(), 'end' => $yesterday->toDateString()];
            case 'minggu_ini':
                $lastWeek = $now->copy()->subWeek();
                return ['start' => $lastWeek->startOfWeek()->toDateString(), 'end' => $lastWeek->endOfWeek()->toDateString()];
            case 'bulan_ini':
                $lastMonth = $now->copy()->subMonth();
                return ['start' => $lastMonth->startOfMonth()->toDateString(), 'end' => $lastMonth->endOfMonth()->toDateString()];
            case 'tahun_ini':
                $lastYear = $now->copy()->subYear();
                return ['start' => $lastYear->startOfYear()->toDateString(), 'end' => $lastYear->endOfYear()->toDateString()];
            case 'sepanjang_masa':
                return null; // No previous for all time
            default:
                return null;
        }
    }
}