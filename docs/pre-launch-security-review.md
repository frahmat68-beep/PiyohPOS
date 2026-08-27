# Pre-Launch Security & Architecture Review — PiyohPOS

Dokumen ini mencatat evaluasi keamanan, gap arsitektur, dan mitigasi sebelum peluncuran sistem ke produksi.

---

## Known Gap - Sync Reliability
- Saat ini sync dari PiyohWeb ke PiyohPOS tidak punya retry/outbox otomatis. Jika request gagal (POS down/network putus), perubahan data master bisa hilang tanpa notifikasi ke siapapun.
- **Rekomendasi sebelum go-live:** Minimal tambahkan alert (log/notifikasi) kalau ada sync gagal, supaya admin sadar dan bisa trigger ulang manual lewat `SyncLogResource` yang sudah ada di Filament.
- **Rekomendasi fase berikutnya:** Queued webhook dengan retry backoff di sisi PiyohWeb.

---
