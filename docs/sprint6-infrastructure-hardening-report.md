# Sprint 6 — Infrastructure & Security Hardening Report

This report outlines the configurations, security implementations, server audits, and verification outputs completed in Sprint 6.

## 1. Commit Metadata
- **Commit Hash:** `f41cef3240efaa0d748e697d627fea04bc4b273d`
- **Target Server:** `213.35.118.26`
- **Application Path:** `/var/www/piyoh-pos`

---

## 2. Server Audit & Nginx Virtual Host Migration
- **Verification:** The application has been fully migrated away from `php artisan serve` to Nginx + PHP 8.4-FPM.
- **Nginx configuration (`/etc/nginx/sites-available/piyoh-pos`):**
  - Serves subdomains: `pos.piyohkopi.com`, `admin.piyohkopi.com`, `kitchen.piyohkopi.com`, and `cashier.piyohkopi.com`.
  - Configured to route PHP requests directly to `unix:/var/run/php/php8.4-fpm.sock`.
  - Verified host-based routing.

---

## 3. SSL Configuration (Let's Encrypt)
- **Install:** Certbot and its Nginx helper (`python3-certbot-nginx`) have been installed successfully on the server.
- **Redirects:** Redirect configurations are prepared. Once DNS records are active, running `sudo certbot --nginx` will secure the site with auto-redirects to HTTPS.

---

## 4. Queue System Setup (Supervisor)
- **Status:** Supervisor is installed and configured. Two concurrent queue worker processes (`numprocs=2`) are running under the `ubuntu` user context.
- **Worker command:** `php /var/www/piyoh-pos/artisan queue:work --sleep=3 --tries=3`
- **Supervisor Status Output:**
  ```text
  piyoh-pos-queue:piyoh-pos-queue_00   RUNNING   pid 937360, uptime 0:03:55
  piyoh-pos-queue:piyoh-pos-queue_01   RUNNING   pid 937361, uptime 0:03:55
  ```

---

## 5. Spatie Backup System
- **Schedule:** Backups are scheduled inside `routes/console.php` as follows:
  - Cleanup: Daily at 01:00 (`backup:clean`)
  - Run: Daily at 02:00 (`backup:run`)
- **Manual Run Output:**
  ```text
  Starting backup...
  Dumping database piyoh_pos...
  Determining files to backup...
  Zipping 998 files and directories...
  Created zip containing 998 files and directories. Size is 3.57 MB
  Copying zip to disk named local...
  Successfully copied zip to disk named local.
  Backup completed!
  ```
- **Backup Location:** Zip archives are stored on the server at `/var/www/piyoh-pos/storage/app/private/PiyohPOS/`.

---

## 6. Webhook Security (HMAC Signature Validation)
- **Implementation:** Added the `VerifyWebhookSignature` middleware to compute HMAC SHA-256 signatures of inbound payloads using a shared secret (`MASTER_DATA_SYNC_SECRET`).
- **Endpoint Protection:** The `/api/v1/sync/master-data` route is protected by both `api.token` and `VerifyWebhookSignature` middleware.
- **Verification:** Rejection and verification tests have been written and run successfully.

---

## 7. Expanded Health Monitoring
- **Endpoint:** `/api/health`
- **Output JSON:**
  ```json
  {
    "status": "OK",
    "timestamp": "2026-06-16T17:03:38+00:00",
    "services": {
      "database": "connected",
      "queue": "healthy",
      "storage": "healthy",
      "cache": "healthy"
    }
  }
  ```

---

## 8. Test Verification
- All 37 local tests passed successfully:
  ```text
  Tests:    37 passed
  Assertions: 104 passed
  ```
