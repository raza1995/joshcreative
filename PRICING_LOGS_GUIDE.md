# 💰 ShipStation Pricing Logs Guide

## Overview

All pricing changes and ShipStation API requests are logged to a dedicated **pricing log file** for easy tracking and auditing.

---

## Log Location

```
storage/logs/pricing-YYYY-MM-DD.log
```

**Examples:**
- `storage/logs/pricing-2025-10-14.log`
- `storage/logs/pricing-2025-10-15.log`

**Retention:** 30 days (automatically rotated daily)

---

## View Pricing Logs

### Real-time Monitoring (Linux/Mac)
```bash
tail -f storage/logs/pricing-*.log
```

### View Today's Pricing Log (Windows PowerShell)
```powershell
Get-Content storage/logs/pricing-$(Get-Date -Format "yyyy-MM-dd").log -Tail 50
```

### View Specific Date
```bash
cat storage/logs/pricing-2025-10-14.log
```

### Search for Specific Order
```bash
grep "52886" storage/logs/pricing-*.log
```

### Search for Specific SKU
```bash
grep "REG-BCHRY-1" storage/logs/pricing-*.log
```

---

## Log Entry Types

### 1. PRICING UPDATE
Logged when a component's unit price is calculated/updated.

```json
{
  "timestamp": "2025-10-14 15:30:45",
  "order_number": "52886",
  "sku": "REG-BCHRY-1",
  "item_name": "Black Cherry Lemonade",
  "quantity": 1,
  "original_unit_price": 0.00,
  "calculated_unit_price": 20.62,
  "price_change": 20.62,
  "total_value_change": 20.62,
  "pricing_method": "bundle_component_backtracking",
  "customer_email": "customer@example.com"
}
```

**Key Fields:**
- `original_unit_price`: Original price from ShipStation ($0.00 for SKIO orders)
- `calculated_unit_price`: New price after backtracking ($20.62)
- `price_change`: Difference between new and old price
- `total_value_change`: Total value change (price × quantity)
- `pricing_method`: How the price was calculated

---

### 2. SHIPSTATION API REQUEST
Logged before sending order update to ShipStation.

```json
{
  "timestamp": "2025-10-14 15:30:46",
  "order_number": "52886",
  "order_key": "SHOPIFY-6886148702518",
  "items_count": 4,
  "items_detail": [
    {
      "sku": "REG-BCHRY-1",
      "name": "Black Cherry Lemonade",
      "quantity": 1,
      "unitPrice": 20.62,
      "total": 20.62
    },
    {
      "sku": "REG-WATER-1",
      "name": "Watermelon Lemonade",
      "quantity": 1,
      "unitPrice": 20.62,
      "total": 20.62
    },
    {
      "sku": "REG-STRAW-1",
      "name": "Strawberry Lemonade",
      "quantity": 1,
      "unitPrice": 20.62,
      "total": 20.62
    },
    {
      "sku": "REG-MANGO-1",
      "name": "Mango Lemonade",
      "quantity": 1,
      "unitPrice": 20.62,
      "total": 20.62
    }
  ],
  "endpoint": "https://ssapi.shipstation.com/orders/createorder"
}
```

**Key Fields:**
- `items_detail`: Complete breakdown of all items being sent
- `unitPrice`: The **exact** unit price being sent to ShipStation API
- `total`: Calculated total (quantity × unitPrice)

---

### 3. SHIPSTATION API SUCCESS
Logged when ShipStation accepts the update.

```json
{
  "timestamp": "2025-10-14 15:30:47",
  "order_number": "52886",
  "status": "success"
}
```

---

### 4. SHIPSTATION API FAILED
Logged when ShipStation rejects the update.

```json
{
  "timestamp": "2025-10-14 15:30:47",
  "order_number": "52886",
  "status": "failed",
  "error": "Invalid order data: ..."
}
```

---

## Pricing Flow Example

### Complete Log Flow for Order #52886

