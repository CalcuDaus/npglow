# Product Requirements Document (PRD)
# Landing Page & Platform NPGLOW

**Versi:** 0.1 (Draft)
**Tanggal:** 3 Juli 2026
**Referensi Konsep:** Halodoc (landing page & flow konsultasi)
**Status:** Brainstorming — untuk didiskusikan & direvisi

---

## 1. Latar Belakang & Tujuan

NPGLOW adalah brand yang membutuhkan landing page dengan konsep mirip Halodoc: informatif, terpercaya, dan mengarahkan user ke dua aksi utama:

1. **Konsultasi/chat** dengan syarat sudah pernah membeli produk NPGLOW.
2. **Belanja produk** melalui marketplace yang ditampilkan di landing page.

Tujuan utama dokumen ini adalah menyepakati scope, arsitektur teknis, dan struktur halaman sebelum masuk ke tahap desain/development.

---

## 2. Target Tech Stack

Karena target hosting adalah **shared hosting murah**, stack dipilih agar ringan dan kompatibel:

| Layer | Pilihan | Alasan |
|---|---|---|
| Bahasa Backend | **PHP native / PHP + framework ringan** (mis. CodeIgniter 4 atau Laravel 10/11 jika hosting support) | Kompatibel shared hosting, dokumentasi luas |
| Database | **MySQL / MariaDB** | Default di hampir semua shared hosting (cPanel) |
| Frontend | HTML + CSS (Tailwind/Bootstrap) + JS (vanilla / jQuery) | Ringan, tidak butuh build tool khusus di server |
| Flash Message | **SweetAlert2** | Sesuai requirement |
| Realtime Chat | Polling AJAX (interval) atau **Pusher/Ably (free tier)** jika butuh realtime, karena WebSocket native sulit di shared hosting | Shared hosting umumnya tidak support long-running socket server |
| File/Asset Storage | Folder lokal di hosting (`/uploads`) | Hemat biaya, tanpa cloud storage tambahan |
| Payment Gateway | Midtrans / Xendit (untuk transaksi marketplace) | Umum dipakai di Indonesia, mendukung berbagai metode pembayaran |

> **Catatan:** Jika nanti butuh chat realtime penuh (WebSocket), perlu VPS minimal — bisa jadi fase 2. Untuk MVP di shared hosting, polling AJAX tiap beberapa detik sudah cukup.

---

## 3. Struktur Halaman Landing Page (mengacu Halodoc)

### 3.1 Header / Navbar
- Logo NPGLOW
- Menu: Produk, Tentang, Konsultasi, (opsional: Artikel/Edukasi)
- Tombol **Masuk / Daftar**

### 3.2 Hero Section
- Headline utama (value proposition NPGLOW)
- CTA utama: "Konsultasi Sekarang" & "Belanja Produk"
- Ilustrasi/gambar produk atau chat mockup (mirip mockup "HILDA" di Halodoc)

### 3.3 Section "Kenapa Konsultasi di NPGLOW"
- Value proposition: legit/BPOM, direspons cepat, dst. (isi menyesuaikan brand)
- Badge kepercayaan (jika ada — misal sertifikasi produk)

### 3.4 Section Konsultasi (Chat)
- Penjelasan singkat cara kerja
- **Gate/syarat:** tombol "Mulai Konsultasi" → cek status pembelian user
  - Jika **belum pernah beli** → SweetAlert info + CTA ke marketplace
  - Jika **sudah pernah beli** → redirect ke halaman/chat konsultasi

### 3.5 Marketplace Section (bagian bawah landing page)
- Grid/list produk NPGLOW (gambar, nama, harga, tombol "Beli"/"+Keranjang")
- Filter/kategori produk (opsional untuk MVP)
- Link "Lihat Semua Produk" → halaman katalog penuh

### 3.6 Footer
- Info brand, kontak, sosial media, disclaimer (jika produk kesehatan/skincare perlu disclaimer BPOM dll.)

---

## 4. Flow Utama

### 4.1 Flow Registrasi/Login
1. User daftar (email/no HP + password) atau login.
2. Verifikasi (OTP/email) — opsional untuk MVP awal, bisa disederhanakan dulu.

