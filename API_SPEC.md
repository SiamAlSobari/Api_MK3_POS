# API Specification

Berikut adalah Spesifikasi API untuk endpoints `/ai` dan `/reports`.
Semua endpoint di bawah ini **membutuhkan autentikasi** berupa Bearer Token (`auth:sanctum`). Untuk endpoint `/ai`, pengguna juga diwajibkan memiliki subscription (langganan) **PRO** yang masih aktif.

---

## 1. Dapatkan Hasil AI Restock Terakhir
Mengambil riwayat data analisis *restock* barang terakhir (terbaru) dari AI untuk user yang sedang *login*.

- **URL**: `/api/ai/runs/latest/stocks`
- **Method**: `GET`
- **Middleware**: `auth:sanctum`
- **Syarat**: Langganan `PRO`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response: 200 OK (Berhasil)**
```json
{
  "success": true,
  "message": "Latest AI STOCKS run retrieved successfully",
  "data": {
    "id": 1,
    "user_id": 10,
    "type_ai": "STOCKS",
    "status": "COMPLETED",
    "generated_at": "2023-11-20T10:00:00.000000Z",
    "ai_recommendations": [
      {
        "id": 1,
        "ai_run_id": 1,
        "product_id": 5,
        "current_stock": 10,
        "recommed_restok_qty": 50,
        "risk_level": "CRITICAL",
        "days_until_emty": 2,
        "estimated_emty_date": "2023-11-22",
        "risk": "HIGH",
        "description": "Stock will empty in 2 days",
        "risk_point": 90,
        "product": {
          "id": 5,
          "name": "Produk A",
          "price": 10000
        },
        "ai_recommendation_actions": null
      }
    ]
  }
}
```

**Response: 403 Forbidden (Bukan Pro)**
```json
{
  "success": false,
  "message": "This feature requires an active PRO subscription."
}
```

---

## 2. Dapatkan Hasil AI Jam Sibuk Terakhir
Mengambil hasil analisis prediksi jam sibuk dan prediksi *revenue* terakhir dari AI.

- **URL**: `/api/ai/runs/latest/busy-hours`
- **Method**: `GET`
- **Middleware**: `auth:sanctum`
- **Syarat**: Langganan `PRO`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Response: 200 OK (Berhasil)**
```json
{
  "success": true,
  "message": "Latest AI BUSY hours run retrieved successfully",
  "data": {
    "id": 2,
    "user_id": 10,
    "type_ai": "BUSY",
    "status": "COMPLETED",
    "generated_at": "2023-11-20T10:30:00.000000Z",
    "busy_hour_daily_forecasts": [
      {
        "id": 1,
        "ai_run_id": 2,
        "forecast_date": "2023-11-21",
        "day_name": "Tuesday",
        "day_of_week": 1,
        "is_weekend": false,
        "total_predicted_trx": 150.5,
        "total_predicted_revenue": 500000,
        "peak_hour": "18:00",
        "peak_hour_trx": 25.5,
        "busy_hours_count": 3,
        "hourly_predictions": [
            {
               "id": 1,
               "hour": "18:00",
               "predicted_transactions": 25.5,
               "predicted_revenue": 100000,
               "busy_level": "PEAK",
               "emoji": "🔥",
               "product_predictions": [
                  {
                     "product_id": 5,
                     "product_name": "Produk A",
                     "probability": 0.85,
                     "estimated_qty": 5,
                     "estimated_revenue": 50000,
                     "product": {
                        "id": 5,
                        "name": "Produk A"
                     }
                  }
               ]
            }
        ]
      }
    ]
  }
}
```

---

## 3. Mulai Analisis AI Restock Baru
Memicu server AI eksternal untuk melakukan kalkulasi prediksi stok barang untuk 14 hari ke depan berdasarkan data transaksi *user*.

