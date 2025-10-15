# ShipStation Update Summary

## 📋 What We Update in ShipStation

### 🎯 **Overview**
Our system **ONLY UPDATES existing orders** in ShipStation. It never creates new orders. The updates focus on:
1. Line items (consolidating bundles into individual SKUs)
2. Item pricing (applying correct prices from Shopify)

---

## 🔄 **Update Process**

### **Step 1: Order Lookup**
- **Method**: Search by order number (e.g., "52944")
- **API Call**: `GET /orders?orderNumber=52944`
- **Purpose**: Verify order exists before attempting any updates

### **Step 2: Line Items Update (MINIMAL PAYLOAD)**
- **API Call**: `POST /orders/createorder`
- **What We Send** (ONLY 3 fields):
  ```json
  {
    "orderId": 154649128,      // Identifier to locate order
    "orderKey": "SHOPIFY-...", // Identifier for matching
    "items": [                 // The ONLY data we're updating
      {
        "lineItemKey": "unique-identifier",
        "sku": "REG-BLUE-1",
        "name": "Blue Razz",
        "quantity": 1,
        "unitPrice": 34.95,
        "imageUrl": "...",
        "weight": { "value": 2.4, "units": "ounces" }
      }
      // ... more items
    ]
  }
  ```
  
**93% Reduction**: We send only 3 fields instead of 43 (40 fields removed!)

---

## 📦 **Specific Updates**

### **1. Line Items (SKUs)**

#### **BEFORE Consolidation:**
```
Order #52944:
  - BUND-REG-CLASSIC-4 (qty: 1, price: $82.46)
```

#### **AFTER Consolidation:**
```
Order #52944:
  - BUND-REG-CLASSIC-4 (qty: 1, price: $82.46)  [Original bundle kept]
  - REG-BLUE-1 (qty: 1, price: $34.95)          [Individual item added]
  - REG-CITR-1 (qty: 1, price: $34.95)          [Individual item added]
  - REG-GRAP-1 (qty: 1, price: $34.95)          [Individual item added]
  - REG-RUBY-1 (qty: 1, price: $34.95)          [Individual item added]
```

### **2. Item Pricing**

For each line item, we update:
- **`unitPrice`**: Price per unit from Shopify product database
- **`quantity`**: Number of units (from bundle configuration)

**Example:**
```json
{
  "sku": "REG-BLUE-1",
  "unitPrice": 34.95,  // ← Updated from Shopify products table
  "quantity": 1
}
```

---

## 🚫 **What We DO NOT Update**

### **Order-Level Data (NOT Updated):**
- ❌ Order number
- ❌ Customer information (name, email, address)
- ❌ Shipping address
- ❌ Order status
- ❌ Shipping method
- ❌ Order totals (subtotal, tax, shipping, total)
- ❌ Payment information
- ❌ Tracking numbers
- ❌ Order dates (created, modified, shipped)
- ❌ Custom fields
- ❌ Tags
- ❌ Notes
- ❌ Gift message
- ❌ Insurance options
- ❌ Dimensions
- ❌ Weight (order-level)

### **Item-Level Data (NOT Updated):**
- ❌ Line item keys (preserved from original)
- ❌ Product IDs
- ❌ Warehouse locations
- ❌ Fulfillment SKUs
- ❌ UPC codes
- ❌ Tax amounts
- ❌ Shipping amounts
- ❌ Adjustment flags

---

## 🔍 **Detailed Update Fields**

### **Line Items Array**
```json
{
  "items": [
    {
      // ✅ UPDATED FIELDS:
      "sku": "REG-BLUE-1",                    // New SKU from bundle mapping
      "name": "Blue Razz",                    // Product name from Shopify
      "quantity": 1,                          // Quantity from bundle config
      "unitPrice": 34.95,                     // Price from Shopify products
      "imageUrl": "https://...",              // Image URL from Shopify
      "weight": {                             // Weight from Shopify
        "value": 2.4,
        "units": "ounces"
      },
      
      // ❌ PRESERVED FIELDS (not changed):
      "lineItemKey": "17064984576310",        // Original line item ID
      "productId": 14604002,                  // Original product ID
      "taxAmount": 0,                         // Original tax
      "shippingAmount": 0,                    // Original shipping
      "warehouseLocation": null,              // Original location
      "options": [],                          // Original options
      "fulfillmentSku": null,                 // Original fulfillment SKU
      "adjustment": false,                    // Original adjustment flag
      "upc": null                             // Original UPC
    }
  ]
}
```

---

## 📊 **Update Frequency**

