# AI Stock API Documentation

## Base URL

```
/api/ai
```

> **Auth**: Semua endpoint memerlukan `Bearer Token` via Laravel Sanctum.
> **Subscription**: Memerlukan subscription PRO aktif.

---

## 1. Latest Stocks

Mengambil data AI run terbaru untuk analisis stok (STOCKS), termasuk rekomendasi restock per produk, seasonal restock, dan aksi user.

### Request

```
GET /api/ai/runs/latest/stocks
```

**Headers:**

| Header          | Value              |
| --------------- | ------------------ |
| `Authorization` | `Bearer {token}`   |
| `Accept`        | `application/json` |

**Body:** Tidak ada

---

### Response

#### ✅ 200 OK — Berhasil

```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 1,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2026-05-26T06:30:00.000000Z",
        "error_message": null,
        "seasonal_insight": {
            "has_upcoming_holiday": true,
            "upcoming_holidays": [
                {
                    "date": "2026-05-27",
                    "name": "Eid al-Adha (estimated)",
                    "days_away": 1,
                    "impact": "HIGH"
                },
                {
                    "date": "2026-05-31",
                    "name": "Vesak Day (estimated)",
                    "days_away": 5,
                    "impact": "HIGH"
                }
            ],
            "seasonal_advice": "Wah, ternyata Idul Adha sudah dekat nih! ...",
            "source": "gemini-lite"
        },
        "total_products": 6,
        "created_at": "2026-05-26T06:30:00.000000Z",
        "updated_at": "2026-05-26T06:30:00.000000Z",
        "deleted_at": null,
        "ai_recommendations": [
            {
                "id": 1,
                "ai_run_id": 1,
                "product_id": 1,
                "product_name": "Sabun cuci piring",
                "product_price": "15000.00",
                "current_stock": 2,
                "avg_daily_sales": "3.40",
                "recommed_restok_qty": 16,
                "restock_min": 10,
                "restock_max": 16,
                "restock_label": "Saran restock: 10 - 16 item untuk persediaan 7 hari ke depan.",
                "target_days_coverage": null,
                "risk_level": "CRITICAL",
                "urgency_description": "⚠️ DARURAT! Stok diperkirakan habis dalam 1 hari (sekitar tanggal 2026-05-26). Disarankan segera restock.",
                "days_until_emty": 1,
                "estimated_emty_date": "2026-05-26",
                "risk": "CRITICAL",
                "description": "⚠️ DARURAT! Stok diperkirakan habis dalam 1 hari ...",
                "risk_point": 3,
                "stock_timeline": null,
                "seasonal_min": null,
                "seasonal_max": null,
                "seasonal_label": null,
                "seasonal_holiday": null,
                "seasonal_reason": null,
                "created_at": "2026-05-26T06:30:00.000000Z",
                "updated_at": "2026-05-26T06:30:00.000000Z",
                "deleted_at": null,
                "product": {
                    "id": 1,
                    "name": "Sabun cuci piring",
                    "price": "15000.00",
                    "image_url": null,
                    "category_id": 1,
                    "is_active": 1,
                    "user_id": 1,
                    "created_at": "...",
                    "updated_at": "..."
                },
                "ai_recommendation_actions": [],
                "seasonal_recommendation": null
            },
            {
                "id": 4,
                "ai_run_id": 1,
                "product_id": 6,
                "product_name": "Gula pasir 1kg",
                "product_price": "14000.00",
                "current_stock": 14,
                "avg_daily_sales": "4.20",
                "recommed_restok_qty": 22,
                "restock_min": 14,
                "restock_max": 22,
                "restock_label": "Saran restock: 14 - 22 item untuk persediaan 7 hari ke depan.",
                "target_days_coverage": null,
                "risk_level": "MEDIUM",
                "urgency_description": "⚡ PERHATIAN! Stok diestimasi akan habis dalam 4 hari ...",
                "days_until_emty": 4,
                "estimated_emty_date": "2026-05-29",
                "risk": "MEDIUM",
                "description": "⚡ PERHATIAN! Stok diestimasi akan habis dalam 4 hari ...",
                "risk_point": 2,
                "stock_timeline": null,
                "seasonal_min": 21,
                "seasonal_max": 33,
                "seasonal_label": "Restock musiman 21 - 33 item untuk Eid al-Adha (estimated), Vesak Day (estimated).",
                "seasonal_holiday": "Eid al-Adha (estimated), Vesak Day (estimated)",
                "seasonal_reason": "Idul Adha seringkali melibatkan persiapan makanan yang membutuhkan gula dalam jumlah lebih banyak. ...",
                "created_at": "2026-05-26T06:30:00.000000Z",
                "updated_at": "2026-05-26T06:30:00.000000Z",
                "deleted_at": null,
                "product": {
                    "id": 6,
                    "name": "Gula pasir 1kg",
                    "price": "14000.00",
                    "..."
                },
                "ai_recommendation_actions": [],
                "seasonal_recommendation": {
                    "id": 1,
                    "ai_recommendation_id": 4,
                    "min": 21,
                    "max": 33,
                    "label": "Restock musiman 21 - 33 item untuk Eid al-Adha (estimated), Vesak Day (estimated).",
                    "holiday": "Eid al-Adha (estimated), Vesak Day (estimated)",
                    "reason": "Idul Adha seringkali melibatkan persiapan makanan ...",
                    "created_at": "...",
                    "updated_at": "..."
                }
            }
        ]
    }
}
```

