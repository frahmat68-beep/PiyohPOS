# Sprint 4 — Order Operation Layer Report

## 1. Commit and Deployment Details

- **Commit Hash:** `3aa06ab96b31d4046ffa9847d4c2939d360b3555`
- **Deployment Result:** Successfully pulled, migrated, and cached bootstrap configurations on server `213.35.118.26` under `/var/www/piyoh-pos`.

## 2. Implemented Features

### Order Status Pipeline
Strict state transitions mapping:
- Sequence constraint: `pending -> confirmed -> preparing -> ready -> served -> completed`.
- `cancelled` is restricted from `pending` or `confirmed` states only.
- Specific timestamp tracking fields automatically populating (e.g. `confirmed_at`, `preparing_at`, `ready_at`, `served_at`, `completed_at`, `cancelled_at`).

### Order Timelines
- Added `order_timelines` table and `OrderTimeline` model.
- Automatically inserts tracking timelines with executor logs and notes whenever statuses are transitioned.

### Cashier vs Kitchen Restrictions
- **Cashier Actions:** Restricted to confirming, cancelling, serving, and completing orders.
- **Kitchen Actions:** Restricted to preparing and marking orders ready.

### Dashboard Metrics
- Cashier dashboard includes today's orders, today's paid revenue sum, pending payments count, and active table counts.
- Kitchen dashboard includes pending, preparing, and ready statuses.
- Revenue analytics dashboard widget (`RevenueReportsOverview`) reports today's and weekly revenue along with top products and categories.

### Order Detail Page
- Configured a detailed `ViewOrder` infolist displaying metadata, products, subtotal summary, payment state, and a full status timeline list.

## 3. Test Verification Result

All 31 unit & feature tests pass successfully:
- Local & server databases verify correct pipeline flow.
- Total assertions: 83.
