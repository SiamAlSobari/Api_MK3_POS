# API Specification - Sales History (RN)

Dokumentasi ini berisi spesifikasi API untuk mengambil riwayat transaksi khusus tipe penjualan (`SALE`). Endpoint ini sudah dilengkapi dengan fitur pencarian, filter periode, dan pagination bawaan Laravel untuk mempermudah implementasi list atau *infinite scroll* di sisi Mobile/Frontend.

---

## Get Sales History (Riwayat Penjualan)

Mengambil daftar transaksi penjualan yang dilakukan oleh *user* yang sedang *login*.

- **Method:** `GET`
- **Endpoint:** `/api/reports/sales-history`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

### Query Parameters

Anda dapat mengirimkan parameter ini melalui URL (contoh: `/api/reports/sales-history?period=hari_ini&per_page=15&search=kopi`)

| Parameter  | Tipe    | Default | Keterangan |
| :---       | :---    | :---    | :---       |
| `period`   | String  | `semua` | Filter berdasarkan waktu penjualan. Nilai yang diterima: `semua` (menampilkan semua waktu), `hari_ini`, atau `today` (hanya transaksi di hari ini). |
| `per_page` | Integer | `10`    | Menentukan jumlah data yang ditampilkan per halaman. Angka maksimal adalah `100`. |
| `search`   | String  | `""`    | Kata kunci untuk pencarian. Akan mencari ke: **ID Transaksi**, **Total Harga (total_amount)**, dan **Nama Produk** di dalam transaksi tersebut. |

### Response Success (200 OK)

Respons yang dikembalikan menggunakan format *Length Aware Paginator* dari Laravel. Data transaksi berada di dalam array `data.data`.

```json
{
    "message": "Riwayat transaksi penjualan (SALE) berhasil diambil",
    "data": {
        "current_page": 1,
        "data": [
            {
                "id": 105,
                "user_id": 1,
                "trx_type": "SALE",
                "total_amount": 75000,
                "trx_date": "2023-11-01 14:30:00",
                "created_at": "2023-11-01T14:30:00.000000Z",
                "updated_at": "2023-11-01T14:30:00.000000Z",
                "user": {
                    "id": 1,
                    "name": "Budi Santoso",
                    "email": "budi@example.com"
                },
                "items": [
                    {
                        "id": 201,
                        "transaction_id": 105,
                        "product_id": 12,
                        "quantity": 3,
                        "subtotal": 75000,
                        "created_at": "2023-11-01T14:30:00.000000Z",
                        "updated_at": "2023-11-01T14:30:00.000000Z",
                        "product": {
                            "id": 12,
                            "name": "Kopi Susu Gula Aren",
                            "price": 25000,
                            "category_id": 2
                        }
                    }
                ]
            }
        ],
        "first_page_url": "http://domain.com/api/reports/sales-history?page=1",
        "from": 1,
        "last_page": 5,
        "last_page_url": "http://domain.com/api/reports/sales-history?page=5",
        "next_page_url": "http://domain.com/api/reports/sales-history?page=2",
        "path": "http://domain.com/api/reports/sales-history",
        "per_page": 10,
        "prev_page_url": null,
        "to": 10,
        "total": 45
    }
}
```

### Penjelasan Data untuk Frontend / Mobile (React Native / Kotlin / dll):

1. **Pagination / Infinite Scroll:**
   - Gunakan URL dari `data.next_page_url` untuk memanggil halaman selanjutnya secara otomatis saat user melakukan *scroll* ke bawah.
   - Jika `next_page_url` bernilai `null`, artinya itu adalah halaman terakhir (sudah tidak ada data lagi).
2. **Menampilkan List Data:**
   - Datanya adalah array di path `response.data.data`.
3. **Detail Pembelian (Items):**
   - Transaksi ini sudah me-*load* relasi ke `items.product`. Artinya, jika user mengklik sebuah riwayat transaksi, Anda sudah memiliki data lengkap mengenai produk apa saja yang dibeli tanpa perlu *hit* API untuk detail lagi.