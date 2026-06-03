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
use OpenApi\Attributes as OA;

class ReportController extends Controller
{
    #[OA\Get(
        path: "/reports",
        summary: "Get sales reports for all periods",
        tags: ["Reports"],
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Report data retrieved", content: new OA\JsonContent(ref: "#/components/schemas/ReportResponse")),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $hariIni = $this->getReportData($userId, "hari_ini");
        $mingguIni = $this->getReportData($userId, "minggu_ini");
        $bulanIni = $this->getReportData($userId, "bulan_ini");
        $tahunIni = $this->getReportData($userId, "tahun_ini");
        $sepanjangMasa = $this->getReportData($userId, "sepanjang_masa");

        $data = [
            "hari_ini" => $hariIni,
            "minggu_ini" => $mingguIni,
            "bulan_ini" => $bulanIni,
            "tahun_ini" => $tahunIni,
            "sepanjang_masa" => $sepanjangMasa,

            // Tambahan khusus (Helper) untuk mempermudah render grafik di Mobile App (Kotlin)
            // Format dipisah menjadi sumbu X (labels) dan sumbu Y (values)
            "grafik_data" => [
                "minggu_ini" => [
                    "labels" => array_column(
                        $mingguIni["tren_penjualan"],
                        "date",
                    ),
                    "values" => array_column(
                        $mingguIni["tren_penjualan"],
                        "total",
                    ),
                ],
                "bulan_ini" => [
                    "labels" => array_column(
                        $bulanIni["tren_penjualan"],
                        "date",
                    ),
                    "values" => array_column(
                        $bulanIni["tren_penjualan"],
                        "total",
                    ),
                ],
                "tahun_ini" => [
                    "labels" => array_column(
                        $tahunIni["tren_penjualan"],
                        "date",
                    ),
                    "values" => array_column(
                        $tahunIni["tren_penjualan"],
                        "total",
                    ),
                ],
            ],
        ];

