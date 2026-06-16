# Phase 2 Report — Master Data Integration Hardening

**Date:** 2026-06-16
**Branch:** main
**Commit:** 65206d7

---

## Architecture Rule

> PiyohWeb is the **ONLY MASTER DATA SYSTEM**.
> PiyohPOS is a **CONSUMER ONLY** — it reads but never creates, updates, or deletes master data.

---

## 1. Filament Resource Audit — Read-Only Enforcement

| Resource | Actions Removed | Status |
|---|---|---|
| `OutletResource` | create route, edit route, EditAction, DeleteBulkAction | ✅ Read-only |
| `CategoryResource` | create route, edit route, EditAction, DeleteBulkAction | ✅ Read-only |
| `ProductResource` | create route, edit route, EditAction, DeleteBulkAction | ✅ Read-only |
| `ProductPricesRelationManager` | CreateAction, AssociateAction, EditAction, DissociateAction, DeleteAction, all BulkActions | ✅ Read-only |

All list views now show `source_system` and `last_synced_at` columns for visibility.

---

## 2. Sync Column Migrations

| Table | Columns Added |
|---|---|
| `outlets` | `external_id` (unique), `source_system`, `last_synced_at` |
| `categories` | `external_id` (unique), `source_system`, `last_synced_at` |
| `products` | `external_id` (unique), `source_system`, `last_synced_at` |
| `product_prices` | `external_id` (unique), `source_system`, `last_synced_at` |

---

## 3. SyncService

**File:** `app/Services/SyncService.php`

### Methods

| Method | Upsert Key | FK Resolution |
|---|---|---|
| `syncOutlets(array)` | `external_id` | — |
| `syncCategories(array)` | `external_id` | — |
| `syncProducts(array)` | `external_id` | `category_id` ← Category `external_id` |
| `syncPrices(array)` | `external_id` | `product_id` ← Product `external_id`; `outlet_id` ← Outlet `external_id` |
| `syncAll(array)` | orchestrates all, wrapped in DB transaction | — |

### Behavior
- Fully **idempotent** — same payload sent twice yields no duplicates
- Items with missing required fields are **skipped** (counted in `skipped`)
- Errors are caught per-item, logged, and included in `errors[]` (non-blocking)
- Ordered processing: `outlets → categories → products → prices`

---

## 4. Sync API Endpoint

```
POST /api/v1/sync/master-data
Authorization: Bearer <MASTER_DATA_SYNC_TOKEN>
Content-Type: application/json
```

### Payload Schema

```json
{
  "outlets": [
    { "id": "string", "name": "string", "slug": "string", "address": "string?", "phone": "string?", "is_active": "bool?" }
  ],
  "categories": [
    { "id": "string", "name": "string", "slug": "string", "sort_order": "int?" }
  ],
  "products": [
    { "id": "string", "name": "string", "slug": "string", "category_id": "string?", "description": "string?", "base_price": "numeric", "sku": "string?", "is_active": "bool?" }
  ],
  "prices": [
    { "id": "string", "product_id": "string", "outlet_id": "string", "price": "numeric", "is_available": "bool?" }
  ]
}
```

All four keys are optional — send only what changed.

### Response

```json
{
  "success": true,
  "message": "Master data sync completed.",
  "results": {
    "outlets":    { "synced": 1, "skipped": 0, "errors": [] },
    "categories": { "synced": 1, "skipped": 0, "errors": [] },
    "products":   { "synced": 1, "skipped": 0, "errors": [] },
    "prices":     { "synced": 1, "skipped": 0, "errors": [] }
  }
}
```

### Auth
Token configured in `config/master-data.php` → env `MASTER_DATA_SYNC_TOKEN`.
Middleware: `VerifyApiToken` (401 on missing/wrong token).

---

## 5. Test Coverage

**File:** `tests/Feature/MasterDataSyncIntegrationTest.php`

| Test | Result |
|---|---|
| `test_invalid_token_is_rejected` | ✅ |
| `test_missing_token_is_rejected` | ✅ |
| `test_sync_creates_new_outlets` | ✅ |
| `test_sync_updates_existing_outlet` | ✅ |
| `test_sync_creates_new_categories` | ✅ |
| `test_sync_updates_existing_category` | ✅ |
| `test_sync_creates_new_products` | ✅ |
| `test_sync_updates_existing_product` | ✅ |
| `test_sync_creates_product_prices` | ✅ |
| `test_price_sync_skips_if_product_not_found` | ✅ |
| `test_full_sync_payload_processes_all_entities` | ✅ |

**Total suite: 21/21 PASSED**

---

## 6. Server Deployment — 213.35.118.26

| Step | Result |
|---|---|
| `git clone https://github.com/frahmat68-beep/PiyohPOS.git /var/www/piyoh-pos` | ✅ |
| `composer install --no-dev --optimize-autoloader` | ✅ |
| `.env` configured (`APP_ENV=production`, `APP_DEBUG=false`, `MASTER_DATA_SYNC_TOKEN`) | ✅ |
| `php artisan migrate --force` — 18 migrations | ✅ |
| `php artisan optimize` — config, routes, views, filament cached | ✅ |
| `php artisan route:list` — `api/v1/sync/master-data` confirmed | ✅ |
| curl — category-only payload | ✅ synced: 1 |
| curl — full payload (outlets+categories+products+prices) | ✅ synced: 4 |
| DB verification via `tinker` | ✅ `external_id`, `source_system`, `last_synced_at` present |

---

## 7. What PiyohWeb Must Send

When calling the sync endpoint from PiyohWeb, use the following:

```bash
curl -X POST https://<POS_DOMAIN>/api/v1/sync/master-data \
  -H "Authorization: Bearer <MASTER_DATA_SYNC_TOKEN>" \
  -H "Content-Type: application/json" \
  -d @payload.json
```

The `id` field in each entity must be PiyohWeb's **integer ID** (sent as string). This becomes `external_id` in POS and is the upsert key for all future syncs.

---

## Next Steps (Phase 3)

- [ ] Set up nginx vhost for `piyoh-pos` on the server
- [ ] Configure PiyohWeb to call the sync endpoint on model save/update events
- [ ] Implement webhook signature verification (HMAC) as alternative to bearer token
- [ ] Add sync status dashboard widget in Filament
- [ ] Kitchen display system
- [ ] Payment flow