### **When Updates Occur:**
1. **Webhook Triggered**: Real-time when ShipStation sends `ORDER_NOTIFY` event
2. **Manual Sync**: When `php artisan shipstation:sync-from-api` is run
3. **Scheduled Sync**: If configured in Laravel scheduler

### **When Updates Are SKIPPED:**
1. ❌ Order doesn't exist in ShipStation
2. ❌ Order is in `shipped` status
3. ❌ Order is in `cancelled` status
4. ❌ Order validation fails (missing shipping address, etc.)

---

## 🎯 **Bundle Consolidation Logic**

### **Bundle Mappings (from config/shipstation.php):**

```php
'BUND-REG-CLASSIC-4' => [
    'REG-BLUE-1'  => ['quantity' => 1],
    'REG-CITR-1'  => ['quantity' => 1],
    'REG-GRAP-1'  => ['quantity' => 1],
    'REG-RUBY-1'  => ['quantity' => 1],
],

'BUND-REG-TROP-4' => [
    'REG-TRMA-1'  => ['quantity' => 1],
    'REG-PICO-1'  => ['quantity' => 1],
    'REG-MEPO-1'  => ['quantity' => 1],
    'REG-COPI-1'  => ['quantity' => 1],
],

'BUND-REG-BERRY-4' => [
    'REG-BLUE-1'  => ['quantity' => 1],
    'REG-BCHRY-1' => ['quantity' => 1],
    'REG-STCH-1'  => ['quantity' => 1],
    'REG-RUBY-1'  => ['quantity' => 1],
]
```

### **Process:**
1. Detect bundle SKU (starts with `BUND-`)
2. Look up bundle components in config
3. Add individual SKUs to order
4. Apply pricing from Shopify products table
5. Keep original bundle SKU in order (for reference)

---

## 🔐 **Update Safety**

### **✅ Safe Operations:**
- Updates only affect line items and pricing
- Original order data preserved
- All changes logged to database
- Webhook logs track all updates
- Can be re-run without side effects (idempotent)

### **✅ Rollback Capability:**
- Original line items stored in `shipstation_orders.original_line_items` (JSON)
- Can restore from Shopify raw data
- Webhook logs provide complete audit trail

---

## 📝 **Update Logging**

### **Database Tables:**
1. **`shipstation_orders`**: Order metadata and consolidation status
2. **`shipstation_line_items`**: Line items after consolidation
3. **`shipstation_webhook_logs`**: Complete webhook history with payloads
4. **`shipstation_sync_logs`**: Sync operation logs

### **Log Files:**
1. **`storage/logs/shipstation-webhook-YYYY-MM-DD.log`**: Webhook events
2. **`storage/logs/shipstation-data-YYYY-MM-DD.log`**: Detailed data logs
3. **`storage/logs/pricing-YYYY-MM-DD.log`**: Pricing calculations
4. **`storage/logs/laravel.log`**: General application logs

---

## 🚀 **API Calls Made**

### **1. Check Order Exists:**
```http
GET https://ssapi.shipstation.com/orders?orderNumber=52944
Authorization: Basic [credentials]
```

### **2. Update Order (with new line items):**
```http
POST https://ssapi.shipstation.com/orders/createorder
Authorization: Basic [credentials]
Content-Type: application/json

{
  "orderNumber": "52944",
  "orderKey": "SHOPIFY-6887056802102",
  "orderDate": "2025-10-14T20:29:57",
  "orderStatus": "awaiting_shipment",
  "customerEmail": "customer@example.com",
  "billTo": { ... },
  "shipTo": { ... },
  "items": [
    // Updated line items here
  ],
  "orderTotal": 97.20,
  "amountPaid": 97.20,
  "taxAmount": 6.37,
  "shippingAmount": 8.37
}
```

**Note**: ShipStation's `createorder` endpoint uses `orderKey` for updates. If the `orderKey` exists, it updates the existing order instead of creating a new one.

---

## 🎯 **Summary**

### **What Gets Updated:**
✅ Line items (SKUs, names, quantities)  
✅ Unit prices  
✅ Images  
✅ Weights  

### **What Stays The Same:**
❌ Order metadata  
❌ Customer information  
❌ Shipping address  
❌ Order totals  
❌ Payment information  
❌ Order status  

### **Key Principles:**
1. **Update Only**: Never create new orders
2. **Minimal Changes**: Only update line items and pricing
3. **Data Integrity**: Preserve all other order data
4. **Audit Trail**: Log everything for traceability
5. **Idempotent**: Can be re-run safely

---

## 📞 **Support**

For questions about what data is being updated, check:
- **Webhook logs**: `php view_webhook_logs.php`
- **Database**: Query `shipstation_webhook_logs` table
- **Log files**: Check `storage/logs/shipstation-*.log`

