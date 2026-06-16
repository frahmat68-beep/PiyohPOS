# Role Separation & Permissions Matrix

This document defines user roles, access scope boundaries, and permissions for the PiyohPOS transaction system.

---

## 1. Role Scope Definitions

* **`super_admin`**
  * Scope: Global (all outlets).
  * Capabilities: Access to system-wide settings, logs, table layouts for all outlets, global reports, user provisioning.
* **`admin`**
  * Scope: Outlet-scoped (works on the assigned active outlet).
  * Capabilities: Manage local dining tables, generate/regenerate QR codes, view local outlet reports.
* **`cashier`**
  * Scope: Outlet-scoped.
  * Capabilities: View local dining tables status, approve pending orders, accept payments (cash, card, QRIS), close table sessions.
* **`kitchen`**
  * Scope: Outlet-scoped.
  * Capabilities: View order prep queues, mark order item statuses (Preparing, Ready, Served).

---

## 2. Permissions Matrix

| Module / Action | super_admin | admin | cashier | kitchen |
| :--- | :---: | :---: | :---: | :---: |
| **Manage Dining Tables** | Yes | Yes | No | No |
| **Reset Table QR Token** | Yes | Yes | No | No |
| **View local sales reports** | Yes | Yes | No | No |
| **View global sales reports**| Yes | No | No | No |
| **Approve / Confirm Orders** | Yes | Yes | Yes | No |
| **Mark order status as Cooking**| Yes | Yes | Yes | Yes |
| **Mark order status as Ready** | Yes | Yes | Yes | Yes |
| **Process Payment / Close Session**| Yes | Yes | Yes | No |
| **Manage Users & Role Assignment**| Yes | No | No | No |