```
[2025-10-14 15:30:45] PRICING UPDATE
- Order: 52886
- SKU: REG-BCHRY-1 (Black Cherry Lemonade)
- Original Price: $0.00
- Calculated Price: $20.62
- Change: +$20.62
- Method: bundle_component_backtracking

[2025-10-14 15:30:45] PRICING UPDATE
- Order: 52886
- SKU: REG-WATER-1 (Watermelon Lemonade)
- Original Price: $0.00
- Calculated Price: $20.62
- Change: +$20.62

[2025-10-14 15:30:45] PRICING UPDATE
- Order: 52886
- SKU: REG-STRAW-1 (Strawberry Lemonade)
- Original Price: $0.00
- Calculated Price: $20.62
- Change: +$20.62

[2025-10-14 15:30:45] PRICING UPDATE
- Order: 52886
- SKU: REG-MANGO-1 (Mango Lemonade)
- Original Price: $0.00
- Calculated Price: $20.62
- Change: +$20.62

[2025-10-14 15:30:46] SHIPSTATION API REQUEST
- Order: 52886
- Items: 4 items
- Total Value: $82.48

[2025-10-14 15:30:47] SHIPSTATION API SUCCESS
- Order: 52886
```

---

## API Payload Structure

According to ShipStation API docs, each item in the payload has this structure:

```json
{
  "lineItemKey": null,
  "sku": "REG-BCHRY-1",
  "name": "Black Cherry Lemonade",
  "quantity": 1,
  "unitPrice": 20.62,           // ← This is what we update
  "imageUrl": "https://...",
  "taxAmount": null,
  "shippingAmount": null,
  "warehouseLocation": null,
  "options": [],
  "productId": null,
  "fulfillmentSku": "REG-BCHRY-1",
  "adjustment": false,
  "upc": null,
  "weight": {
    "value": 1,
    "units": "ounces"
  }
}
```

**Key Field:** `unitPrice` - This is the **per-unit price** that appears on packing slips.

---

## Useful Queries

### Find All Pricing Changes Today
```bash
grep "PRICING UPDATE" storage/logs/pricing-$(date +%Y-%m-%d).log
```

### Find Orders with Zero Prices Fixed
```bash
grep "original_unit_price.*0.00" storage/logs/pricing-*.log
```

### Find All Orders for a Customer
```bash
grep "customer@example.com" storage/logs/pricing-*.log
```

### Find Failed API Requests
```bash
grep "SHIPSTATION API FAILED" storage/logs/pricing-*.log
```

### Count Pricing Updates Today
```bash
grep -c "PRICING UPDATE" storage/logs/pricing-$(date +%Y-%m-%d).log
```

---

## What to Look For

### ✅ Success Pattern
1. **PRICING UPDATE** entries for each zero-price component
2. **SHIPSTATION API REQUEST** with correct `unitPrice` values
3. **SHIPSTATION API SUCCESS** confirmation

### ❌ Problem Patterns

**Zero Prices Not Updated:**
```
"original_unit_price": 0.00,
"calculated_unit_price": 0.00  ← Problem!
```
**Solution:** Check if bundle exists in customer's order history

**API Rejection:**
```
"status": "failed",
"error": "Invalid pricing data"
```
**Solution:** Check API payload format

**Missing Customer Email:**
```
"customer_email": null  ← Can't backtrack without email
```
**Solution:** Ensure orders have customer email

---

## Pricing Methods

### bundle_component_backtracking
- Searches customer's order history
- Finds most recent bundle order with proper pricing
- Divides bundle price by component count
- Used for SKIO subscription orders

---

## Audit Trail

The pricing log provides a complete audit trail showing:
- ✅ **What changed:** Original vs calculated prices
- ✅ **When changed:** Timestamp for each update
- ✅ **Why changed:** Pricing method used
- ✅ **What was sent:** Exact API payload
- ✅ **What happened:** Success or failure

This ensures complete transparency and traceability for all pricing operations!

---

## Log Maintenance

**Daily Rotation:** Logs automatically rotate daily  
**Retention:** Last 30 days kept automatically  
**Manual Cleanup:** Older logs can be safely deleted if needed

```bash
# Remove logs older than 30 days
find storage/logs/pricing-*.log -mtime +30 -delete
```

---

**Perfect for debugging, auditing, and ensuring pricing accuracy!** 💰🎯

