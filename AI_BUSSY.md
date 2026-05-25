# AI Bussy Hour API

Dokumentasi untuk endpoint pengambilan data analisis jam sibuk (Bussy Hour) terakhir.

## Mendapatkan Analisis Jam Sibuk Terbaru

Endpoint ini mengambil hasil analisis jam sibuk terakhir yang berhasil diselesaikan untuk pengguna.

- **Endpoint:** `GET /api/ai/runs/latest/busy-hours`
- **Method:** `GET`
- **Authentication:** `Bearer Token` (memerlukan login)

### Request

Tidak ada body yang diperlukan untuk request ini.

**Contoh cURL:**

```bash
curl -X GET "http://localhost:8000/api/ai/runs/latest/busy-hours" \
     -H "Authorization: Bearer <YOUR_AUTH_TOKEN>" \
     -H "Accept: application/json"
```

### Response

#### Response Sukses (200 OK)

Respons ini berisi data lengkap dari analisis terakhir yang berhasil.

```json
{
    "success": true,
    "message": "Latest busy hour AI run fetched successfully",
    "data": {
        "ai_run": {
            "id": 1,
            "user_id": 1,
            "type_ai": "BUSY",
            "status": "COMPLETED",
            "generated_at": "2024-05-24T10:00:00.000000Z",
            "error_message": null,
            "created_at": "2024-05-24T10:00:00.000000Z",
            "updated_at": "2024-05-24T10:00:00.000000Z",
            "busyHourDailyForecasts": [
                {
                    "id": 1,
                    "ai_run_id": 1,
                    "forecast_date": "2024-05-25",
                    "day_name": "Saturday",
                    "day_of_week": 5,
                    "is_weekend": true,
                    "total_predicted_trx": 50,
                    "est_trx_min": 45,
                    "est_trx_max": 55,
                    "est_trx_label": "45-55",
                    "total_predicted_revenue": 500000,
                    "est_revenue_min": 480000,
                    "est_revenue_max": 520000,
                    "est_revenue_label": "480k-520k",
                    "peak_hour": "19:00",
                    "peak_hour_label": "7 PM",
                    "peak_hour_trx": 15,
                    "busy_hours_count": 4,
                    "hourlyPredictions": [
                        {
                            "id": 1,
                            "daily_forecast_id": 1,
                            "hour": "09:00",
                            "predicted_transactions": 5,
                            "est_trx_min": 3,
                            "est_trx_max": 7,
                            "est_trx_label": "3-7",
                            "predicted_revenue": 50000,
                            "est_revenue_min": 45000,
                            "est_revenue_max": 55000,
                            "est_revenue_label": "45k-55k",
                            "busy_level": "Low",
                            "busy_label": "Sepi",
                            "emoji": "😌",
                            "what_to_prepare": "Stok dasar, siapkan promosi.",
                            "productPredictions": [
                                {
                                    "id": 1,
                                    "hourly_prediction_id": 1,
                                    "product_id": 101,
                                    "product_name": "Kopi Americano",
                                    "probability": 0.8,
                                    "estimated_qty": 4,
                                    "estimated_revenue": 40000
                                }
                            ]
                        }
                    ]
                }
            ]
        },
        "summary": {
            "analysis_date": "2024-05-24T10:00:00.000000Z",
            "forecast_days": 14,
            "busiest_day": {
                "day_name": "Saturday",
                "total_predicted_transactions": 50
            },
            "quietest_day": {
                "day_name": "Monday",
                "total_predicted_transactions": 20
            },
            "total_peak_hours": 28,
            "top_peak_hours": [
                { "hour": "19:00", "occurrence": 7 },
                { "hour": "20:00", "occurrence": 6 }
            ]
        }
    }
}
```

#### Response Gagal (404 Not Found)

Terjadi jika belum ada analisis jam sibuk yang pernah dijalankan atau berhasil.

```json
{
    "success": false,
    "message": "No successful busy hour AI run found."
}
```