- **URL**: `/api/ai/runs/analyze`
- **Method**: `POST`
- **Middleware**: `auth:sanctum`
- **Syarat**: Langganan `PRO`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Accept": "application/json"
}
```

**Body Request:**
*(Kosong. Data akan diambil secara internal berdasarkan transaksi user)*

**Response: 200 OK (Berhasil)**
```json
{
  "success": true,
  "message": "AI run started successfully",
  "data": {
    "id": 3,
    "user_id": 10,
    "type_ai": "STOCKS",
    "status": "COMPLETED",
    "generated_at": "2023-11-20T11:00:00.000000Z",
    "ai_recommendations": [
        // List of restock recommendations
    ]
  }
}
```

**Response: 500 Internal Server Error (Gagal Koneksi AI)**
```json
{
  "success": false,
  "message": "An error occurred during AI analysis: Connection timeout"
}
```

---

## 4. Mulai Analisis AI Jam Sibuk Baru
Memicu server AI eksternal untuk melakukan kalkulasi prediksi jam sibuk dan omset pendapatan per jam selama 14 hari ke depan.

- **URL**: `/api/ai/runs/analyze-busy-hours`
- **Method**: `POST`
- **Middleware**: `auth:sanctum`
- **Syarat**: Langganan `PRO`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Accept": "application/json"
}
```

**Response: 200 OK (Berhasil)**
```json
{
  "success": true,
  "message": "Busy hour AI run completed successfully",
  "data": {
    "ai_run": {
        "id": 4,
        "user_id": 10,
        "type_ai": "BUSY",
        "status": "COMPLETED",
        "generated_at": "2023-11-20T11:30:00.000000Z",
        "busy_hour_daily_forecasts": []
    },
    "summary": {
        "accuracy_percent": 92.5,
        "training_samples": 1500,
        "data_range": { "from": "2023-10-01", "to": "2023-11-20" },
        "busiest_day": "2023-11-25 (Saturday)",
        "quietest_day": "2023-11-21 (Tuesday)",
        "avg_daily_transactions": 100.5,
        "avg_daily_revenue": 1000000,
        "total_peak_hours": 15,
        "top_peak_hours": []
    }
  }
}
```

---

## 5. Update Status Aksi Rekomendasi (Action)
Memperbarui status tindakan pada suatu rekomendasi AI (misalnya, menandai rekomendasi restock sebagai "Sudah Dilakukan / DONE" atau "Diabaikan / IGNORE").

