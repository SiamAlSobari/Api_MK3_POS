# 🚀 Dokumentasi API POS (Versi Paling Manusiawi)

Halo teman-teman frontend dan mobile! 👋 

Ini adalah contekan rahasia (panduan lengkap) cara nyambungin aplikasi kalian ke API backend kita. Dokumen ini sengaja dibikin pakai bahasa nongkrong supaya kalian nggak pusing baca kode yang kaku. 

**Aturan Main Paling Penting:**
Hampir semua fitur di bawah ini butuh **Token**. Anggap aja token ini kayak tiket VIP. Kalau kalian nggak bawa tiketnya, satpam (server) bakal nolak kalian dan ngasih error **401 Unauthorized**.
Cara pakainya gampang, masukin token di Header setiap request kalian:
`Authorization: Bearer <TOKEN_KAMU_DISINI>`

Yuk, kita mulai petanya! 🗺️

---

## 🔑 1. Pintu Gerbang (Login & Register)
Ini satu-satunya area yang **nggak butuh token**, karena emang tujuannya buat *ngedapetin* token.

### 📝 Bikin Akun Baru (Register)
* **Method:** `POST`
* **URL:** `/api/auth/register`
* **Fungsi:** Buat daftarin user baru. Hebatnya, tiap ada yang daftar, backend otomatis bikinin data awal (kategori & produk sampel) biar warungnya nggak kosong melompong.

### 🚪 Masuk Aplikasi (Login)
* **Method:** `POST`
* **URL:** `/api/auth/login`
* **Fungsi:** Buat login. Kalau email dan password bener, kalian bakal dikasih **Token**. *Simpan token ini baik-baik (misal di Local Storage), jangan sampai ilang!*

### 🕵️ Cek Nyawa Token (Session)
* **Method:** `GET`
* **URL:** `/api/auth/session`
* *(Butuh Token 🎟️)*
* **Fungsi:** Cuma buat mastiin, "Eh token gue masih idup nggak sih?" Kalau berhasil, berarti user masih login.

---

## ❤️ 2. Cek Denyut Nadi Server (Health Check)
* **Method:** `GET`
* **URL:** `/api/health`
* **Fungsi:** Buat nge-ping doang. Kalau servernya sehat walafiat, dia bakal bales status `ok`. Cocok buat fitur "Check Connection" di aplikasi kalian.

---

## 👤 3. "Siapa Gue?" (Profil User)
* **Method:** `GET`
* **URL:** `/api/user`
* *(Butuh Token 🎟️)*
* **Fungsi:** Buat ngambil data profil abang/mbak yang lagi login sekarang. Backend tahu ini siapa dari token yang kalian kirim.

---

## 📦 4. Gudang Barang (Produk & Kategori)
Ini tempat kalian ngatur barang jualan. Semuanya **WAJIB pakai Token** ya!

### 🏷️ Kategori (Rak Barang)
* **Lihat semua rak:** `GET /api/categories`
* **Bikin rak baru:** `POST /api/categories`
* **Lihat detail 1 rak:** `GET /api/categories/{id}`
* **Ubah nama rak:** `PUT /api/categories/{id}`
* **Hapus rak:** `DELETE /api/categories/{id}`
* **Matiin/Nyalain rak (Aktif/Nonaktif):** `PATCH /api/categories/{id}/status`
* **⭐ JURUS ANDALAN:** `GET /api/categories/products`
  *(Ini enak banget buat frontend! Kalian bisa ambil semua kategori sekaligus sama isi produk-produk di dalamnya. Tinggal render buat menu kasir deh!)*

### 🍔 Produk (Isi Barangnya)
* **Lihat semua barang:** `GET /api/products`
* **Tambah barang baru:** `POST /api/products`
* **Lihat detail 1 barang:** `GET /api/products/{id}`
* **Edit info barang:** `PUT /api/products/{id}`
* **Hapus barang:** `DELETE /api/products/{id}`

---

## 💸 5. Meja Kasir (Transaksi)
Tempat nyatet duit masuk atau barang rusak/disesuaikan. Semuanya **WAJIB pakai Token**!

* **Lihat Daftar Bon (Riwayat):** `GET /api/transactions`
* **Lihat Detail 1 Bon:** `GET /api/transactions/{id}`
* **Bayar di Kasir (Bikin Transaksi):** `POST /api/transactions`
  *(Ini bisa buat transaksi jualan normal, atau sekadar nyatat penyesuaian stok alias adjustment)*

---

## 💳 6. Bayar Langganan (Billing Midtrans)
Buat user yang mau upgrade ke fitur Sultan (PRO / PRO MAX).

* **Beli Paket (Subscribe):** `POST /api/billing/subscribe` *(Butuh Token 🎟️)*
  *(Ini bakal ngembaliin link atau kode dari Midtrans buat dibayar sama usernya)*
* **Cek Status Langganan:** `GET /api/billing/active` *(Butuh Token 🎟️)*
  *(Nanya ke backend, "Akun ini lagi langganan paket apa dan sampai kapan?")*
* **Webhook Pembayaran:** `POST /api/billing/webhook`
  *(Yang ini **KHUSUS** buat server Midtrans ngobrol sama server kita. Frontend jangan coba-coba manggil ini ya!)*

---

## 🤖 7. Fitur Cenayang AI (Khusus Sultan PRO MAX)
Fitur magis yang bisa nebak masa depan warung! Semuanya **WAJIB pakai Token**.

* **Suruh AI Mikir Rekomendasi Stok:** `POST /api/ai/runs/analyze`
* **Suruh AI Ngeramal Jam Rame:** `POST /api/ai/runs/analyze-busy-hours`
* **Lihat Hasil Mikir Stok Terakhir:** `GET /api/ai/runs/latest/stocks`
* **Lihat Hasil Ramalan Jam Rame:** `GET /api/ai/runs/latest/busy-hours`
* **Tandai Tugas AI (Udah Dikerjain/Dicuekin):** `PATCH /api/ai/recommendations/{id}/action`

---

## 📊 8. Buku Laporan Bos (Reports)
Buat lihat cuan dan rekap jualan. **WAJIB pakai Token**.

* **Laporan Ringkas:** `GET /api/reports`
  *(Dapat ringkasan jualan hari ini, minggu ini, dll)*
* **Laporan Sejarah Detail:** `GET /api/reports/sales-history`
  *(Buat nampilin daftar panjang sejarah jualan, udah ada fitur pembagian halamannya/pagination).*

---
### 💡 Pesan Terakhir
Kalau tiba-tiba aplikasi kalian nge-bug dan dapet balikan (response) error dari backend, jangan panik! Baca dulu pesan error JSON-nya, biasanya backend udah ngasih tau salahnya di mana (misal: "email harus diisi", "stok nggak cukup", dll).

**Selamat Ngoding! Semangat terus sampai rilis! 🚀☕**
