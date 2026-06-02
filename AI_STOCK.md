# AI STOCKS API

## GET `/api/ai/runs/latest/stocks`

Mengambil AI run terbaru untuk STOCKS milik user yang login.

---

### Headers

| Header | Value | Required |
|--------|-------|----------|
| `Authorization` | `Bearer {sanctum_token}` | Yes |
| `Accept` | `application/json` | Yes |

---

### Response: Success (200)

```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 3,
        "user_id": 2,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2026-06-02T11:38:01.000000Z",
        "error_message": null,
        "seasonal_insight": {
            "has_upcoming_holiday": false,
            "upcoming_holidays": [],
            "seasonal_advice": "Tidak ada hari raya besar dalam 14 hari ke depan. Gunakan rekomendasi restock normal dari AI.",
            "source": "system"
        },
        "total_products": 4,
        "created_at": "2026-06-02T11:38:01.000000Z",
        "updated_at": "2026-06-02T11:38:01.000000Z",
        "deleted_at": null,
        "ai_recommendations": [
            {
                "id": 51,
                "ai_run_id": 3,
                "product_id": 53,
                "current_stock": 1,
                "recommed_restok_qty": 32,
                "risk_level": "CRITICAL",
                "days_until_emty": 1,
                "estimated_emty_date": "2026-06-02",
                "risk": "CRITICAL",
                "description": "⚠️ DARURAT! Stok diperkirakan habis dalam 1 hari (sekitar tanggal 2026-06-02). Disarankan segera restock.",
                "risk_point": 3,
                "product_name": "Gas melon 3kg",
                "restock_min": 22,
                "restock_max": 32,
                "restock_label": "Saran restock: 22 - 32 item untuk persediaan 7 hari ke depan.",
                "urgency_description": "⚠️ DARURAT! Stok diperkirakan habis dalam 1 hari (sekitar tanggal 2026-06-02). Disarankan segera restock.",
                "product": {
                    "id": 53,
                    "name": "Gas melon 3kg",
                    "price": "20000.00",
                    "description": "Gas melon 3kg adalah produk contoh.",
                    "image_url": "https://transisienergi.id/wp-content/uploads/2025/02/20250202_GAS-3-KG-LANGKA.jpg",
                    "category_id": 10,
                    "is_active": true,
                    "user_id": 2,
                    "created_at": "2026-06-02T11:35:39.000000Z",
                    "updated_at": "2026-06-02T11:35:39.000000Z",
                    "deleted_at": null
                },
                "ai_recommendation_actions": [],
                "seasonal_recommendation": null
            },
            {
                "id": 52,
                "ai_run_id": 3,
                "product_id": 52,
                "current_stock": 10,
                "recommed_restok_qty": 5,
                "risk_level": "MEDIUM",
                "days_until_emty": 5,
                "estimated_emty_date": "2026-06-06",
                "risk": "MEDIUM",
                "description": "⚡ PERHATIAN! Stok diestimasi akan habis dalam 5 hari (sekitar tanggal 2026-06-06). Pertimbangkan untuk restock.",
                "risk_point": 2,
                "product_name": "Rinso",
                "restock_min": 3,
                "restock_max": 5,
                "restock_label": "Saran restock: 3 - 5 item untuk persediaan 7 hari ke depan.",
                "urgency_description": "⚡ PERHATIAN! Stok diestimasi akan habis dalam 5 hari (sekitar tanggal 2026-06-06). Pertimbangkan untuk restock.",
                "product": {
                    "id": 52,
                    "name": "Rinso",
                    "price": "5500.00",
                    "description": null,
                    "image_url": null,
                    "category_id": 5,
                    "is_active": true,
                    "user_id": 2,
                    "created_at": "2026-06-02T11:35:39.000000Z",
                    "updated_at": "2026-06-02T11:35:39.000000Z",
                    "deleted_at": null
                },
                "ai_recommendation_actions": [],
                "seasonal_recommendation": null
            }
        ]
    }
}
```

---

### Response: Success dengan Seasonal Recommendation (200)

```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 5,
        "user_id": 2,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2026-06-10T08:00:00.000000Z",
        "error_message": null,
        "seasonal_insight": {
            "has_upcoming_holiday": true,
            "upcoming_holidays": [
                {
                    "name": "Idul Adha",
                    "date": "2026-06-17",
                    "days_away": 7,
                    "impact": "Meningkatnya permintaan daging dan bahan masakan"
                }
            ],
            "seasonal_advice": "Idul Adha tinggal 7 hari lagi. Disarankan menambah stok daging, bumbu masak, dan minyak goreng.",
            "source": "system"
        },
        "total_products": 8,
        "created_at": "2026-06-10T08:00:00.000000Z",
        "updated_at": "2026-06-10T08:00:00.000000Z",
        "deleted_at": null,
        "ai_recommendations": [
            {
                "id": 60,
                "ai_run_id": 5,
                "product_id": 60,
                "current_stock": 5,
                "recommed_restok_qty": 40,
                "risk_level": "HIGH",
                "days_until_emty": 3,
                "estimated_emty_date": "2026-06-13",
                "risk": "HIGH",
                "description": "⚠️ Stok menipis! Segera restok. Ditambah permintaan musiman karena Idul Adha.",
                "risk_point": 80,
                "product_name": "Minyak Goreng",
                "restock_min": 30,
                "restock_max": 50,
                "restock_label": "Saran restock: 30 - 50 item untuk persediaan 7 hari ke depan.",
                "urgency_description": "⚠️ Stok menipis! Segera restok. Ditambah permintaan musiman karena Idul Adha.",
                "product": {
                    "id": 60,
                    "name": "Minyak Goreng",
                    "price": "14000.00",
                    "description": "Minyak goreng kemasan",
                    "image_url": null,
                    "category_id": 3,
                    "is_active": true,
                    "user_id": 2,
                    "created_at": "2026-06-10T07:00:00.000000Z",
                    "updated_at": "2026-06-10T07:00:00.000000Z",
                    "deleted_at": null
                },
                "ai_recommendation_actions": [
                    {
                        "id": 2,
                        "ai_recommendation_id": 60,
                        "action_type": "DONE",
                        "action_at": "2026-06-10T09:00:00.000000Z",
                        "created_at": "2026-06-10T09:00:00.000000Z",
                        "updated_at": "2026-06-10T09:00:00.000000Z",
                        "deleted_at": null
                    }
                ],
                "seasonal_recommendation": {
                    "id": 1,
                    "ai_recommendation_id": 60,
                    "min": 50,
                    "max": 70,
                    "label": "Tambah stok 50-70 pcs untuk Idul Adha",
                    "holiday": "Idul Adha",
                    "reason": "Idul Adha meningkatkan permintaan minyak goreng untuk memasak daging kurban",
                    "created_at": "2026-06-10T08:00:00.000000Z",
                    "updated_at": "2026-06-10T08:00:00.000000Z",
                    "deleted_at": null
                }
            }
        ]
    }
}
```