- **URL**: `/api/ai/recommendations/{recommendationId}/action`
- **Method**: `PATCH`
- **Middleware**: `auth:sanctum`
- **Syarat**: Langganan `PRO`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}",
  "Content-Type": "application/json",
  "Accept": "application/json"
}
```

**Body Request:**
```json
{
  "action_type": "DONE" 
}
```
*(Keterangan: `action_type` hanya boleh diisi `"DONE"` atau `"IGNORE"`)*

**Response: 200 OK (Berhasil)**
```json
{
  "success": true,
  "message": "Action updated successfully",
  "data": {
    "id": 1,
    "ai_recommendation_id": 12,
    "action_type": "DONE",
    "action_at": "2023-11-20T12:00:00.000000Z"
  }
}
```

---

## 6. Dapatkan Ringkasan Report Penjualan
Mengambil semua ringkasan data report penjualan untuk periode `hari_ini`, `minggu_ini`, `bulan_ini`, `tahun_ini`, dan `sepanjang_masa`.

- **URL**: `/api/reports`
- **Method**: `GET`
- **Middleware**: `auth:sanctum`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Query Parameters:**
- Tidak ada parameter wajib khusus.

**Response: 200 OK (Berhasil)**
```json
{
  "message": "Report data retrieved successfully.",
  "data": {
    "hari_ini": {
      "total_pendapatan": 150000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 120000,
        "persentase_perubahan": 25.0
      },
      "total_transaksi": 8,
      "rata_rata_keranjang": 18750,
      "tren_penjualan": {
        "2026-05-12": 150000
      },
      "produk_terlaris": [
        {
          "name": "Produk A",
          "total_quantity": 10
        },
        {
          "name": "Produk B",
          "total_quantity": 5
        }
      ],
      "transaksi_terakhir": [
        {
          "id": 101,
          "user_id": 10,
          "trx_type": "SALE",
          "total_amount": 50000,
          "trx_date": "2026-05-12 14:00:00",
          "items": [
            {
              "id": 1,
              "transaction_id": 101,
              "product_id": 5,
              "quantity": 2,
              "price": 25000,
              "product": {
                "id": 5,
                "name": "Produk A"
              }
            }
          ]
        }
      ]
    },
    "minggu_ini": {
      "total_pendapatan": 750000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 680000,
        "persentase_perubahan": 10.29
      },
      "total_transaksi": 35,
      "rata_rata_keranjang": 21428.57,
      "tren_penjualan": {
        "2026-05-10": 180000,
        "2026-05-11": 220000,
        "2026-05-12": 350000
      },
      "produk_terlaris": [
        {
          "name": "Produk A",
          "total_quantity": 42
        }
      ],
      "transaksi_terakhir": []
    },
    "bulan_ini": {
      "total_pendapatan": 2200000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 1900000,
        "persentase_perubahan": 15.79
      },
      "total_transaksi": 95,
      "rata_rata_keranjang": 23157.89,
      "tren_penjualan": {
        "2026-05-01": 125000,
        "2026-05-02": 145000
      },
      "produk_terlaris": [],
      "transaksi_terakhir": []
    },
    "tahun_ini": {
      "total_pendapatan": 9800000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 9200000,
        "persentase_perubahan": 6.52
      },
      "total_transaksi": 420,
      "rata_rata_keranjang": 23333.33,
      "tren_penjualan": {
        "2026-04-30": 180000,
        "2026-05-12": 150000
      },
      "produk_terlaris": [],
      "transaksi_terakhir": []
    },
    "sepanjang_masa": {
      "total_pendapatan": 35000000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 0,
        "persentase_perubahan": 0.0
      },
      "total_transaksi": 1450,
      "rata_rata_keranjang": 24137.93,
      "tren_penjualan": {
        "2025-12-01": 125000
      },
      "produk_terlaris": [],
      "transaksi_terakhir": []
    }
  }
}
```

---

## 7. Riwayat Transaksi Penjualan (Sale History)
Mendapatkan semua riwayat transaksi tipe penjualan (`SALE`) milik *user* dengan dukungan filter periode dan pagination.

- **URL**: `/api/reports/sales-history`
- **Method**: `GET`
- **Middleware**: `auth:sanctum`

**Request Headers:**
```json
{
  "Authorization": "Bearer {token}"
}
```

**Query Parameters (Opsional):**
- `period`: Filter periode waktu transaksi:
  - `semua` (default) - Semua transaksi tanpa filter periode
  - `hari_ini` atau `today` - Hanya transaksi hari ini
- `search`: Cari berdasarkan:
  - Nama produk dalam transaksi
  - ID transaksi
  - Total amount (nilai transaksi)
- `per_page`: Jumlah data per halaman (default: `10`, max: `100`)
- `page`: Nomor halaman (default: `1`)

**Contoh Request:**
- `/api/reports/sales-history` - Semua transaksi, 10 per halaman
- `/api/reports/sales-history?period=hari_ini` - Transaksi hari ini
- `/api/reports/sales-history?search=Produk` - Cari produk dengan nama "Produk"
- `/api/reports/sales-history?period=semua&per_page=20&search=A&page=1` - Semua transaksi dengan pencarian, 20 per halaman

**Response: 200 OK (Berhasil)**
```json
{
  "message": "Riwayat transaksi penjualan (SALE) berhasil diambil",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 100,
        "user_id": 10,
        "trx_type": "SALE",
        "total_amount": 50000,
        "trx_date": "2026-05-12 14:30:00",
        "created_at": "2026-05-12T14:30:00.000000Z",
        "updated_at": "2026-05-12T14:30:00.000000Z",
        "deleted_at": null,
        "items": [
          {
            "id": 1,
            "transaction_id": 100,
            "product_id": 5,
            "quantity": 2,
            "price": 25000,
            "product": {
              "id": 5,
              "name": "Produk A"
            }
          }
        ],
        "user": {
          "id": 10,
          "name": "User Kasir"
        }
      }
    ],
    "first_page_url": "http://localhost/api/reports/sales-history?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost/api/reports/sales-history?page=5",
    "next_page_url": "http://localhost/api/reports/sales-history?page=2",
    "path": "http://localhost/api/reports/sales-history",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 50
  }
}
