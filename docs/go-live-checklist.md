# Go-Live & Production Deployment Checklist — Piyoh Kopi

Dokumen panduan dan checklist ini berlaku gabungan untuk **PiyohPOS** (`/var/www/piyoh-pos`) dan **PiyohWeb** (`/var/www/piyoh-web`) pada server produksi **Lazarus (`213.35.118.26`)**.

---

## 1. Topologi Server & Domain

| Aplikasi | Path Server | Domain / Subdomain | Port / Socket |
| :--- | :--- | :--- | :--- |
| **PiyohWeb** | `/var/www/piyoh-web` | `piyohkopi.com` | `unix:/var/run/php/php8.4-fpm.sock` |
| **PiyohPOS** | `/var/www/piyoh-pos` | `pos.piyohkopi.com`, `admin.piyohkopi.com`, `cashier.piyohkopi.com`, `kitchen.piyohkopi.com` | `unix:/var/run/php/php8.4-fpm.sock` |
| **Database** | MySQL Server | Localhost (`127.0.0.1:3306`) | Database `piyoh_web` & `piyoh_pos` |

---

## 2. Checklist Environment Variables (`.env`)

Sebelum menjalankan aplikasi di server produksi, pastikan variabel `.env` di masing-masing direktori aplikasi telah dikonfigurasi:

### A. Konfigurasi Umum (`.env` PiyohWeb & PiyohPOS)
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` telah di-generate menggunakan `php artisan key:generate --force`
- [ ] `APP_URL=https://pos.piyohkopi.com` (PiyohPOS) / `https://piyohkopi.com` (PiyohWeb)
- [ ] **HTTPS Cookie Security**: `SESSION_SECURE_COOKIE=true` (Wajib aktif saat HTTPS aktif agar cookie otentikasi hanya dikirim via koneksi TLS/HTTPS terenkripsi).
- [ ] `SESSION_HTTP_ONLY=true` (Mencegah pencurian session via XSS).
- [ ] `SESSION_SAME_SITE=lax` (Mitigasi serangan CSRF).
- [ ] `SESSION_DRIVER=database` atau `SESSION_DRIVER=file`
- [ ] Kredensial MySQL: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

### B. Konfigurasi Khusus Master Data Sync (PiyohWeb & PiyohPOS)
- [ ] `MASTER_DATA_SYNC_TOKEN` telah di-generate string acak baru (64 karakter) khusus server produksi (jangan gunakan default `.env.example`).
- [ ] `WEBHOOK_HMAC_SECRET` telah di-generate string acak baru (64 karakter) khusus server produksi.
- [ ] Nilai kedua token tersebut identik antara `.env` di `piyoh-web` dan `piyoh-pos`.

---

## 3. Langkah-Langkah Deployment (Step-by-Step)

Jalankan perintah berikut di server produksi (`ssh -i ssh-key-Lazarus.key ubuntu@213.35.118.26`):

### A. Deploy / Update PiyohPOS
```bash
cd /var/www/piyoh-pos

# 1. Tarik pembaruan kode
git pull origin main

# 2. Install dependensi PHP
composer install --no-dev --optimize-autoloader

# 3. Build aset frontend
npm install --ignore-scripts
npm run build

# 4. Jalankan migrasi database
php artisan migrate --force

# 5. Optimasi cache framework
php artisan optimize

# 6. Set permission direktori
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# 7. Restart Queue Workers di Supervisor
sudo supervisorctl restart piyoh-pos-queue:*
```

### B. Deploy / Update PiyohWeb
```bash
cd /var/www/piyoh-web

# 1. Tarik pembaruan kode
git pull origin main

# 2. Install dependensi PHP (production mode)
composer install --no-dev --optimize-autoloader

# 3. Build aset frontend (Tailwind/Vite)
npm install --ignore-scripts
npm run build

# 4. Jalankan migrasi database
php artisan migrate --force

# 5. Optimasi cache framework
php artisan optimize

# 6. Set permission direktori
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### C. Reload Web Server & PHP-FPM
```bash
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl reload php8.4-fpm
```

---

## 4. Checklist Verifikasi Pasca Deploy (Go-Live Verification)

Setelah deployment selesai, lakukan pemeriksaan satu per satu:

- [ ] **SSL / HTTPS**: Sertifikat SSL Let's Encrypt aktif di semua domain tanpa error/peringatan browser (`sudo certbot renew --dry-run` lolos).
- [ ] **Health Check POS**: Mengakses `https://pos.piyohkopi.com/api/health` mengembalikan respon `200 OK` dengan status `database`, `queue`, `storage`, dan `cache` berstatus `healthy`.
- [ ] **Tampilan Publik PiyohWeb**:
  - Halaman Beranda (`/`), Tentang Kami (`/about`), Menu (`/menu`), dan Karir (`/careers`) tampil sempurna.
  - Halaman Outlet (`/outlet`) hanya menampilkan cabang aktif (Galaxy) dan tidak menampilkan cabang Bekasi draf.
  - Form Kontak (`/contact`) dapat mengirim pesan dan tersimpan di database CMS.
