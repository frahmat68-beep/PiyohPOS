# Sprint 3 - Operational Core Report

## 1. Commit and Deployment Details

- **Commit Hash:** `3d6aceec69a7536ac944f50f362059119546f1df`
- **Deployed Path:** `/var/www/piyoh-pos` on server `213.35.118.26`

## 2. Database Migration Results

The database connection has been converted from SQLite to MySQL/MariaDB. On the production server:
- Database: `piyoh_pos`
- User: `piyoh-pos-user`
- All 24 migrations run successfully on MariaDB 10.11.14:
  - `0001_01_01_000000_create_users_table`
  - `2026_06_16_072701_create_permission_tables`
  - `2026_06_16_083536_create_payments_table`
  - `2026_06_16_083538_add_expires_at_to_table_sessions_table`
  - `2026_06_16_083542_add_payment_fields_to_orders_table`
  - `2026_06_16_083800_create_activity_log_table`
  - Spatie activity log columns successfully added.

## 3. Seeding Results

Successful seeding of:
- Outlets: `Piyoh Galaxy`, `Piyoh Bekasi`
- Tables: 20 tables per outlet
- Roles & Users:
  - `superadmin@piyohkopi.com` (super_admin)
  - `admin@piyohkopi.com` (admin)
  - `cashier@piyohkopi.com` (cashier)
  - `kitchen@piyohkopi.com` (kitchen)

## 4. Test Results

All 24 feature tests ran and passed successfully:
```bash
php artisan test
```
Result: `passed - 24 tests, 66 assertions`

### Verified Capabilities:
1. **Session Expiration:** Open table sessions automatically expire after 4 hours and are validated upon active requests.
2. **Order Status Workflow:** Seamless transitions through `pending -> confirmed -> preparing -> ready -> served -> completed/cancelled`.
3. **Multi-panel authorization:** Access to `/admin`, `/cashier`, and `/kitchen` is strictly verified based on Spatie roles and a custom `RestrictPanelAccess` middleware.
4. **Payment Processing:** Integrated cash, QRIS, card methods, which record a payment transaction and update order payment statuses to `paid`.
5. **Activity Log:** Automated event logging for key cashier, kitchen, and checkout workflows.
