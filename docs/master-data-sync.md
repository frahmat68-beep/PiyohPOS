# Master Data Sync Architecture

This document describes the Website-POS integration architecture, where the central website serves as the single source of truth (Master) and the POS operates as the transactional consumer.

---

## 1. Role Scopes & Boundaries

### Website (PiyohWeb - Master Data System)
* **Outlets:** Creation, modification, toggling active states, branch metadata (phone, address).
* **Categories:** Managing menu categories and display order.
* **Products:** Managing master product listings (SKU, master name, base price, descriptions, and media).
* **Product Prices:** Localized branch-specific pricing overrides and stock availability toggles.

### POS (PiyohPOS - Transaction System)
* **Consumer Role:** Downloads, stores, and caches Master Data locally.
* **Master Data Sync Cache:**
  * Read-only replicas: `outlets`, `categories`, `products`, `product_prices`.
  * Managed locally via API synchronizations; no local creation or manual database edits.
* **Transactional Ownership:** Fully owns dining tables configuration, QR session codes, customer carts, orders, kitchen preparation states, and payment records.

---

## 2. Sync Mechanism

The central website triggers synchronization payloads when master records change (webhooks/REST triggers) hitting the secure API endpoint exposed by the POS.

### POS Endpoint: `POST /api/v1/sync/master-data`
* **Security:** Authenticated using standard Authorization Bearer Token.
* **Token Configuration:** Configured in `config/master-data.php` via the `MASTER_DATA_SYNC_TOKEN` env variable.
