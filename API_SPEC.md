# 📊 DOKUMENTASI API REKOMENDASI STOK AI

Dokumentasi ini menjelaskan secara rinci endpoint API untuk analisis stok berbasis kecerdasan buatan (AI), pengambilan data hasil analisis rekomendasi terbaru, serta pencatatan tindakan (action) terhadap rekomendasi yang diberikan.

---

## 🔒 Otentikasi & Keamanan

Semua endpoint di bawah ini memerlukan otentikasi menggunakan **Laravel Sanctum (Bearer Token)** dan hanya dapat diakses oleh pengguna dengan status langganan **PRO** yang aktif.

- **Header Wajib**:
  ```http
  Authorization: Bearer <your_sanctum_token>
  Accept: application/json
  ```

> [!IMPORTANT]
> **Pengecekan Akses PRO (Subscription Check):**
> Jika pengguna tidak memiliki paket langganan `PRO` yang aktif (`ACTIVE`) dengan masa berlaku yang belum berakhir, sistem akan secara otomatis memblokir akses dan mengembalikan respons error berikut:
> - **HTTP Status Code**: `403 Forbidden`
> - **Response Body**:
>   ```json
>   {
>     "success": false,
>     "message": "This feature requires an active PRO subscription."
>   }
>   ```

---

## 🚀 Ringkasan Endpoint

| No | Method | Endpoint | Deskripsi |
| :--- | :--- | :--- | :--- |
| 1 | `POST` | `/api/ai/runs/analyze` | Memicu mesin AI untuk menganalisis data transaksi dan menghasilkan prediksi stok terbaru. |
| 2 | `GET` | `/api/ai/runs/latest/stocks` | Mengambil data hasil analisis stok & rekomendasi AI yang paling mutakhir. |
| 3 | `PATCH` | `/api/ai/recommendations/{recommendationId}/action` | Menyimpan/memperbarui tindakan (`DONE` atau `IGNORE`) pada item rekomendasi tertentu. |

---

## 📂 Detail Spesifikasi API

### 1. Memicu Analisis AI (`POST /api/ai/runs/analyze`)
Mengirimkan data transaksi pengguna secara dinamis ke mesin AI eksternal untuk memprediksi tingkat stok barang dan menghasilkan saran restock selama 14 hari ke depan.

#### 📤 Request
- **Headers**:
  ```http
  Authorization: Bearer <sanctum_token>
  Accept: application/json
  ```
- **Body Parameter**: Tidak ada (Sistem mengambil data transaksi secara otomatis dari database internal berdasarkan akun pengguna yang sedang terotentikasi).

#### 📥 Response

##### ✅ Respons Sukses (`200 OK`)
Mengembalikan rekaman `AiRun` yang sukses dibuat lengkap dengan detail instansiasi `ai_recommendations`.

```json
{
  "success": true,
  "message": "AI run started successfully",
  "data": {
    "id": 12,
    "user_id": 3,
    "type_ai": "STOCKS",
    "status": "COMPLETED",
    "generated_at": "2026-05-21T16:22:29.000000Z",
    "error_message": null,
    "seasonal_insight": {
      "insight": "Penjualan meningkat sebesar 25% pada akhir pekan untuk kategori minuman segar.",
      "trends": ["Weekend spike", "Warm weather preference"]
    },
    "total_products": 1,
    "created_at": "2026-05-21T16:22:29.000000Z",
    "updated_at": "2026-05-21T16:22:29.000000Z",
    "deleted_at": null,
    "ai_recommendations": [
      {
        "id": 45,
        "ai_run_id": 12,
        "product_id": 8,
        "product_name": "Kopi Susu Gula Aren",
        "product_price": "18000.00",
        "current_stock": 5,
        "avg_daily_sales": "12.50",
        "recommed_restok_qty": 50,
        "restock_min": 20,
        "restock_max": 50,
        "restock_label": "High Restock Needed",
        "target_days_coverage": 14,
        "risk_level": "HIGH",
        "urgency_description": "Stok kritis akan habis dalam kurun waktu 1 hari.",
        "days_until_emty": 1,
        "estimated_emty_date": "2026-05-22",
        "risk": "Kehilangan potensi omset harian akibat kehabisan stok.",
        "description": "Stok kritis akan habis dalam kurun waktu 1 hari.",
        "risk_point": 95,
        "stock_timeline": [
          {"date": "2026-05-21", "projected_stock": 5},
          {"date": "2026-05-22", "projected_stock": 0}
        ],
        "created_at": "2026-05-21T16:22:29.000000Z",
        "updated_at": "2026-05-21T16:22:29.000000Z",
        "deleted_at": null
      }
    ]
  }
}
```

##### ❌ Respons Gagal - Kendala Server AI (`400/500/etc`)
Jika integrasi pihak ketiga mengalami kendala teknis, status rekaman diubah menjadi `FAILED` dan mengembalikan respons kegagalan.

```json
{
  "success": false,
  "message": "Failed to fetch AI recommendations"
}
```

---

### 2. Mendapatkan Rekomendasi Stok Terbaru (`GET /api/ai/runs/latest/stocks`)
Mengakses hasil analisis stok AI terakhir yang berhasil diproses untuk pengguna terotentikasi.

#### 📤 Request
- **Headers**:
  ```http
  Authorization: Bearer <sanctum_token>
  Accept: application/json
  ```

#### 📥 Response