        return response()->json([
            "message" => "Report data retrieved successfully.",
            "data" => $data,
        ]);
    }

    private function getReportData(int $userId, string $period): array
    {
        $dateRange = $this->getDateRange($period);
        $previousRange = $this->getPreviousDateRange($period);

        $aggregates = Transaction::selectRaw("
                COALESCE(SUM(CASE WHEN trx_date >= ? AND trx_date <= ? THEN total_amount END), 0) as total_revenue,
                COALESCE(COUNT(CASE WHEN trx_date >= ? AND trx_date <= ? THEN 1 END), 0) as total_transactions,
                COALESCE(SUM(CASE WHEN trx_date >= ? AND trx_date <= ? THEN total_amount END), 0) as previous_revenue
            ", [
                $dateRange["start"], $dateRange["end"],
                $dateRange["start"], $dateRange["end"],
                $previousRange["start"] ?? "1970-01-01", $previousRange["end"] ?? "1970-01-01",
            ])
            ->where("user_id", $userId)
            ->where("trx_type", "SALE")
            ->first();

        $totalRevenue = (float) $aggregates->total_revenue;
        $totalTransactions = (int) $aggregates->total_transactions;
        $previousRevenue = (float) $aggregates->previous_revenue;

        $revenueChange =
            $previousRevenue > 0
                ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100
                : 0;

        // Rata-rata Keranjang
        $avgBasket =
            $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Tren Penjualan (sum per hari dalam periode)
        $salesTrend = Transaction::selectRaw(
            "trx_date as date, SUM(total_amount) as total",
        )
            ->where("user_id", $userId)
            ->where("trx_type", "SALE")
            ->whereBetween("trx_date", [$dateRange["start"], $dateRange["end"]])
            ->groupBy("date")
            ->orderBy("date")
            ->get()
            ->map(function ($item) {
                return [
                    "date" => $item->date,
                    "total" => (float) $item->total,
                ];
            })
            ->values()
            ->toArray();

        // Produk Terlaris
        $topProducts = TransactionItem::selectRaw(
            "products.name, SUM(transaction_items.quantity) as total_quantity",
        )
            ->join(
                "transactions",
                "transaction_items.transaction_id",
                "=",
                "transactions.id",
            )
            ->join(
                "products",
                "transaction_items.product_id",
                "=",
                "products.id",
            )
            ->where("transactions.user_id", $userId)
            ->where("transactions.trx_type", "SALE")
            ->whereBetween("transactions.trx_date", [
                $dateRange["start"],
                $dateRange["end"],
            ])
            ->groupBy("products.id", "products.name")
            ->orderByDesc("total_quantity")
            ->limit(5)
            ->get()
            ->toArray();

        // 5 Transaksi Terakhir
        $recentTransactions = Transaction::with(["items.product"])
            ->where("user_id", $userId)
            ->where("trx_type", "SALE")
            ->whereBetween("trx_date", [$dateRange["start"], $dateRange["end"]])
            ->orderByDesc("trx_date")
            ->limit(5)
            ->get()
            ->toArray();

        return [
            "total_pendapatan" => $totalRevenue,
            "pendapatan_vs_sebelumnya" => [
                "nilai_sebelumnya" => $previousRevenue,
                "persentase_perubahan" => round($revenueChange, 2),
            ],
            "total_transaksi" => $totalTransactions,
            "rata_rata_keranjang" => round($avgBasket, 2),
            "tren_penjualan" => $salesTrend,
            "produk_terlaris" => $topProducts,
            "transaksi_terakhir" => $recentTransactions,
        ];
    }

    private function getDateRange(string $period): array
    {
        $now = Carbon::now();

        switch ($period) {
            case "hari_ini":
                return [
                    "start" => $now->toDateString(),
                    "end" => $now->toDateString(),
                ];
            case "minggu_ini":
                return [
                    "start" => $now->startOfWeek()->toDateString(),
                    "end" => $now->endOfWeek()->toDateString(),
                ];
            case "bulan_ini":
                return [
                    "start" => $now->startOfMonth()->toDateString(),
                    "end" => $now->endOfMonth()->toDateString(),
                ];
            case "tahun_ini":
                return [
                    "start" => $now->startOfYear()->toDateString(),
                    "end" => $now->endOfYear()->toDateString(),
                ];
            case "sepanjang_masa":
                return ["start" => "1970-01-01", "end" => $now->toDateString()];
            default:
                return [
                    "start" => $now->toDateString(),
                    "end" => $now->toDateString(),
                ];
        }
    }

    private function getPreviousDateRange(string $period): ?array
    {
        $now = Carbon::now();

        switch ($period) {
            case "hari_ini":
                $yesterday = $now->copy()->subDay();
                return [
                    "start" => $yesterday->toDateString(),
                    "end" => $yesterday->toDateString(),
                ];
            case "minggu_ini":
                $lastWeek = $now->copy()->subWeek();
                return [
                    "start" => $lastWeek->startOfWeek()->toDateString(),
                    "end" => $lastWeek->endOfWeek()->toDateString(),
                ];
            case "bulan_ini":
                $lastMonth = $now->copy()->subMonth();
                return [
                    "start" => $lastMonth->startOfMonth()->toDateString(),
                    "end" => $lastMonth->endOfMonth()->toDateString(),
                ];
            case "tahun_ini":
                $lastYear = $now->copy()->subYear();
                return [
                    "start" => $lastYear->startOfYear()->toDateString(),
                    "end" => $lastYear->endOfYear()->toDateString(),
                ];
            case "sepanjang_masa":
                return null; // No previous for all time
            default:
                return null;
        }
    }
    #[OA\Get(
        path: "/reports/sales-history",
        summary: "Get paginated sales history",
        tags: ["Reports"],
        security: [["bearerAuth" => []]],
        parameters: [
            new OA\Parameter(name: "period", in: "query", required: false, schema: new OA\Schema(type: "string", default: "semua"), example: "hari_ini"),
            new OA\Parameter(name: "per_page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 10), example: 10),
            new OA\Parameter(name: "search", in: "query", required: false, schema: new OA\Schema(type: "string"), example: "mie"),
        ],
        responses: [
            new OA\Response(response: 200, description: "Sales history retrieved", content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "message", type: "string"),
                    new OA\Property(property: "data", type: "object"),
                ],
                type: "object"
            )),
        ]
    )]
    public function salesHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $period = $request->query("period", "semua");
        $perPage = (int) $request->query("per_page", 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;
        $search = $request->query("search", "");

        $salesQuery = Transaction::with(["items.product", "user"])
            ->where("user_id", $userId)
            ->whereIn("trx_type", ["SALE", "PURCHASE"]);

        if (in_array($period, ["hari_ini", "today"], true)) {
            $salesQuery->whereDate("trx_date", Carbon::today());
        }

        if (!empty($search)) {
            $salesQuery->where(function ($query) use ($search) {
                $query
                    ->whereHas("items.product", function ($q) use ($search) {
                        $q->where("name", "ILIKE", "%" . $search . "%");
                    })
                    ->orWhere("id", "LIKE", "%" . $search . "%")
                    ->when(is_numeric($search), fn($q) => $q->orWhere("total_amount", $search));
            });
        }

        $sales = $salesQuery
            ->latest("trx_date") // Urutkan dari yang terbaru
            ->paginate($perPage); // Menampilkan data per halaman

        return response()->json([
            "message" => "Riwayat transaksi penjualan (SALE) berhasil diambil",
            "data" => $sales,
        ]);
    }
}
