# Sprint 2 Foundation Report - PiyohPOS QR Ordering

This document details the features and architecture implemented during Sprint 2 of the PiyohPOS project, focusing on the customer QR ordering flow.

---

## 1. Table Sessions Schema (`table_sessions`)

Stores the active dining sessions created when a customer scans a table's QR code:
- **`id`** (BIGINT, PK, Auto Increment)
- **`table_id`** (BIGINT, FK -> `tables.id`, Cascade on Delete)
- **`session_code`** (VARCHAR(64), Unique) - secure session identifier
- **`status`** (VARCHAR(20), Default: 'open') - e.g., 'open', 'closed'
- **`opened_at`** (Timestamp) - session initiation time
- **`closed_at`** (Timestamp, Nullable) - session closure time
- **`created_at` / `updated_at`** (Timestamp)

---

## 2. Table QR Tokens (`tables` table update)

Added `qr_token` column to the `tables` table:
- Type: `VARCHAR(64)`, Unique, Indexed, Nullable.
- Auto-generation: Handled via the `Table` model's `creating` boot event, assigning a unique random 32-character token. Also fully populated in `DatabaseSeeder.php` when creating initial tables.

---

## 3. Customer Ordering Routes

Registered in `routes/web.php` under the `qr.session` middleware constraint where appropriate:
- **GET `/scan/{token}`**: Validates the table QR token, starts a new open `table_session`, stores the code/ID in the PHP session, and redirects to `/menu`.
- **GET `/menu`**: Lists menu categories and products filtered for active status. Resolves custom pricing/availability overrides for the table's outlet.
- **GET `/cart`**: Shows cart products, quantities, notes, individual item prices, subtotal, tax (10%), service charge (5%), and the final grand total.
- **POST `/cart/add`**: Adds product items, quantities, and customer notes to the cart.
- **POST `/checkout`**: Creates the `Order` and `OrderItem` records under database transactions, clears the session cart, and presents a checkout success view.

---

## 4. Cart Service (`CartService`)

Encapsulates shopping cart logic inside the user's session (`qr_cart` key):
- Tracks product ID, quantity, and notes.
- Resolves outlet-specific prices from `product_prices` (based on active table session) falling back to master `products.base_price`.
- Automatically calculates item subtotals and grand totals.

---

## 5. Order Number Generator (`OrderService`)

Generates sequential order numbers scoped by outlet prefix:
- **Format**: `{OUTLET_PREFIX}-{YYYYMMDD}-{SEQUENCE}`
- **Prefixes**:
  - `GLX` for Piyoh Galaxy
  - `BKS` for Piyoh Bekasi
  - `OUT` (Fallback)
- **Sequence**: Pads the count of orders placed at that outlet today + 1 to 3 digits (`001`, `002`, etc.).

---

## 6. Verification & Test Results

Created a feature test suite: [QrOrderingTest.php](file:///Users/kiki/Documents/Web%20Develop/Piyoh/QR/tests/Feature/QrOrderingTest.php)

Ran `php artisan test` successfully:
```text
PHPUnit 12.5.30 by Sebastian Bergmann and contributors.

.......                                                             7 / 7 (100%)

Time: 00:01.029, Memory: 32.00 MB

OK (7 tests, 25 assertions)
```
Tests validated:
1. Denying menu access without a scanned table session.
2. Opening a session and redirecting on scan of a valid QR token.
3. Rendering the menu correctly for active session.
4. Adding items to cart.
5. Flowing checkout to create order records, generating correct order numbers (e.g. `GLX-YYYYMMDD-001`), saving order items, and clearing the session.
