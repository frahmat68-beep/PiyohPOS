# Pre-Launch Security & Architecture Review — PiyohPOS

Dokumen ini mencatat evaluasi keamanan komprehensif, gap arsitektur, dan mitigasi sebelum peluncuran sistem **PiyohPOS** ke produksi.

---

## 1. Known Gap - Sync Reliability
- Saat ini sync dari PiyohWeb ke PiyohPOS tidak punya retry/outbox otomatis. Jika request gagal (POS down/network putus), perubahan data master bisa hilang tanpa notifikasi ke siapapun.
- **Rekomendasi sebelum go-live:** Minimal tambahkan alert (log/notifikasi) kalau ada sync gagal, supaya admin sadar dan bisa trigger ulang manual lewat `SyncLogResource` yang sudah ada di Filament.
- **Rekomendasi fase berikutnya:** Queued webhook dengan retry backoff di sisi PiyohWeb.

---

## 2. Ringkasan Status Keamanan & Hardening

| Parameter Keamanan | Status | Tindakan / Hasil Audit |
| :--- | :---: | :--- |
| **1. Rate Limiting** |  Hardened | Ditambahkan `throttle:30,1` pada scan QR, `throttle:15,1` pada checkout, serta `throttle:60,1` pada API sync & health check. |
| **2. Validasi & Mass Assignment** |  Aman | Real-time stock validation diterapkan pada checkout. Validasi ketat diterapkan pada cart, checkout, dan master data sync. |
| **3. Konfigurasi Session & Cookie** |  Aman | Sesi QR dibatasi 4 jam (`expires_at`), `http_only` aktif, `same_site=lax` aktif. `SESSION_SECURE_COOKIE=true` diwajibkan saat HTTPS. |
| **4. Kebocoran Kredensial (.env)** |  Bersih | Riwayat git (`git log -p -- .env*`) diverifikasi bersih dari credentials asli. Secret key dikonfigurasi via env server. |
| **5. Role & Permission (Multi-Panel)** |  Terisolasi | Middleware `RestrictPanelAccess` mengisolasi akses panel `cashier`, `kitchen`, dan `admin`. Cashier tidak dapat membuka panel admin/dapur. |
| **6. Keamanan Endpoint /api/health** |  Aman | Endpoint `/api/health` hanya menampilkan boolean status layanan internal tanpa mengekspos versi framework, error stack trace, atau koneksi DB mentah. |

---

## 3. Rincian Proteksi & Isolasi Multi-Panel

### A. Rate Limiting & Proteksi Endpoint
- `GET /scan/{token}`: Maksimum 30 request per menit per IP (`throttle:30,1`).
- `POST /checkout`: Maksimum 15 checkout per menit per IP (`throttle:15,1`).
- `POST /api/v1/sync/master-data`: Maksimum 60 request per menit (`throttle:60,1`) + HMAC SHA-256 Signature + Bearer Token.
- `GET /api/health`: Maksimum 60 request per menit (`throttle:60,1`).

### B. Isolasi Hak Akses Multi-Panel
- **Panel Kasir (`/cashier`)**: Khusus role `cashier`, `admin`, `super_admin`.
- **Panel Dapur (`/kitchen`)**: Khusus role `kitchen`, `admin`, `super_admin`.
- **Panel POS Admin (`/admin`)**: Khusus role `admin`, `super_admin`.
- Percobaan lintas akses tanpa izin akan langsung memutus sesi (*Auth::logout()*) dan dialihkan ke login.

### C. Keamanan Device Kasir & Dapur (Shared Device)
- Disarankan mengatur masa aktif session (`SESSION_LIFETIME=120`) dan logout otomatis saat pergantian shift kasir.
- Pastikan browser kasir tidak menyimpan kredensial otomatis (*disable browser password autofill* pada perangkat bersama).

---

## 4. Checklist Hardening Produksi (Server Kiki)
- [ ] Pastikan `APP_ENV=production` dan `APP_DEBUG=false` pada file `.env` di `/var/www/piyoh-pos`.
- [ ] Pastikan `SESSION_SECURE_COOKIE=true` pada file `.env` saat SSL HTTPS aktif di domain POS.
- [ ] Pastikan `WEBHOOK_HMAC_SECRET` dan `MASTER_DATA_SYNC_TOKEN` menggunakan random string dengan entropi tinggi.
- [ ] Supervisor queue worker berjalan aktif (`piyoh-pos-queue`).
- [ ] Jadwal backup database harian aktif di crontab/scheduler.

---
