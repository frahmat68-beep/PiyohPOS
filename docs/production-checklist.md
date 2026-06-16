# Production Readiness Checklist

This document details the checklist and setup procedures required to run PiyohPOS in a production environment.

## 1. Domain & DNS Configuration
The following DNS A-records must be configured to point to the server IP (`213.35.118.26`):

| Domain | Record Type | Target IP | Status |
|---|---|---|---|
| `pos.piyohkopi.com` | A | `213.35.118.26` | Pending DNS Update |
| `admin.piyohkopi.com` | A | `213.35.118.26` | Pending DNS Update |
| `kitchen.piyohkopi.com` | A | `213.35.118.26` | Pending DNS Update |
| `cashier.piyohkopi.com` | A | `213.35.118.26` | Pending DNS Update |

---

## 2. Nginx Virtual Host Setup
Nginx acts as the reverse proxy passing PHP requests to the PHP-FPM socket.
The configuration file is located at `/etc/nginx/sites-available/piyoh-pos`.

### Site Configuration (HTTP fallback / SSL prep)
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pos.piyohkopi.com admin.piyohkopi.com kitchen.piyohkopi.com cashier.piyohkopi.com;
    root /var/www/piyoh-pos/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_read_timeout 60;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 3. SSL Configuration (Let's Encrypt)
Once DNS records propagate, run the following command to obtain SSL certificates and automatically configure HTTPS redirection:

```bash
sudo certbot --nginx -d pos.piyohkopi.com -d admin.piyohkopi.com -d kitchen.piyohkopi.com -d cashier.piyohkopi.com --agree-tos -m contact@piyohkopi.com --non-interactive
```

To verify the auto-renewal system (cron job / systemd timer):
```bash
sudo certbot renew --dry-run
```

---

## 4. Queue Supervisor configuration
Supervisor keeps the queue workers running.
Configuration file: `/etc/supervisor/conf.d/piyoh-pos-queue.conf`

```ini
[program:piyoh-pos-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/piyoh-pos/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=ubuntu
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/piyoh-pos/storage/logs/queue.log
stopasgroup=true
killasgroup=true
```

### Supervisor Commands:
- Reload config: `sudo supervisorctl reread`
- Apply changes: `sudo supervisorctl update`
- Check status: `sudo supervisorctl status`
- Restart workers: `sudo supervisorctl restart piyoh-pos-queue:*`

---

## 5. Spatie Backup Setup
Automated backups are configured in Laravel console routing scheduler:
- File: `routes/console.php`
- Command: `php artisan backup:clean` (Daily at 01:00)
- Command: `php artisan backup:run` (Daily at 02:00)

### Manual verification:
```bash
php artisan backup:run
```
Backups are zipped and stored in `storage/app/private/PiyohPOS/`.

---

## 6. Security (HMAC Webhooks)
- All incoming sync webhooks under `/api/v1/sync/master-data` require the `X-Hub-Signature-256` header.
- Signature matches the request payload hashed with the shared `WEBHOOK_HMAC_SECRET` secret key using the HMAC SHA-256 algorithm.
- Ensure the production `.env` contains:
  ```env
  MASTER_DATA_SYNC_TOKEN=piyoh_sync_secret_2026!
  WEBHOOK_HMAC_SECRET=piyoh_webhook_secure_secret_2026!
  ```

---

## 7. File System Permissions
After initial deployment, ensure `storage/` and `bootstrap/cache/` are owned by `www-data` (the PHP-FPM user):

```bash
sudo chown -R www-data:www-data /var/www/piyoh-pos/storage /var/www/piyoh-pos/bootstrap/cache
sudo chmod -R 775 /var/www/piyoh-pos/storage /var/www/piyoh-pos/bootstrap/cache
sudo usermod -aG www-data ubuntu
```

> **Note:** Without correct permissions, Laravel cannot write to `laravel.log`, which causes 500 errors on any request that triggers logging (all sync requests do).