- [ ] **Login Admin CMS (`https://piyohkopi.com/admin`)**: Login berhasil untuk akun `super_admin`.
- [ ] **Sinkronisasi Master Data**: Menguji transmisi sinkronisasi data master dari PiyohWeb ke PiyohPOS dengan signature HMAC valid.
- [ ] **Pemesanan QR Meja (Customer Flow)**:
  - Buka URL meja contoh (`https://pos.piyohkopi.com/scan/<TOKEN>`).
  - Pilih menu, masukkan catatan, tambah ke keranjang, dan lakukan checkout.
  - Pesanan muncul di **Cashier Panel** (`https://cashier.piyohkopi.com`).
  - Kasir menyetujui pesanan $\rightarrow$ Pesanan muncul di **Kitchen Display System** (`https://kitchen.piyohkopi.com`).
  - Status dapat diubah ke *Cooking* dan *Ready*.
- [ ] **Queue Worker**: Jalankan `sudo supervisorctl status` dan pastikan `piyoh-pos-queue` berstatus `RUNNING`.
- [ ] **Backup Database**: Jalankan `php artisan backup:run` dan pastikan file zip backup tersimpan di folder storage.

---

## 5. Prosedur Rollback Sederhana (Jika Terjadi Kendala Rilis)

Apabila rilis baru mengalami kegagalan fatal pada server produksi, ikuti langkah rollback berikut untuk mengembalikan sistem ke versi stabil sebelumnya:

```bash
# 1. Masuk ke direktori aplikasi yang bermasalah
cd /var/www/piyoh-pos  # atau cd /var/www/piyoh-web

# 2. Hentikan antrean proses sementara (khusus POS)
sudo supervisorctl stop piyoh-pos-queue:*

# 3. Kembalikan kode git ke commit stabil sebelumnya
# Ganti <STABLE_COMMIT_HASH> dengan hash commit sebelumnya yang valid
git reset --hard <STABLE_COMMIT_HASH>

# 4. Install ulang dependensi sesuai lockfile versi stabil
composer install --no-dev --optimize-autoloader
npm run build

# 5. Rollback migrasi database (HANYA jika rilis baru menambahkan migrasi yang rusak)
# php artisan migrate:rollback --step=1 --force
# Atau restore dump SQL database dari folder backup: storage/app/private/...

# 6. Bersihkan dan perbarui cache aplikasi
php artisan optimize:clear
php artisan optimize

# 7. Nyalakan kembali queue worker dan reload server
sudo supervisorctl restart piyoh-pos-queue:*
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx

# 8. Verifikasi ulang endpoint /api/health dan halaman publik
curl -I https://pos.piyohkopi.com/api/health
```

## 6. Log Mutasi Data Khusus & Aturan Operasional Database

### A. Catatan Mutasi Manual Historis
* **2026-08-28 (Staging Deploy Pertama)**:
  * Dilakukan eksekusi query pembersihan manual: `DELETE FROM outlets WHERE slug = 'bekasi';` pada database `piyoh_web` di server Lazarus.
  * **Alasan**: Menghapus data draf placeholder outlet lama (`Piyoh Kopi Bekasi`) setelah pembaruan data resmi outlet kedua menjadi `Piyoh Jaktim` (`slug = 'jaktim'`, `is_active = false`).

### B. Aturan Kerja Operasional Produksi (Production Rules)
> [!IMPORTANT]
> **Aturan Mutasi Database Langsung**:
> Segala bentuk manipulasi data langsung pada database server produksi (`raw SQL` manual / direct delete/update) **WAJIB dikonfirmasi dan disetujui terlebih dahulu** oleh Penanggung Jawab Proyek / Owner sebelum dijalankan, kecuali dalam kondisi *emergency / hotfix outage* yang mengancam integritas sistem.
> Perubahan data normal harus selalu melalui alur resmi: Migration, Seeder terkontrol, atau Panel Admin CMS Filament.

---
