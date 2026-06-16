# PiyohPOS: QR Ordering Architecture

This document outlines the technical design for the QR-based customer ordering system. To maintain scalability and data integrity, no database migrations or models have been created yet.

---

## 1. Table Management
Each physical dining table is registered in the database under a `tables` schema:
- **`id`**: Unique identifier.
- **`outlet_id`**: Identifies which branch (e.g., Galaxy, Bekasi) the table belongs to.
- **`number`**: String label (e.g., "T-01").
- **`token`**: Unique, cryptographically secure random token (refreshed periodically or when a new customer session begins) to prevent URL guessing.

## 2. QR Generation
- Each table is associated with a QR URL structure:
  `https://app.piyohkopi.com/order/{outlet_slug}/{table_number}?token={token}`
- The backend generates these codes on-demand using a QR generation library (e.g., `chillerlan/php-qrcode` which was installed as a sub-dependency).
- Admins can print high-resolution stickers with custom branding directly from the Admin Panel.

## 3. Customer Session
- When a customer scans the QR code:
  1. The system validates the `token` in the URL query parameters.
  2. If valid, a temporary session is established on the customer's device (stored in Cookie/Session/Local Storage) containing the `table_id` and `token`.
  3. This binds all subsequent operations to that specific physical table.
  4. The session is closed/expired once the cashier processes the checkout and marks the table as paid/clear.

## 4. Cart Structure
- The customer's cart is maintained locally or in database sessions:
  - **Local Cart**: Managed on the client side (Local Storage) for fast interactions, sent to the server only when submitting.
  - **Server-side Temporary Cart**: Stored in MariaDB cache/database to allow multi-device sharing (e.g., if multiple guests at the same table add items to a shared cart).
- Each cart item records:
  - Product ID
  - Quantity
  - Selected variant/modifiers (e.g., Extra shot, less sugar)
  - Custom remarks/notes (e.g., "ice separate")

## 5. Order Flow
1. **Selection & Cart**: Customer selects items, configures options, and clicks "Pesan" (Submit).
2. **Persistence**: The order is saved as a `pending` state in the `orders` and `order_items` tables.
3. **Real-time Dispatch**: The system triggers a Laravel Event broadcasted to the corresponding Cashier panel.

## 6. Kitchen Flow
1. Once an order is approved by the cashier, it is marked as `in_kitchen`.
2. The order is automatically pushed to the Kitchen Display System (KDS).
3. Kitchen staff can group items by category (e.g., Drinks/Food) or view them chronologically.
4. Once completed, the kitchen updates the status to `ready_to_serve`.

## 7. Cashier Flow
1. Cashier sees the order on the POS.
2. Cashier can append manual items (e.g., customer ordered directly at the counter later).
3. Upon checkout, the cashier initiates the payment interface, inputs payment method (Cash, QRIS, Card), processes payment, prints receipt, and marks the table session as closed/available.

## 8. Multi Outlet Flow
- Table tables and orders include `outlet_id`.
- Livewire components (KDS and POS) are scoped by `Session::get('active_outlet_id')` based on the staff member's profile.
- Customers can only see items available in their specific outlet's inventory.

## 9. Future Accurate Integration
To integrate with **Accurate ERP** in the future:
- **Product Mapping**: Store Accurate `item_id` / SKU mapping in the `products` table.
- **Sales Invoices**: Trigger an API job to send completed orders to Accurate's endpoint (`/api/sales-invoice`) to sync sales and decrement inventory levels automatically.
- **Error Handling**: Store sync state on orders (`accurate_sync_status`, `accurate_sync_error`) with a retry job dispatcher.
