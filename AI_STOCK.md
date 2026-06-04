# AI Stock Endpoints

## 1. GET /api/ai/runs/latest/stocks

Mengambil analisis stok AI terbaru.

### Request

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
```

**Body:** Tidak ada

---

### Response Success (200)

```json
{
  "success": true,
  "message": "Latest AI STOCKS run retrieved successfully",
  "data": {
    "id": "{uuid-ai_run}",
    "user_id": "{uuid-user}",
    "type_ai": "STOCKS",
    "status": "COMPLETED",
    "generated_at": "2026-06-04T01:00:00.000000Z",
    "error_message": null,
    "seasonal_insight": {
      "summary": "string | null",
      "detail": "string | null"
    },
    "total_products": 10,
    "created_at": "2026-06-04T01:00:00.000000Z",
    "updated_at": "2026-06-04T01:00:00.000000Z",
    "ai_recommendations": [
      {
        "id": 1,
        "ai_run_id": "{uuid-ai_run}",
        "product_id": "{uuid-product}",
        "product_name": "Nama Produk",
        "current_stock": 50,
        "avg_daily_sales": 12.5,
        "recommed_restok_qty": 60,
        "restock_min": 40,
        "restock_max": 80,
        "restock_label": "Sedang",
        "risk_level": "HIGH",
        "urgency_description": "Stok akan habis dalam 3 hari",
        "days_until_emty": 3,
        "estimated_emty_date": "2026-06-07",
        "risk": "HIGH",
        "description": "Stok akan habis dalam 3 hari",
        "risk_point": 85,
        "seasonal_min": 30,
        "seasonal_max": 100,
        "seasonal_label": "Hari Raya",
        "seasonal_holiday": "Idul Adha",
        "seasonal_reason": "Permintaan meningkat menjelang hari raya",
        "selected_stocks": [
          {
            "id": "{uuid-stock}",
            "product_id": "{uuid-product}",
            "stock_on_hand": 50,
            "created_at": "2026-06-04T01:00:00.000000Z",
            "updated_at": "2026-06-04T01:00:00.000000Z"
          }
        ],
        "selected_seasonal_stocks": [
          {
            "id": "{uuid-stock}",
            "product_id": "{uuid-product}",
            "stock_on_hand": 50,
            "created_at": "2026-06-04T01:00:00.000000Z",
            "updated_at": "2026-06-04T01:00:00.000000Z"
          }
        ],
        "product": {
          "id": "{uuid-product}",
          "user_id": "{uuid-user}",
          "name": "Nama Produk",
          "price": "15000.00",
          "description": "Deskripsi produk",
          "image_url": "https://example.com/image.jpg",
          "category_id": "{uuid-category}",
          "is_active": true,
          "created_at": "2026-01-01T00:00:00.000000Z",
          "updated_at": "2026-06-04T01:00:00.000000Z",
          "stocks": [
            {
              "id": "{uuid-stock}",
              "product_id": "{uuid-product}",
              "stock_on_hand": 50,
              "created_at": "2026-06-04T01:00:00.000000Z",
              "updated_at": "2026-06-04T01:00:00.000000Z"
            }
          ]
        },
        "ai_recommendation_actions": [
          {
            "id": 1,
            "ai_recommendation_id": 1,
            "action_type": "DONE",
            "action_at": "2026-06-04T02:00:00.000000Z",
            "created_at": "2026-06-04T02:00:00.000000Z",
            "updated_at": "2026-06-04T02:00:00.000000Z"
          }
        ]
      }
    ]
  }
}
```

### Response Error (403 - Not PRO)

```json
{
  "success": false,
  "message": "This feature requires an active PRO subscription."
}
```

### Response Error (404 - Not Found)

```json
{
  "success": false,
  "message": "No AI run found for STOCKS",
  "data": null
}
```

---

## 2. PATCH /api/ai/recommendations/{recommendationId}/action

Mengupdate aksi (DONE/IGNORE) pada rekomendasi stok AI.

### Request

**Headers:**
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

**Parameters:**
| Parameter | Tipe | Wajib | Deskripsi |
|-----------|------|-------|-----------|
| recommendationId | integer | Ya | ID rekomendasi dari AI |

**Body (DONE with stock update):**
```json
{
  "action_type": "DONE",
  "stock_quantity": 60
}
```

**Body (IGNORE — no stock update):**
```json
{
  "action_type": "IGNORE"
}
```

| Field | Tipe | Wajib | Validasi |
|-------|------|-------|----------|
| `action_type` | string | Ya | `DONE` atau `IGNORE` |
| `stock_quantity` | integer | Ya (jika `action_type=DONE`) | `>= 0` dan harus dalam rentang min/max rekomendasi |

> **Catatan `stock_quantity`:**  
> Saat `action_type = DONE`, nilai `stock_quantity` otomatis divalidasi:
> - Jika ada `seasonal_recommendation` → range `seasonal_min` – `seasonal_max`
> - Jika tidak ada seasonal → range `restock_min` – `restock_max`
> - Sistem akan langsung **update stok produk** dan membuat **transaksi ADJUSTMENT**

---

### Response Success (200)

```json
{
  "success": true,
  "message": "Action updated successfully",
  "data": {
    "action": {
      "id": 1,
      "ai_recommendation_id": 1,
      "action_type": "DONE",
      "action_at": "2026-06-04T02:00:00.000000Z",
      "created_at": "2026-06-04T02:00:00.000000Z",
      "updated_at": "2026-06-04T02:00:00.000000Z"
    },
    "recommendation": {
      "id": 1,
      "ai_run_id": "{uuid-ai_run}",
      "product_id": "{uuid-product}",
      "product_name": "Nama Produk",
      "current_stock": 50,
      "avg_daily_sales": 12.5,
      "recommed_restok_qty": 60,
      "restock_min": 40,
      "restock_max": 80,
      "restock_label": "Sedang",
      "risk_level": "HIGH",
      "urgency_description": "Stok akan habis dalam 3 hari",
      "days_until_emty": 3,
      "estimated_emty_date": "2026-06-07",
      "risk": "HIGH",
      "description": "Stok akan habis dalam 3 hari",
      "risk_point": 85,
      "seasonal_min": 30,
      "seasonal_max": 100,
      "seasonal_label": "Hari Raya",
      "seasonal_holiday": "Idul Adha",
      "seasonal_reason": "Permintaan meningkat menjelang hari raya",
      "selected_stocks": [],
      "selected_seasonal_stocks": [],
      "product": { "...": "..." },
      "ai_recommendation_actions": [],
      "seasonalRecommendation": null
    }
  }
}
```

### Response Error (403 - Not PRO)

```json
{
  "success": false,
  "message": "This feature requires an active PRO subscription."
}
```

### Response Error (404 - Not Found)

```json
{
  "success": false,
  "message": "AI recommendation not found"
}
```

### Response Error (422 - Validation)

```json
{
  "message": "The action_type field is required. (and 1 more error)",
  "errors": {
    "action_type": [
      "The action_type field is required.",
      "The selected action_type is invalid."
    ]
  }
}
```

### Response Error (422 - Range Validation)

Terjadi saat `stock_quantity` di luar rentang min/max yang ditentukan rekomendasi:

```json
{
  "success": false,
  "message": "stock_quantity must be at least 40"
}
```

Atau:

```json
{
  "success": false,
  "message": "stock_quantity must not exceed 80"
}
```

---

## Catatan

- Semua endpoint di atas membutuhkan **PRO subscription** aktif.
- Kedua endpoint menggunakan middleware `auth:sanctum`.
- Hanya rekomendasi dengan `product` yang ter-relasi yang akan muncul di `latestStocks`.
- Urutan rekomendasi diurutkan berdasarkan `risk_point` descending (paling berisiko di atas).