#### Response Field Reference — `data` (AiRun)

| Field              | Type      | Deskripsi                                                     |
| ------------------ | --------- | ------------------------------------------------------------- |
| `id`               | integer   | ID AI run                                                     |
| `user_id`          | integer   | ID user pemilik                                               |
| `type_ai`          | string    | Tipe AI: `STOCKS`                                             |
| `status`           | string    | Status: `PROCESSING`, `COMPLETED`, `FAILED`                   |
| `generated_at`     | datetime  | Waktu AI selesai generate                                     |
| `error_message`    | string?   | Pesan error jika status `FAILED`                              |
| `seasonal_insight` | object?   | Insight musiman (lihat tabel di bawah)                        |
| `total_products`   | integer?  | Total produk yang dianalisis                                  |
| `ai_recommendations` | array  | Array rekomendasi per produk (diurutkan `risk_point` DESC)    |

#### Response Field Reference — `seasonal_insight`

| Field                | Type     | Deskripsi                                     |
| -------------------- | -------- | --------------------------------------------- |
| `has_upcoming_holiday`| boolean | Apakah ada hari libur yang mendekat           |
| `upcoming_holidays`  | array    | Daftar hari libur yang mendekat               |
| `upcoming_holidays[].date` | string | Tanggal hari libur (YYYY-MM-DD)         |
| `upcoming_holidays[].name` | string | Nama hari libur                         |
| `upcoming_holidays[].days_away` | integer | Berapa hari lagi                   |
| `upcoming_holidays[].impact` | string | Tingkat dampak: `HIGH`, `MEDIUM`, `LOW` |
| `seasonal_advice`    | string   | Saran musiman dari AI (bahasa Indonesia)      |
| `source`             | string   | Model AI yang digunakan                       |

#### Response Field Reference — `ai_recommendations[]`

| Field                  | Type      | Deskripsi                                                   |
| ---------------------- | --------- | ----------------------------------------------------------- |
| `id`                   | integer   | ID rekomendasi                                              |
| `ai_run_id`            | integer   | FK ke AI run                                                |
| `product_id`           | integer   | FK ke produk                                                |
| `product_name`         | string?   | Nama produk (cache)                                         |
| `product_price`        | decimal   | Harga produk (cache)                                        |
| `current_stock`        | integer   | Stok saat ini                                               |
| `avg_daily_sales`      | decimal   | Rata-rata penjualan harian                                  |
| `recommed_restok_qty`  | integer   | Jumlah restock yang disarankan (= `restock_max`)            |
| `restock_min`          | integer?  | Minimum restock range                                       |
| `restock_max`          | integer?  | Maximum restock range                                       |
| `restock_label`        | string?   | Label deskripsi range restock                               |
| `target_days_coverage` | integer?  | Target hari cakupan stok                                    |
| `risk_level`           | string?   | Level urgensi: `CRITICAL`, `MEDIUM`, `NORMAL`               |
| `urgency_description`  | string?   | Deskripsi urgensi (dengan emoji)                            |
| `days_until_emty`      | integer?  | Estimasi hari sampai stok habis (`null` = aman)             |
| `estimated_emty_date`  | date?     | Estimasi tanggal stok habis (`null` = aman)                 |
| `risk`                 | string?   | Level risiko: `CRITICAL`, `MEDIUM`, `NORMAL`                |
| `description`          | string?   | Deskripsi (sama dengan `urgency_description`)               |
| `risk_point`           | integer   | Skor risiko: `3` = CRITICAL, `2` = MEDIUM, `1` = NORMAL    |
| `stock_timeline`       | array?    | Timeline stok harian (jika tersedia)                        |
| `seasonal_min`         | integer?  | Min restock musiman (dari `seasonal_recommendation`)        |
| `seasonal_max`         | integer?  | Max restock musiman (dari `seasonal_recommendation`)        |
| `seasonal_label`       | string?   | Label restock musiman                                       |
| `seasonal_holiday`     | string?   | Nama hari libur terkait                                     |
| `seasonal_reason`      | string?   | Alasan restock musiman dari AI                              |
| `product`              | object    | Data produk lengkap (relasi)                                |
| `ai_recommendation_actions` | array | Daftar aksi user pada rekomendasi ini                  |
| `seasonal_recommendation` | object? | Data restock musiman detail (relasi)                     |

