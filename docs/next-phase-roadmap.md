# Roadmap Fitur Fase Berikutnya — PiyohPOS & PiyohWeb

Dokumen ini memuat daftar usulan fitur, optimasi, dan modul lanjutan yang direncanakan untuk pengembangan sprint berikutnya setelah peluncuran sistem utama.

---

## 🟡 Sprint Berikutnya (Prioritas Operasional & UX)

1. **Audio Alert / Notifikasi Suara di Kitchen Panel**
   - *Kondisi saat ini:* Kitchen panel mengandalkan polling visual periodik untuk menampilkan pesanan baru.
   - *Rencana Pengembangan:* Menambahkan efek suara chime / audio cue otomatis (HTML5 Audio API) setiap kali ada pesanan baru berstatus `confirmed` yang masuk antrian racik, sehingga staf dapur/barista yang sibuk tidak melewatkan pesanan.

2. **Custom 404 & Error Pages Bergaya Piyoh**
   - *Kondisi saat ini:* URL yang salah/kedaluwarsa (misal QR token tidak valid, IDOR tracking mismatch) menampilkan default exception/error page bawaan framework.
   - *Rencana Pengembangan:* Halaman 404/500/403 bermerek Piyoh Kopi dengan tone yang ramah, visual warm ivory/olive, serta tombol bantuan "Minta Bantuan Barista/Kasir" atau "Pindai Ulang Meja".

3. **Batas Maksimum Item per Keranjang (Cart Max Limits)**
   - *Kondisi saat ini:* Keranjang belum membatasi jumlah akumulasi total kuantitas per meja.
   - *Rencana Pengembangan:* Validasi batas wajar per pemesanan (misal max 50 item / Rp 5.000.000 per checkout) untuk mencegah penyalahgunaan payload Midtrans Snap dan membatasi ukuran antrian dapur.

4. **Reconnect & Sesi Persisten QR Browser**
   - *Kondisi saat ini:* Penutupan tab/browser penuh bisa memutuskan keterikatan session code jika cookie hilang sebelum checkout.
   - *Rencana Pengembangan:* Mekanisme pemulihan sesi pintar yang memungkinkan perangkat yang sama menautkan kembali ke sesi meja aktif selama masa berlaku sesi (4 jam) belum habis.

---

## 🔵 Roadmap Jangka Panjang (Post-Launch Expansion)

1. **PWA (Progressive Web App) & Offline-First Menu**
   - Dukungan Service Worker dan caching aset agar katalog menu tetap bisa dibuka dengan cepat meski koneksi seluler customer di cafe sedang lambat.

2. **Integrasi Printer Thermal Kasir & Dapur (ESC/POS Bluetooth/Network)**
   - Cetak struk otomatis ke printer thermal dapur (checker ticket) saat pesanan dikonfirmasi dan struk customer di kasir.

3. **Customer Push Notification via Web Push API**
   - Notifikasi langsung ke layar HP pelanggan saat pesanan telah berubah status menjadi `ready` / siap diambil di bar.

4. **Multi-Outlet Consolidated Analytics**
   - Dashboard analitik pendapatan dan perbandingan performa antar outlet untuk manajemen pusat / owner.
