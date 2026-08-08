# PRD: NPGLOW (Web App Skincare Terintegrasi)

## 1. Ringkasan Eksekutif
**NPGLOW** adalah platform e-commerce dan telehealth spesifik untuk produk skincare. Platform ini menggabungkan pengalaman belanja online dengan konsultasi kulit profesional (Tim Ahli) serta AI Assistant yang cerdas. 

Berbeda dengan platform e-commerce biasa, NPGLOW memiliki fitur *Skincare Journal* dan sistem konsultasi terbuka untuk semua pengguna (baik yang sudah membeli maupun belum), sehingga pengguna dapat mendiskusikan kondisi kulit mereka sebelum atau setelah pembelian.

## 2. Tujuan & Lingkup Proyek
### 2.1 Tujuan
- Membangun *brand awareness* dan kepercayaan untuk NPGLOW melalui konsultasi profesional dan teknologi AI.
- Memfasilitasi transaksi pembelian produk secara langsung.
- Memberikan wadah (*Skincare Journal*) bagi pelanggan untuk melacak perkembangan (*progress*) kulit mereka.

### 2.2 Lingkup (MVP)
1. **Landing Page:** Halaman depan yang estetik, menampilkan katalog produk (Marketplace) dan CTA menuju fitur Konsultasi.
2. **Autentikasi:** Sistem login dan registrasi berbasis *session* menggunakan PHP native.
3. **PWA (Progressive Web App):** Dukungan instalasi aplikasi di *smartphone* dengan halaman fallback offline yang menarik.
4. **Marketplace/Checkout:** Menampilkan produk (harga, gambar, dll), integrasi keranjang, dan proses *checkout* (integrasi *payment gateway* / manual transfer).
5. **Consultation Hub (Sistem Terbuka):**
   - **AI Assistant:** Chat 24/7 dengan dukungan Google Gemini (RAG) menggunakan Knowledge Base khusus NPGLOW.
   - **Chat Tim Ahli:** Chat real-time berbasis AJAX polling untuk konsultasi mendalam dengan ahli.
6. **Skincare Journal:** Fitur pendukung chat di mana pengguna dapat mengunggah foto wajah untuk dianalisa.
7. **Dashboard Admin/Expert:** Mengelola chat masuk, status produk, status pengguna (Pelanggan / Calon Pelanggan), dan pesanan.

---

## 3. Fitur Utama

### 3.1 PWA (Progressive Web App)
- Dukungan *Service Worker* (`sw.js`) untuk caching halaman dan *assets*.
- *Install Prompt* pintar di *navbar* (Hanya muncul jika belum diinstal).
- Halaman `offline.html` bermerek NPGLOW ketika koneksi internet terputus.

### 3.2 Consultation Hub (Halodoc Style)
- **Tersedia Untuk Semua Pengguna:** Baik pelanggan maupun calon pelanggan dapat mengakses fitur ini (tanpa gerbang pembelian).
- **Pilihan Mode Konsultasi (`konsultasi.php`):**
  - **AI Assistant:** Bot dengan RAG yang memberikan respons seputar skincare dan produk NPGLOW. Terbatas pada pengetahuan yang di-*inject* melalui `knowledge-base.json`.
  - **Chat Tim Ahli:** Mode komunikasi langsung dengan admin/expert. Hub menampilkan badge status (Online / Offline) dari Tim Ahli secara dinamis.

### 3.3 Live Chat Real-Time (AJAX)
- Menggunakan *AJAX long-polling* ringan agar tidak membebani server *shared hosting*.
- Mendukung pengiriman teks dan gambar (*Skincare Journal*).

### 3.4 Marketplace & Order Management
- Menampilkan produk dengan deskripsi, *ingredients*, dan kecocokan jenis kulit.
- Manajemen *cart* (keranjang) dan proses pemesanan.
- *Tagging* pengguna: Saat pesanan valid/selesai, sistem mencatat status `has_purchased = true` di profil pengguna.

### 3.5 Dashboard Expert / Admin
- Menampilkan daftar pengguna yang sedang butuh konsultasi.
- **Badge Status:** Membedakan pengguna yang sudah beli produk (*Pelanggan*) dan yang belum (*Calon Pelanggan*).
- *Ping/Heartbeat System:* Memastikan status *Online* Tim Ahli selalu *up-to-date*.

---

## 4. Stack Teknologi
- **Backend:** PHP Native (Minimal 8.0)
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla), Tailwind CSS, SweetAlert2, AOS (Animate on Scroll).
- **Database:** MySQL / MariaDB (Driver `mysqli`)
- **AI Integration:** Google Gemini API (v1beta / Free Tier)

---

## 5. Skema Database

- `users`: Data pengguna (id, name, email, password, role, is_online, last_active, has_purchased, created_at)
- `products`: Katalog produk NPGLOW.
- `ai_chats`: Riwayat obrolan AI per pengguna (id, user_id, sender, message, created_at).
- `consultation_messages`: Riwayat chat dengan Tim Ahli (termasuk unggahan gambar).
- `orders`: Data transaksi pembelian.

---

## 6. Deployment & Hosting
Sistem dirancang sedemikian rupa sehingga:
- Dapat berjalan dengan baik di *Shared Hosting* (cPanel, dll) tanpa memerlukan Node.js atau WebSocket.
- RAG AI diproses di sisi server PHP menggunakan *cURL* ke API Gemini Google, sehingga API Key aman.
- Fitur *Polling* dioptimalkan sedemikian rupa agar beban *database* minimal.
