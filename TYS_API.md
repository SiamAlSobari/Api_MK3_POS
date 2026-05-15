# API Specification - Reports & Dashboard

Dokumentasi ini berisi spesifikasi API untuk modul Laporan (Dashboard) dan Riwayat Penjualan. API ini digunakan oleh Frontend / Mobile App untuk menampilkan ringkasan data, statistik, grafik penjualan, dan riwayat transaksi.

---

## 1. Get Dashboard Reports (Ringkasan Laporan)

Endpoint ini mengembalikan seluruh data ringkasan metrik dashboard, termasuk total pendapatan, rata-rata keranjang, tren penjualan, dan produk terlaris. Data dipisah berdasarkan periode (hari ini, minggu ini, bulan ini, tahun ini, dan sepanjang masa). 

Endpoint ini juga menyediakan helper `grafik_data` khusus untuk mempermudah render *chart* (grafik) di Mobile/Frontend.

- **Method:** `GET`
- **Endpoint:** `/api/reports` *(Sesuaikan prefix route dengan `routes/api.php` Anda)*
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`

### Response Success (200 OK)

```json
{
  "message": "Report data retrieved successfully.",
  "data": {
    "hari_ini": {
      "total_pendapatan": 500000,
      "pendapatan_vs_sebelumnya": {
        "nilai_sebelumnya": 400000,
        "persentase_perubahan": 25.0
      },
      "total_transaksi": 10,
      "rata_rata_keranjang": 50000.0,
      "tren_penjualan": [
        {
          "date": "2023-10-31",
          "total": 500000.0
        }
      ],
      "produk_terlaris": [
        {
          "name": "Kopi Susu",
          "total_quantity": 20
        }
      ],
      "transaksi_terakhir": [
        {
          "id": 1,
          "trx_type": "SALE",
          "total_amount": 50000,
          "trx_date": "2023-10-31 10:00:00",
          "items": []
        }
      ]
    },
    "minggu_ini": {
        "...": "Sama dengan struktur hari_ini"
    },
    "bulan_ini": {
        "...": "Sama dengan struktur hari_ini"
    },
    "tahun_ini": {
        "...": "Sama dengan struktur hari_ini"
    },
    "sepanjang_masa": {
        "...": "Sama dengan struktur hari_ini"
    },
    "grafik_data": {
      "minggu_ini": {
        "labels": ["2023-10-25", "2023-10-26", "2023-10-27"],
        "values": [150000, 200000, 250000]
      },
      "bulan_ini": {
        "labels": ["2023-10-01", "2023-10-02"],
        "values": [500000, 600000]
      },
      "tahun_ini": {
        "labels": ["2023-01-01", "2023-02-01"],
        "values": [15000000, 20000000]
      }
    }
  }
}
```

**Penjelasan Field (Untuk Frontend):**
- Objek `hari_ini`, `minggu_ini`, dll memiliki properti yang sama. Masing-masing hanya menghitung metrik dalam *date range* tersebut. Semua query bersifat `SALE` only (penjualan murni).
- **`pendapatan_vs_sebelumnya.persentase_perubahan`**: Nilai persentase pertumbuhan dibanding periode yang sama sebelumnya. Jika positif berarti naik (hijau), jika negatif berarti turun (merah).
- **`tren_penjualan`**: Format array object (`[{ date, total }]`).
- **`grafik_data`**: **Ini fitur yang dibuat khusus untuk mempermudah render Chart (grafik) di Frontend/Mobile App.** Anda tidak perlu melooping `tren_penjualan` lagi untuk memisahkan sumbu X dan Y. Gunakan array `labels` untuk sumbu X (Tanggal) dan array `values` untuk sumbu Y (Total Pendapatan).

---

## 2. Get Sales History (Riwayat Penjualan)

Mengembalikan riwayat transaksi dengan tipe `SALE` (penjualan). Mendukung pagination, filter periode, dan pencarian berdasarkan nama produk atau ID transaksi.

- **Method:** `GET`
- **Endpoint:** `/api/reports/sales-history`
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Accept: application/json`
- **Query Parameters:**
  - `period` *(opsional)*: Filter waktu. Contoh: `hari_ini`, `today`, atau defaultnya `semua`.
  - `per_page` *(opsional)*: Jumlah data per halaman. Default: `10`. Maksimal: `100`.
  - `search` *(opsional)*: Kata kunci pencarian berdasarkan nama produk, ID transaksi, atau total nominal.

### Request Example
`GET /api/reports/sales-history?period=semua&per_page=15&search=kopi`

### Response Success (200 OK)

```json
{
  "message": "Riwayat transaksi penjualan (SALE) berhasil diambil",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 150,
        "user_id": 1,
        "trx_type": "SALE",
        "total_amount": 105000,
        "trx_date": "2023-10-31 14:30:00",
        "created_at": "2023-10-31T14:30:00.000000Z",
        "updated_at": "2023-10-31T14:30:00.000000Z",
        "user": {
          "id": 1,
          "name": "Owner Toko"
        },
        "items": [
          {
            "id": 300,
            "transaction_id": 150,
            "product_id": 10,
            "quantity": 2,
            "subtotal": 50000,
            "product": {
              "id": 10,
              "name": "Kopi Susu Gula Aren",
              "price": 25000
            }
          }
        ]
      }
    ],
    "first_page_url": "http://localhost/api/reports/sales-history?page=1",
    "from": 1,
    "last_page": 5,
    "last_page_url": "http://localhost/api/reports/sales-history?page=5",
    "next_page_url": "http://localhost/api/reports/sales-history?page=2",
    "path": "http://localhost/api/reports/sales-history",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 75
  }
}
```

**Penjelasan Field (Untuk Frontend):**
- Menggunakan standar format Laravel Pagination.
- List data ada di array `data.data`.
- Untuk membuat fitur *Infinite Scroll* (lazy loading) di Mobile App, cukup pantau param `next_page_url` atau hitung page ke-N dan kirim `&page=N` di endpoint selanjutnya.
- `items` sudah me-*load* relasi `product` sehingga bisa langsung menampilkan detail barang apa saja yang dibeli pada transaksi tersebut.