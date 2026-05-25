# AI Stock Recommendation API

Dokumentasi untuk endpoint yang terkait dengan rekomendasi stok (AI Stock).

## 1. Mendapatkan Rekomendasi Stok Terbaru

Endpoint ini mengambil hasil analisis rekomendasi stok terakhir yang berhasil diselesaikan untuk pengguna. Hasilnya diurutkan berdasarkan tingkat urgensi (`risk_point`) tertinggi.

-   **Endpoint:** `GET /api/ai/runs/latest/stocks`
-   **Method:** `GET`
-   **Authentication:** `Bearer Token` (memerlukan login)

### Request

Tidak ada body yang diperlukan untuk request ini.

**Contoh cURL:**

```bash
curl -X GET "http://localhost:8000/api/ai/runs/latest/stocks" \
     -H "Authorization: Bearer <YOUR_AUTH_TOKEN>" \
     -H "Accept: application/json"
```

### Response

#### Response Sukses (200 OK)

```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 1,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2024-05-25T12:00:00.000000Z",
        "seasonal_insight": "Permintaan akan meningkat menjelang libur akhir pekan.",
        "total_products": 5,
        "error_message": null,
        "created_at": "2024-05-25T12:00:00.000000Z",
        "updated_at": "2024-05-25T12:00:00.000000Z",
        "aiRecommendations": [
            {
                "id": 1,
                "ai_run_id": 1,
                "product_id": 101,
                "product_name": "Kopi Susu",
                "product_price": 18000,
                "current_stock": 5,
                "avg_daily_sales": 10,
                "recommed_restok_qty": 70,
                "restock_min": 65,
                "restock_max": 75,
                "restock_label": "65-75",
                "target_days_coverage": 7,
                "risk_level": "Sangat Mendesak",
                "urgency_description": "Stok akan habis dalam kurang dari 1 hari. Segera lakukan restock untuk menghindari kehabisan stok.",
                "days_until_emty": 0.5,
                "estimated_emty_date": "2024-05-25",
                "risk_point": 95,
                "seasonal_min": 80,
                "seasonal_max": 90,
                "seasonal_label": "80-90",
                "seasonal_holiday": "Libur Nasional",
                "seasonal_reason": "Peningkatan permintaan selama liburan.",
                "product": {
                    "id": 101,
                    "name": "Kopi Susu",
                    "price": 18000,
                    "stock": 5,
                    "category_id": 1,
                    "image_url": "http://example.com/images/kopi_susu.jpg"
                },
                "aiRecommendationActions": []
            }
        ]
    }
}
```

#### Response Gagal (404 Not Found)

```json
{
    "success": false,
    "message": "No AI run found for STOCKS",
    "data": null
}
```

---

## 2. Memulai Analisis Rekomendasi Stok Baru

Endpoint ini memulai proses analisis baru untuk menghasilkan rekomendasi stok. Proses ini berjalan di latar belakang.

-   **Endpoint:** `POST /api/ai/runs/analyze`
-   **Method:** `POST`
-   **Authentication:** `Bearer Token`

### Request

Tidak ada body yang diperlukan. Server akan menggunakan data transaksi dari pengguna yang terautentikasi.

**Contoh cURL:**

```bash
curl -X POST "http://localhost:8000/api/ai/runs/analyze" \
     -H "Authorization: Bearer <YOUR_AUTH_TOKEN>" \
     -H "Accept: application/json"
```

### Response

#### Response Sukses (200 OK)

Respons ini menandakan bahwa analisis telah berhasil dimulai dan data rekomendasi baru telah dibuat.

```json
{
    "success": true,
    "message": "AI run started successfully",
    "data": {
        "id": 2,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2024-05-26T10:00:00.000000Z",
        "seasonal_insight": "Permintaan stabil, tidak ada event khusus.",
        "total_products": 5,
        "aiRecommendations": [
            {
                "id": 2,
                "ai_run_id": 2,
                "product_id": 102,
                "product_name": "Teh Manis",
                "current_stock": 20,
                "risk_point": 50,
                "product": {
                    "id": 102,
                    "name": "Teh Manis",
                    "price": 5000,
                    "stock": 20,
                    "category_id": 2,
                    "image_url": "http://example.com/images/teh_manis.jpg"
                }
            }
        ]
    }
}
```

#### Response Gagal (500 Internal Server Error / Timeout)

```json
{
    "success": false,
    "message": "An error occurred during AI analysis: cURL error 28: Operation timed out..."
}
```

---

## 3. Mengubah Status Aksi Rekomendasi

Endpoint ini digunakan untuk menandai sebuah rekomendasi sebagai "selesai" (misalnya, setelah restock dilakukan) atau "diabaikan".

-   **Endpoint:** `PATCH /api/recommendations/{recommendationId}/action`
-   **Method:** `PATCH`
-   **Authentication:** `Bearer Token`

### Request Body

```json
{
    "action": "COMPLETED"
}
```

-   `action` (string, required): Status aksi yang ingin diatur. Nilai yang valid: `COMPLETED`, `DISMISSED`.

**Contoh cURL:**

```bash
curl -X PATCH "http://localhost:8000/api/recommendations/1/action" \
     -H "Authorization: Bearer <YOUR_AUTH_TOKEN>" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{"action": "COMPLETED"}'
```

### Response

#### Response Sukses (200 OK)

```json
{
    "success": true,
    "message": "Action for recommendation 1 updated to COMPLETED.",
    "data": {
        "id": 1,
        "ai_recommendation_id": 1,
        "action": "COMPLETED",
        "user_id": 1,
        "created_at": "2024-05-26T11:00:00.000000Z",
        "updated_at": "2024-05-26T11:00:00.000000Z"
    }
}
```

#### Response Gagal (404 Not Found)

```json
{
    "success": false,
    "message": "Recommendation not found"
}
```