### 4.2 Flow Pembelian Produk (Marketplace)
1. User pilih produk → tambah ke keranjang.
2. Checkout → isi alamat → pilih metode pembayaran.
3. Setelah pembayaran **berhasil**, sistem mencatat `has_purchased = true` pada akun user (atau menyimpan riwayat order yang bisa dicek).
4. SweetAlert konfirmasi: "Pembayaran berhasil! Kamu sekarang bisa konsultasi."

### 4.3 Flow Konsultasi (Chat) — *placeholder, detail menyusul dari user*
1. User klik "Mulai Konsultasi".
2. Sistem cek: apakah user punya minimal 1 riwayat pembelian **valid** (status paid/selesai)?
   - **Tidak** → SweetAlert: "Konsultasi hanya untuk pembeli produk NPGLOW" + tombol ke marketplace.
   - **Ya** → lanjut ke halaman chat (pilih admin/CS yang online, atau auto-assign).
3. *(Detail lanjutan flow chat akan ditentukan oleh user — misalnya: apakah 1x beli = 1x jatah konsultasi, atau unlimited selama pernah beli, ada batas waktu, dsb.)*

> Poin ini akan diperbarui begitu detail flow dari kamu tersedia — beberapa hal yang perlu dipastikan nanti:
> - Apakah konsultasi berlaku selamanya setelah 1x beli, atau per-produk/per-order?
> - Siapa yang menjawab chat: admin manual, dokter/CS, atau bot dulu?
> - Apakah ada limit jumlah/durasi konsultasi per user?

---

## 5. Modul/Fitur Sistem (Draft)

| Modul | Deskripsi |
|---|---|
| Auth | Register, Login, Logout, Reset Password |
| Produk & Marketplace | List produk, detail produk, kategori, keranjang, checkout |
| Order & Payment | Riwayat order, status pembayaran, integrasi payment gateway |
| Konsultasi/Chat | Validasi syarat beli, ruang chat, riwayat chat |
| Admin Panel | Kelola produk, kelola order, kelola chat/CS, kelola user |
| Notifikasi | SweetAlert (client-side flash message), email/WA notifikasi order (opsional) |

---

## 6. Database (Draft Skema Tingkat Tinggi)

- `users` (id, nama, email, no_hp, password, role, created_at)
- `products` (id, nama, deskripsi, harga, stok, gambar, kategori_id)
- `categories` (id, nama)
- `orders` (id, user_id, total, status, created_at)
- `order_items` (id, order_id, product_id, qty, harga)
- `consultations` (id, user_id, admin_id, status, created_at)
- `consultation_messages` (id, consultation_id, sender_id, pesan, created_at)

> Skema ini masih kasar, perlu disesuaikan begitu flow konsultasi final.

---

## 7. Non-Functional Requirements

- **Performance:** ringan untuk shared hosting (hindari query berat, gunakan cache sederhana jika perlu).
- **Security:** hashing password (bcrypt), proteksi SQL Injection (prepared statement/ORM), validasi input, CSRF token di form.
- **Responsif:** mobile-first, karena traffic kesehatan biasanya dominan mobile.
- **SEO dasar:** meta title/description tiap halaman produk & artikel.

---

## 8. Yang Masih Perlu Diputuskan (Open Questions)

1. Detail lengkap flow chat konsultasi (siapa yang balas, batasan, dsb.) — akan disusun user.
2. Apakah butuh fitur artikel/edukasi kesehatan seperti "Kamus Kesehatan A-Z" di Halodoc, atau cukup landing + marketplace + chat saja untuk MVP?
3. Payment gateway mana yang akan dipakai (Midtrans/Xendit/lainnya)?
4. Apakah perlu app mobile juga, atau web-only dulu?
5. Branding: warna, logo, tone komunikasi NPGLOW (biar desain landing page konsisten)?

---

## 9. Next Steps

1. Finalisasi flow konsultasi (dari user).
2. Buat wireframe/mockup landing page (Figma atau langsung HTML draft).
3. Breakdown modul di atas menjadi task development per sprint.
4. Setup environment PHP di shared hosting (pilih framework: native/CodeIgniter/Laravel).

