# Roadmap Fitur Fase Berikutnya — PiyohPOS & PiyohWeb

Dokumen ini memuat daftar usulan fitur, optimasi, dan modul lanjutan yang direncanakan untuk pengembangan fase berikutnya setelah peluncuran sistem.

---

## 1. Fitur Pemesanan & Meja (Customer Experience)
- **Shared Cart / Multi-Device Ordering per Meja**:
  - *Kondisi saat ini:* Sistem menggunakan 1 sesi QR per meja. Sesi lama otomatis ditutup (*closed*) apabila ada pemindaian QR baru pada meja yang sama.
  - *Rencana Pengembangan:* Pertimbangkan dukungan sinkronisasi keranjang bersama (*real-time shared cart via WebSocket/Livewire*) di mana beberapa perangkat di meja yang sama dapat saling menambah item ke dalam satu keranjang pesanan rombongan secara kolaboratif.

---

## 2. Keandalan Sinkronisasi Master Data (Sync Reliability)
- **Queued Webhook with Exponential Backoff Retry**:
  - Mengimplementasikan pengiriman sinkronisasi data master dari PiyohWeb ke POS berbasis Laravel Queue/Job dengan retry otomatis jika server POS sedang maintenance atau jaringan terputus.
- **Auto-Sync Media & Assets**:
  - Sinkronisasi otomatis aset gambar produk dan banner promosi ke penyimpanan lokal POS.

---

## 3. Pembayaran & Kasir (Payment & POS)
- **Integrasi Payment Gateway QRIS Dinamis**:
  - Integrasi langsung dengan Payment Gateway (Midtrans/Xendit/DOKU) untuk generate QRIS dinamis langsung di layar HP customer dan kasir dengan verifikasi callback otomatis.
- **Split Bill & Multi-Payment**:
  - Fitur pemecahan tagihan per customer di meja yang sama atau kombinasi pembayaran (Tunai + QRIS).

---