##### ✅ Respons Sukses (`200 OK`)
Mengembalikan dokumen data rekomendasi stok terbaru lengkap dengan relasi data produk (`product`) serta riwayat aksi rekomendasi (`ai_recommendation_actions`).

```json
{
  "success": true,
  "message": "Latest AI STOCKS run retrieved successfully",
  "data": {
    "id": 12,
    "user_id": 3,
    "type_ai": "STOCKS",
    "status": "COMPLETED",
    "generated_at": "2026-05-21T16:22:29.000000Z",
    "error_message": null,
    "seasonal_insight": {
      "insight": "Penjualan meningkat sebesar 25% pada akhir pekan untuk kategori minuman segar.",
      "trends": ["Weekend spike", "Warm weather preference"]
    },
    "total_products": 1,
    "created_at": "2026-05-21T16:22:29.000000Z",
    "updated_at": "2026-05-21T16:22:29.000000Z",
    "deleted_at": null,
    "ai_recommendations": [
      {
        "id": 45,
        "ai_run_id": 12,
        "product_id": 8,
        "product_name": "Kopi Susu Gula Aren",
        "product_price": "18000.00",
        "current_stock": 5,
        "avg_daily_sales": "12.50",
        "recommed_restok_qty": 50,
        "restock_min": 20,
        "restock_max": 50,
        "restock_label": "High Restock Needed",
        "target_days_coverage": 14,
        "risk_level": "HIGH",
        "urgency_description": "Stok kritis akan habis dalam kurun waktu 1 hari.",
        "days_until_emty": 1,
        "estimated_emty_date": "2026-05-22",
        "risk": "Kehilangan potensi omset harian akibat kehabisan stok.",
        "description": "Stok kritis akan habis dalam kurun waktu 1 hari.",
        "risk_point": 95,
        "stock_timeline": [
          {"date": "2026-05-21", "projected_stock": 5},
          {"date": "2026-05-22", "projected_stock": 0}
        ],
        "created_at": "2026-05-21T16:22:29.000000Z",
        "updated_at": "2026-05-21T16:22:29.000000Z",
        "deleted_at": null,
        "product": {
          "id": 8,
          "category_id": 2,
          "name": "Kopi Susu Gula Aren",
          "sku": "KOP-SUS-AREN",
          "price": "18000.00",
          "cost": "10000.00",
          "status": "ACTIVE",
          "created_at": "2026-05-20T10:00:00.000000Z",
          "updated_at": "2026-05-20T10:00:00.000000Z"
        },
        "ai_recommendation_actions": [
          {
            "id": 1,
            "ai_recommendation_id": 45,
            "action_type": "DONE",
            "action_at": "2026-05-21T17:00:00.000000Z",
            "created_at": "2026-05-21T17:00:00.000000Z",
            "updated_at": "2026-05-21T17:00:00.000000Z"
          }
        ]
      }
    ]
  }
}
```

##### ❌ Respons Gagal - Data Belum Tersedia (`404 Not Found`)
Terjadi apabila pengguna belum pernah memicu proses analisis AI sebelumnya.

```json
{
  "success": false,
  "message": "No AI run found for STOCKS",
  "data": null
}
```

---

### 3. Memperbarui Tindakan Rekomendasi (`PATCH /api/ai/recommendations/{recommendationId}/action`)
Menyimpan atau memperbarui pilihan tindakan yang diambil pengguna atas rekomendasi item tertentu (misalnya, menyatakan tindakan selesai dilakukan atau diabaikan).

#### 📤 Request
- **Headers**:
  ```http
  Authorization: Bearer <sanctum_token>
  Accept: application/json
  Content-Type: application/json
  ```
- **Path Parameter**:
  - `recommendationId` (integer, required): ID unik dari data rekomendasi (`ai_recommendations.id`).

- **Request Body**:
  | Field | Tipe | Validasi | Deskripsi |
  | :--- | :--- | :--- | :--- |
  | `action_type` | `string` | `required`, `in:DONE,IGNORE` | Nilai status aksi tindakan pengguna. |

  *Contoh JSON Body:*
  ```json
  {
    "action_type": "DONE"
  }
  ```

#### 📥 Response

##### ✅ Respons Sukses (`200 OK`)
Mengembalikan entitas objek tindakan rekomendasi (`AiRecommendationAction`) yang berhasil tersimpan di database.

```json
{
  "success": true,
  "message": "Action updated successfully",
  "data": {
    "id": 2,
    "ai_recommendation_id": 45,
    "action_type": "DONE",
    "action_at": "2026-05-21T16:22:29.000000Z",
    "updated_at": "2026-05-21T16:22:29.000000Z",
    "created_at": "2026-05-21T16:22:29.000000Z"
  }
}
```

##### ❌ Respons Gagal - Validasi Data Salah (`422 Unprocessable Content`)
Terjadi bila parameter `action_type` yang dilemparkan kosong atau tidak sesuai ketentuan opsi `DONE`/`IGNORE`.

```json
{
  "message": "The action type field is required.",
  "errors": {
    "action_type": [
      "The action type field is required."
    ]
  }
}
```

##### ❌ Respons Gagal - Data Rekomendasi Tidak Ditemukan (`404 Not Found`)
Terjadi apabila ID parameter rekomendasi di URL tidak terdaftar di database.

```json
{
  "success": false,
  "message": "AI recommendation not found"
}
```
