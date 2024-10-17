# cs-cart-sync-odoo

testv 

# CS-Cart and Odoo Synchronization

This project integrates CS-Cart and Odoo for syncing product categories and customer data using webhooks. It consists of two key components:

## 1. `webhook_addon` (CS-Cart Add-on)
This add-on handles webhook requests in CS-Cart.

### Installation:
- Upload the `webhook_addon` folder to `/app/add-ons/` in your CS-Cart directory.
- In CS-Cart Admin Panel, go to Add-ons > Manage Add-ons.
- Locate `webhook_addon`, click **Install**, and **Activate**.
  
## 2. `webhook_product` (Bridge Between Odoo and CS-Cart)
This is the core system that processes data between Odoo and CS-Cart.

### Setup:
- Host the `webhook_product` folder on a server.
- Save the server URL and use it in both CS-Cart and Odoo webhook configurations.

## Webhook Configuration:
-In CS-Cart: Set up webhooks for events like product creation, updates, and customer changes. Provide the hosted URL of `webhook_product`.
-In Odoo: Similarly, set webhooks to the same URL for syncing relevant data.
