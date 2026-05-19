# Instruksi Lengkap Pembuatan Fitur AI Portfolio

Dokumen ini berisi panduan detail untuk membuat dua method utama di `AiRunController` terkait fitur AI Portfolio. Ikuti spesifikasi field dan response di bawah ini.

---

## Penjelasan API Eksternal (Machine Learning Insight)

Saat memanggil external API untuk generate portofolio, controller ini akan melakukan hit ke endpoint AI Service. Berikut adalah spesifikasi payload dan respons dari AI Service tersebut agar kamu memahami struktur data yang diolah.

### Endpoint API Eksternal
**`POST /insights/generate`** (Relatif terhadap `AI_URL`)

### Request (Payload) yang Dikirim
Data transaksi dikirim melalui request body (JSON) dengan parameter `data`. Controller akan mengirim kumpulan data model `Transaction`. Format JSON yang diterima oleh AI Service kira-kira seperti ini:

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "trx_type": "SALE",
      "trx_date": "2026-05-18T10:00:00",
      "payment_method": "CASH",
      "total_amount": "50000",
      "items": [
        {
          "product_id": 101,
          "quantity": 2,
          "unit_price": "25000",
          "product": {
            "id": 101,
            "name": "Indomie Goreng",
            "price": "25000",
            "stocks": [
              {
                "id": "019dc8a5-47d3-716f-b18f-444d7cfc593b",
                "product_id": 1,
                "stock_on_hand": 2,
                "created_at": "2026-05-02T08:00:00.000000Z",
                "updated_at": "2026-05-18T07:24:03.000000Z",
                "deleted_at": null
              }
            ]
          }
        }
      ]
    }
  ]
}
```

### Response Berhasil (200 OK)
Jika berhasil, AI Service akan merespons dengan struktur berikut (digunakan pada tahap parsing API Response di bawah):

```json
{
  "message": "Portofolio bisnis mingguan berhasil dibuat",
  "data": {
    "insight": "1. Mantap bosku! Omset minggu ini mencapai Rp 1.500.000...\n2. Produk Indomie Goreng jadi bintang warung kita...\n3. Tapi hati-hati, produk Taro kurang laku, mungkin bisa coba dipromo...",
    "summary": {
      "tanggal_laporan": "19 May 2026",
      "periode": "12 May - 19 May 2026",
      "total_omset_minggu_ini": 1500000,
      "total_transaksi": 45,
      "rata_rata_transaksi_per_hari": 6.4,
      "rata_rata_omset_per_hari": 214286,
      "bintang_warung": [
        {"nama": "Indomie Goreng", "terjual": 50, "omset": 150000}
      ],
      "hari_paling_ramai": {"tanggal": "2026-05-15", "omset": 350000},
      "hari_paling_sepi": {"tanggal": "2026-05-18", "omset": 50000},
      "produk_kurang_laku": ["Taro Snack"]
    },
    "source": "gemini-primary", 
    "generated_at": "2026-05-19 13:00:00",
    "valid_until": "2026-05-26 13:00:00"
  }
}
```

> **Info Tambahan (Skema Fallback & Keandalan LLM):** 
> Sistem AI menggunakan pendekatan berlapis (retry mechanism). Jika satu provider LLM (seperti Gemini 2.0 Flash) terkena rate limit, ia akan otomatis fallback ke provider lain (Gemini Lite, lalu Groq) untuk memastikan data tetap tergenerate.

---

## 1. Method `generatePortfolio(Request $request): JsonResponse`

Method ini berfungsi untuk mengirimkan data transaksi user ke AI, lalu menyimpan hasil analisis portofolio ke dalam database.

### Langkah-langkah & Ketentuan:

1. **Cek Langganan PRO**
   Gunakan `$this->checkPro($request->user())`.
   Jika `false`, return response JSON:
   - `success`: `false`
   - `message`: `'This feature requires an active PRO subscription.'`
   - HTTP Status: `403`

2. **Persiapan Data**
   - Ambil `AI_URL` dan `AI_API_TOKEN` dari environment (`env()`).
   - Query data `Transaction` dengan memanggil relasi `items.product` (gunakan `.with(["items.product"])`).
   - Filter transaksi berdasarkan `user_id` user yang sedang login (`$request->user()->id`).

3. **Hit API Eksternal (Lakukan dalam `try-catch`)**
   - Gunakan `Http::withToken($AI_API_TOKEN)->post($AI_URL . '/insights/generate', ['data' => $transactions])`

4. **Penanganan Jika API Berhasil (`$response->successful()`)**
   - Dapatkan data response: `$responseData = $response->json();` lalu ambil `$aiData = $responseData['data'];`
   - Ambil bagian summary: `$summary = $aiData['summary'] ?? [];`
   
   **a. Simpan Data ke Tabel `AiRun`**
   Buat data `AiRun` baru dengan detail field berikut:
   - `user_id`: `$request->user()->id`
   - `type_ai`: `'PORTFOLIO'`
   - `status`: `'COMPLETED'`
   - `generated_at`: `now()`

   **b. Simpan Data ke Tabel `AiPortfolioInsight`**
   Buat data `AiPortfolioInsight` yang terhubung dengan `$aiRun->id` dengan detail field berikut:
   - `ai_run_id`: ID dari data `AiRun` di atas
   - `user_id`: `$request->user()->id`
   - `insight`: `$aiData['insight'] ?? null`
   - `tanggal_laporan`: `$summary['tanggal_laporan'] ?? null`
   - `periode`: `$summary['periode'] ?? null`
   - `total_omset_minggu_ini`: `$summary['total_omset_minggu_ini'] ?? 0`
   - `total_transaksi`: `$summary['total_transaksi'] ?? 0`
   - `rata_rata_transaksi_per_hari`: `$summary['rata_rata_transaksi_per_hari'] ?? 0`
   - `rata_rata_omset_per_hari`: `$summary['rata_rata_omset_per_hari'] ?? 0`
   - `bintang_warung`: `$summary['bintang_warung'] ?? null`
   - `hari_ramai_tanggal`: `$summary['hari_paling_ramai']['tanggal'] ?? null`
   - `hari_ramai_omset`: `$summary['hari_paling_ramai']['omset'] ?? null`
   - `produk_kurang_laku`: `$summary['produk_kurang_laku'] ?? null`
   - `source`: `$aiData['source'] ?? null`
   - `generated_at`: `$aiData['generated_at'] ?? now()`
   - `valid_until`: `$aiData['valid_until'] ?? null`

   **c. Return JSON Success**
   Return response JSON dengan status `200`:
   - `success`: `true`
   - `message`: `'Weekly portfolio generated successfully'`
   - `data`: Data AiRun yang diload relasinya `portfolioInsight` (`$aiRun->load('portfolioInsight')`)

5. **Penanganan Jika API Gagal (API return error, bukan exception)**
   Buat data `AiRun` baru:
   - `user_id`: `$request->user()->id`
   - `type_ai`: `'PORTFOLIO'`
   - `status`: `'FAILED'`
   - `generated_at`: `now()`
   - `error_message`: `$response->body()`
   
   Lalu return JSON dengan HTTP status dari response API:
   - `success`: `false`
   - `message`: `'Failed to generate weekly portfolio'`

6. **Penanganan Jika Error System (Block `catch (\Exception $e)`)**
   Buat data `AiRun` baru dengan field yang sama seperti poin 5, namun `error_message` diisi dengan `$e->getMessage()`.
   
   Lalu return JSON dengan HTTP status `500`:
   - `success`: `false`
   - `message`: `'An error occurred during portfolio generation: ' . $e->getMessage()`

---

## 2. Method `latestPortfolio(Request $request): JsonResponse`

Method ini bertugas mengembalikan hasil analisis AI Portfolio yang paling terakhir/terbaru milik user.

### Langkah-langkah & Ketentuan:

1. **Cek Langganan PRO**
   Lakukan validasi yang sama persis seperti method di atas. Jika gagal, return 403.

2. **Query Database**
   Ambil 1 data `AiRun` (gunakan `first()`) dengan kriteria:
   - `user_id`: Sesuai dengan user login saat ini
   - `type_ai`: `'PORTFOLIO'`
   - Diurutkan: descending (`desc`) berdasarkan kolom `created_at`
   - Muat relasi: `portfolioInsight` (gunakan `with('portfolioInsight')`)

3. **Penanganan Jika Data Kosong (`!$aiRun`)**
   Return JSON dengan HTTP status `404`:
   - `success`: `false`
   - `message`: `'No AI run found for PORTFOLIO insights'`
   - `data`: `null`

4. **Penanganan Jika Data Ditemukan**
   Return JSON dengan HTTP status `200`:
   - `success`: `true`
   - `message`: `'Latest AI PORTFOLIO run retrieved successfully'`
   - `data`: Berisi `$aiRun` hasil query
