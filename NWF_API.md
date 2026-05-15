# API Specification - AI Features (NWF)

Dokumentasi ini berisi spesifikasi API untuk fitur AI terkait manajemen Stok Barang. API ini dikhususkan untuk menampilkan prediksi stok terakhir dan mengubah status / aksi rekomendasi yang diberikan oleh AI.

**Catatan Penting:** 
Semua endpoint dalam dokumen ini membutuhkan *Authorization Bearer Token* dari pengguna yang telah berlangganan (subscription) dengan paket **`PRO_MAX`** dan statusnya **`ACTIVE`**. Jika tidak, akan mengembalikan respons `403 Forbidden`.

---

## 1. Get Latest AI STOCKS Run (Hasil Prediksi Stok Terakhir)

Mengambil hasil analisis (run) AI terbaru khusus untuk prediksi **STOCKS** milik pengguna yang sedang login. Data yang dikembalikan sudah mencakup daftar rekomendasi produk (seperti kuantitas yang harus di-restock, estimasi tanggal kosong, tingkat risiko) dan action apa yang sudah diambil oleh pengguna (DONE / IGNORE).

- **Method:** `GET`
- **Endpoint:** `/api/ai/runs/latest/stocks`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

### Response Success (200 OK)

```json
{
    "success": true,
    "message": "Latest AI STOCKS run retrieved successfully",
    "data": {
        "id": 15,
        "user_id": 1,
        "type_ai": "STOCKS",
        "status": "COMPLETED",
        "generated_at": "2023-11-01T10:00:00.000000Z",
        "created_at": "2023-11-01T10:00:00.000000Z",
        "updated_at": "2023-11-01T10:00:00.000000Z",
        "ai_recommendations": [
            {
                "id": 101,
                "ai_run_id": 15,
                "product_id": 5,
                "current_stock": 2,
                "recommed_restok_qty": 50,
                "risk_level": "HIGH",
                "days_until_emty": 1,
                "estimated_emty_date": "2023-11-02",
                "risk": "Stockout Risk",
                "description": "Critical low stock, restock immediately.",
                "risk_point": 95,
                "product": {
                    "id": 5,
                    "name": "Indomie Renteng",
                    "price": 115000,
                    "image": "url_gambar.jpg"
                },
                "ai_recommendation_actions": null
            },
            {
                "id": 102,
                "ai_run_id": 15,
                "product_id": 8,
                "current_stock": 10,
                "recommed_restok_qty": 20,
                "risk_level": "MEDIUM",
                "days_until_emty": 4,
                "estimated_emty_date": "2023-11-05",
                "risk": "Moderate Risk",
                "description": "Consider restocking soon.",
                "risk_point": 65,
                "product": {
                    "id": 8,
                    "name": "Kopi Kapal Api",
                    "price": 15000,
                    "image": "url_gambar2.jpg"
                },
                "ai_recommendation_actions": {
                    "id": 10,
                    "ai_recommendation_id": 102,
                    "action_type": "DONE",
                    "action_at": "2023-11-01T10:15:00.000000Z"
                }
            }
        ]
    }
}
```

### Response Error

**403 Forbidden (Tidak Punya Akses PRO_MAX)**
```json
{
    "success": false,
    "message": "This feature requires an active PRO_MAX subscription."
}
```

**404 Not Found (Belum Pernah Melakukan AI Run)**
```json
{
    "success": false,
    "message": "No AI run found for STOCKS",
    "data": null
}
```

**Penjelasan Field (Untuk Frontend):**
- Data utama adalah objek `data` (tabel `AiRun`).
- Array `ai_recommendations` berisi rekomendasi restock per produk.
- Relasi `product` menampilkan informasi asli produk (nama, harga).
- Jika relasi `ai_recommendation_actions` bernilai `null`, berarti *user* belum melakukan *action* (belum klik "DONE" atau "IGNORE") pada item tersebut. Jika sudah, akan terisi object action-nya.

---

## 2. Update AI Recommendation Action (Ubah Status Rekomendasi AI)

Mengubah status respon user terhadap satu item rekomendasi AI. Biasanya di Frontend ini adalah aksi ketika user menekan tombol **"Tandai Selesai" (DONE)** atau **"Abaikan" (IGNORE)** pada kartu peringatan restock.

- **Method:** `PATCH`
- **Endpoint:** `/api/ai/recommendations/{recommendationId}/action`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Path Parameter:**
  - `recommendationId` (integer): ID dari tabel `ai_recommendations` (misalnya `101` atau `102` pada contoh GET di atas).

### Request Body

```json
{
    "action_type": "DONE" 
}
```
*(Catatan: Value `action_type` HANYA boleh bernilai `"DONE"` atau `"IGNORE"`)*

### Response Success (200 OK)

```json
{
    "success": true,
    "message": "Action updated successfully",
    "data": {
        "id": 10,
        "ai_recommendation_id": 101,
        "action_type": "DONE",
        "action_at": "2023-11-01T12:00:00.000000Z",
        "created_at": "2023-11-01T12:00:00.000000Z",
        "updated_at": "2023-11-01T12:00:00.000000Z"
    }
}
```

### Response Error

**422 Unprocessable Entity (Validasi Gagal)**
```json
{
    "message": "The given data was invalid.",
    "errors": {
        "action_type": [
            "The selected action type is invalid."
        ]
    }
}
```

**404 Not Found (ID Rekomendasi Tidak Ditemukan)**
```json
{
    "success": false,
    "message": "AI recommendation not found"
}
```