#### Response Field Reference — `seasonal_recommendation`

| Field                   | Type     | Deskripsi                              |
| ----------------------- | -------- | -------------------------------------- |
| `id`                    | integer  | ID seasonal recommendation             |
| `ai_recommendation_id`  | integer  | FK ke AI recommendation               |
| `min`                   | integer? | Minimum restock musiman               |
| `max`                   | integer? | Maximum restock musiman               |
| `label`                 | string?  | Label deskripsi restock musiman       |
| `holiday`               | string?  | Nama hari libur                       |
| `reason`                | string?  | Alasan AI untuk restock musiman       |

---

#### ❌ 403 Forbidden — Tidak punya subscription PRO

```json
{
    "success": false,
    "message": "This feature requires an active PRO subscription."
}
```

#### ❌ 404 Not Found — Belum ada data AI run

```json
{
    "success": false,
    "message": "No AI run found for STOCKS",
    "data": null
}
```

---

---

## 2. Update Action

Mengupdate aksi user terhadap rekomendasi AI. User bisa menandai rekomendasi sebagai `DONE` (sudah dieksekusi) atau `IGNORE` (diabaikan).

### Request

```
PATCH /api/ai/recommendations/{recommendationId}/action
```

**Headers:**

| Header          | Value              |
| --------------- | ------------------ |
| `Authorization` | `Bearer {token}`   |
| `Accept`        | `application/json` |
| `Content-Type`  | `application/json` |

**Path Parameters:**

| Parameter          | Type    | Required | Deskripsi                 |
| ------------------ | ------- | -------- | ------------------------- |
| `recommendationId` | integer | ✅       | ID dari AI recommendation |

**Body:**

```json
{
    "action_type": "DONE"
}
```

| Field         | Type   | Required | Deskripsi                       |
| ------------- | ------ | -------- | ------------------------------- |
| `action_type` | string | ✅       | Tipe aksi: `DONE` atau `IGNORE` |

---

### Response

#### ✅ 200 OK — Berhasil

```json
{
    "success": true,
    "message": "Action updated successfully",
    "data": {
        "id": 1,
        "ai_recommendation_id": 4,
        "action_type": "DONE",
        "action_at": "2026-05-26T06:35:00.000000Z",
        "created_at": "2026-05-26T06:35:00.000000Z",
        "updated_at": "2026-05-26T06:35:00.000000Z",
        "deleted_at": null
    }
}
```

#### Response Field Reference — `data` (AiRecommendationAction)

| Field                    | Type     | Deskripsi                                 |
| ------------------------ | -------- | ----------------------------------------- |
| `id`                     | integer  | ID action                                 |
| `ai_recommendation_id`   | integer  | FK ke AI recommendation                  |
| `action_type`            | string   | Tipe aksi: `DONE` atau `IGNORE`          |
| `action_at`              | datetime | Waktu aksi dilakukan                      |

---

#### ❌ 403 Forbidden — Tidak punya subscription PRO

```json
{
    "success": false,
    "message": "This feature requires an active PRO subscription."
}
```

#### ❌ 404 Not Found — Recommendation tidak ditemukan

```json
{
    "success": false,
    "message": "AI recommendation not found"
}
```

#### ❌ 422 Unprocessable Entity — Validasi gagal

```json
{
    "message": "The action_type field is required.",
    "errors": {
        "action_type": [
            "The action_type field is required."
        ]
    }
}
```

---

## Notes

- Rekomendasi diurutkan berdasarkan `risk_point` DESC (CRITICAL → MEDIUM → NORMAL).
- Field `seasonal_*` di `ai_recommendations` adalah **appended attributes** yang diambil dari relasi `seasonal_recommendation`. Keduanya berisi data yang sama.
- `updateAction` menggunakan `updateOrCreate`, jadi jika user mengubah aksi dari `DONE` ke `IGNORE` (atau sebaliknya), data yang lama akan di-update, bukan dibuat baru.
- `recommed_restok_qty` diisi dengan nilai `restock_max` dari AI response.
- `days_until_emty` dan `estimated_emty_date` bisa `null` jika stok diprediksi aman (tidak akan habis dalam periode forecast).