---

### Response: Error 401 - Unauthenticated

```json
{
    "message": "Unauthenticated."
}
```

### Response: Error 403 - Bukan PRO

```json
{
    "success": false,
    "message": "This feature requires an active PRO subscription."
}
```

### Response: Error 404 - Belum Ada AI Run

```json
{
    "success": false,
    "message": "No AI run found for STOCKS",
    "data": null
}
```

---

### Diagram Relasi

```
AiRun (1)
  ├── type_ai: "STOCKS"
  ├── seasonal_insight: object
  ├── total_products: int
  │
  └── ai_recommendations (*)
        ├── product (1) ─── Product
        ├── ai_recommendation_actions (*)
        └── seasonal_recommendation (0..1)
```

### Field Descriptions

#### `data` (AiRun)

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| user_id | int | Foreign key ke users |
| type_ai | string | `"STOCKS"`, `"BUSY"`, atau `"PORTFOLIO"` |
| status | string | `"PROCESSING"`, `"COMPLETED"`, `"FAILED"` |
| generated_at | datetime | Waktu AI run di-generate |
| error_message | string\|null | Pesan error jika gagal |
| seasonal_insight | object\|null | Insight musiman dari AI (`has_upcoming_holiday`, `upcoming_holidays[]`, `seasonal_advice`, `source`) |
| total_products | int\|null | Total produk yang dianalisis |
| created_at | datetime | Timestamp created |
| updated_at | datetime | Timestamp updated |
| deleted_at | datetime\|null | Soft delete timestamp |
| ai_recommendations | array | Daftar rekomendasi produk (lihat di bawah) |

#### `ai_recommendations[]`

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| ai_run_id | int | Foreign key ke ai_runs |
| product_id | int | Foreign key ke products |
| current_stock | int | Stok saat ini |
| recommed_restok_qty | int | Jumlah restok yang direkomendasikan |
| risk_level | string | `"LOW"`, `"MEDIUM"`, `"HIGH"`, `"CRITICAL"` |
| days_until_emty | int\|null | Perkiraan hari hingga stok habis |
| estimated_emty_date | date\|null | Perkiraan tanggal stok habis |
| risk | string\|null | Label risiko |
| description | string\|null | Deskripsi risiko (bisa mengandung emoji) |
| risk_point | int | Poin risiko (semakin tinggi semakin urgent) |
| product_name | string\|null | Nama produk (denormalized) |
| restock_min | int\|null | Minimum rekomendasi restock |
| restock_max | int\|null | Maksimum rekomendasi restock |
| restock_label | string\|null | Label restock dalam bahasa natural |
| urgency_description | string\|null | Deskripsi urgensi (bisa mengandung emoji) |
| product | object\|null | Data produk (relasi belongsTo) |
| ai_recommendation_actions | array | Riwayat aksi yang sudah dilakukan (DONE/IGNORE) |
| seasonal_recommendation | object\|null | Rekomendasi musiman (jika ada hari libur) |

#### `product` (Product)

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| name | string | Nama produk |
| price | decimal | Harga produk |
| description | string\|null | Deskripsi produk |
| image_url | string\|null | URL gambar produk |
| category_id | int\|null | Foreign key ke categories |
| is_active | bool | Status aktif produk |
| user_id | int | Foreign key ke users |
| created_at | datetime | Timestamp created |
| updated_at | datetime | Timestamp updated |
| deleted_at | datetime\|null | Soft delete timestamp |

#### `ai_recommendation_actions[]`

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| ai_recommendation_id | int | Foreign key ke ai_recommendations |
| action_type | string\|null | `"DONE"` atau `"IGNORE"` |
| action_at | datetime\|null | Waktu aksi dilakukan |
| created_at | datetime | Timestamp created |
| updated_at | datetime | Timestamp updated |
| deleted_at | datetime\|null | Soft delete timestamp |

#### `seasonal_recommendation`

| Field | Type | Description |
|-------|------|-------------|
| id | int | Primary key |
| ai_recommendation_id | int | Foreign key ke ai_recommendations |
| min | int\|null | Stok minimum rekomendasi musiman |
| max | int\|null | Stok maksimum rekomendasi musiman |
| label | string\|null | Label rekomendasi |
| holiday | string\|null | Nama hari libur / event |
| reason | text\|null | Alasan mengapa stok perlu ditambah |
| created_at | datetime | Timestamp created |
| updated_at | datetime | Timestamp updated |
| deleted_at | datetime\|null | Soft delete timestamp |
