# Dokumentasi API Profile (Mobile Frontend)

Dokumentasi ini menjelaskan endpoint API untuk manajemen profil user di POS System. Semua endpoint ini membutuhkan autentikasi menggunakan **Bearer Token**.

---

## Ringkasan Endpoint

| Method | Endpoint | Deskripsi | Tipe Request |
|:---|:---|:---|:---|
| **GET** | `/api/profile` | Mengambil data profil user saat ini | `application/json` |
| **POST** | `/api/profile` | Membuat profil baru (jika belum ada) | `multipart/form-data` |
| **POST** | `/api/profile` dengan `_method=PUT` | Mengupdate profil (termasuk upload gambar) | `multipart/form-data` |
| **DELETE** | `/api/profile` | Menghapus / meriset profil kembali ke default | `application/json` |

---

## 1. Ambil Profil User (GET)

Mengambil data profil dari user yang sedang login. Jika profil belum terbuat di database, API akan otomatis membuat data default dan mengembalikannya secara instan (aman dari error data kosong).

> [!NOTE]
> Jika user memiliki langganan aktif **PRO** (status billing aktif), respons data akan otomatis menyertakan informasi analisis AI Portofolio bisnis mingguan terbaru (`ai_portfolio`). Jika user belum berlangganan PRO atau belum memiliki riwayat analisis portofolio, field `ai_portfolio` akan bernilai `null`.

* **URL:** `/api/profile`
* **Method:** `GET`
* **Headers:**
  ```http
  Authorization: Bearer <your_token_here>
  Accept: application/json
  ```

### Response Sukses (200 OK) - Pengguna Biasa / Tanpa AI Portfolio
```json
{
  "message": "Profile retrieved successfully.",
  "data": {
    "id": 1,
    "user_id": 12,
    "bio": "Owner dari Kopi Kenangan Mantan",
    "image_url": "https://res.cloudinary.com/.../pos_profiles/example.jpg",
    "created_at": "2026-05-21T10:14:00.000000Z",
    "updated_at": "2026-05-21T10:15:30.000000Z",
    "ai_portfolio": null
  }
}
```

### Response Sukses (200 OK) - Pengguna PRO dengan AI Portfolio Aktif
```json
{
  "message": "Profile retrieved successfully.",
  "data": {
    "id": 1,
    "user_id": 12,
    "bio": "Owner dari Kopi Kenangan Mantan",
    "image_url": "https://res.cloudinary.com/.../pos_profiles/example.jpg",
    "created_at": "2026-05-21T10:14:00.000000Z",
    "updated_at": "2026-05-21T10:15:30.000000Z",
    "ai_portfolio": {
      "id": 1,
      "user_id": 12,
      "type_ai": "PORTFOLIO",
      "status": "COMPLETED",
      "generated_at": "2026-05-21T13:00:00.000000Z",
      "error_message": null,
      "created_at": "2026-05-21T13:00:00.000000Z",
      "updated_at": "2026-05-21T13:00:00.000000Z",
      "portfolio_insight": {
        "id": 1,
        "ai_run_id": 1,
        "user_id": 12,
        "insight": "1. Mantap bosku! Omset minggu ini mencapai Rp 1.500.000...\n2. Produk Indomie Goreng jadi bintang warung kita...",
        "tanggal_laporan": "19 May 2026",
        "periode": "12 May - 19 May 2026",
        "total_omset_minggu_ini": "1500000.00",
        "total_transaksi": 45,
        "rata_rata_transaksi_per_hari": "6.40",
        "rata_rata_omset_per_hari": "214286.00",
        "bintang_warung": [
          {"nama": "Indomie Goreng", "terjual": 50, "omset": 150000}
        ],
        "hari_ramai_tanggal": "2026-05-15",
        "hari_ramai_omset": "350000.00",
        "produk_kurang_laku": ["Taro Snack"],
        "source": "gemini-primary",
        "generated_at": "2026-05-19T13:00:00.000000Z",
        "valid_until": "2026-05-26T13:00:00.000000Z"
      }
    }
  }
}
```

---

## 2. Buat Profil Baru (POST)

Digunakan untuk inisialisasi awal data profil secara manual jika diperlukan.

* **URL:** `/api/profile`
* **Method:** `POST`
* **Headers:**
  ```http
  Authorization: Bearer <your_token_here>
  Accept: application/json
  Content-Type: multipart/form-data
  ```
* **Request Body (FormData):**
  * `bio` (String | Optional) -> Biografi singkat user.
  * `image` (File | Optional) -> File gambar profil (Format: jpg/jpeg/png, Max: 2MB).

### Response Sukses (201 Created)
```json
{
  "message": "Profile created successfully.",
  "data": {
    "id": 1,
    "user_id": 12,
    "bio": "Owner dari Kopi Kenangan Mantan",
    "image_url": "https://res.cloudinary.com/.../pos_profiles/example.jpg",
    "created_at": "2026-05-21T10:14:00.000000Z",
    "updated_at": "2026-05-21T10:14:00.000000Z"
  }
}
```

---

## 3. Update Profil & Upload Gambar (POST / PUT)

> [!IMPORTANT]
> **Catatan Penting untuk Developer Mobile:**
> Di Laravel, request dengan method asli `PUT` atau `PATCH` terkadang tidak dapat membaca file upload (`multipart/form-data`). 
> **Solusi terbaik untuk Mobile (Android/iOS):** 
> Kirim request menggunakan method **POST** ke endpoint `/api/profile` namun tambahkan field `_method` dengan value `PUT` di dalam body FormData Anda.

* **URL:** `/api/profile`
* **Method:** `POST` (dengan method spoofing)
* **Headers:**
  ```http
  Authorization: Bearer <your_token_here>
  Accept: application/json
  Content-Type: multipart/form-data
  ```
* **Request Body (FormData):**
  * `_method` (String | **Wajib**) -> Isi dengan value `PUT`
  * `bio` (String | Optional) -> Biografi baru user.
  * `image` (File | Optional) -> File gambar profil baru (Format: jpg/jpeg/png, Max: 2MB) yang akan otomatis diupload ke **Cloudinary** folder `pos_profiles`.

### Response Sukses (200 OK)
```json
{
  "message": "Profile updated successfully.",
  "data": {
    "id": 1,
    "user_id": 12,
    "bio": "Bio terbaru saya",
    "image_url": "https://res.cloudinary.com/demo/image/upload/v1312461204/pos_profiles/new_profile.jpg",
    "created_at": "2026-05-21T10:14:00.000000Z",
    "updated_at": "2026-05-21T10:19:00.000000Z"
  }
}
```

---

## 4. Hapus / Riset Profil (DELETE)

Menghapus data profil spesifik user dan mereset status profil.

* **URL:** `/api/profile`
* **Method:** `DELETE`
* **Headers:**
  ```http
  Authorization: Bearer <your_token_here>
  Accept: application/json
  ```

### Response Sukses (200 OK)
```json
{
  "message": "Profile deleted / reset successfully."
}
```
