# 🚀 ShipStation Test Commands Reference

## Test Any Single Order

### Basic Usage
```bash
php artisan shipstation:sync-from-api --order-id=ORDER_NUMBER
```

### Examples

**Test with Order Number (Most Common):**
```bash
php artisan shipstation:sync-from-api --order-id=52886
```

**Test with Another Order:**
```bash
php artisan shipstation:sync-from-api --order-id=52879
```

**Note:** The command searches by **order number** (the visible order number in ShipStation), not the internal ShipStation order ID.

**Dry Run (Preview Changes Without Applying):**
```bash
php artisan shipstation:sync-from-api --order-id=52886 --dry-run
```

**Force Reprocess (Even if Already Processed):**
```bash
php artisan shipstation:sync-from-api --order-id=52886 --force
```

**Combine Options:**
```bash
php artisan shipstation:sync-from-api --order-id=52886 --force --dry-run
```

---

## Batch Processing

**Process Last 60 Minutes (Default):**
```bash
php artisan shipstation:sync-from-api
```

**Process Last 3 Hours:**
```bash
php artisan shipstation:sync-from-api --minutes=180
```

**Process Last 24 Hours:**
```bash
php artisan shipstation:sync-from-api --minutes=1440
```

---

## View Logs

**Real-time Log Monitoring (Linux/Mac):**
```bash
tail -f storage/logs/laravel.log
```

**View Last 50 Lines (Windows PowerShell):**
```powershell
Get-Content storage/logs/laravel.log -Tail 50
```

**View Last 100 Lines (Linux/Mac):**
```bash
tail -n 100 storage/logs/laravel.log
```

---

## What to Look For in Logs

### Bundle SKU Removal
```
Removed bundle SKU from order:
- bundle_sku: BUND-REG-TROP-4
- order_id: 154085123
- order_number: 52886
```

### SKIO Subscription Backtracking
```
Found original subscription price for bundle component:
- component_sku: REG-BCHRY-1
- bundle_sku: BUND-REG-TROP-4
- current_order: 52886
- customer_email: customer@example.com
- original_bundle_price: 82.46
- component_price: 20.62
- source: backtracked_from_subscription
```

### Image URL Override
```
Applied image URL override for SKU in payload build:
- sku: REG-BCHRY-1
- image_url: https://cdn.shopify.com/...
```

### Order Update Success
```
Successfully updated order in ShipStation:
- shipstation_order_id: 154085123
- order_number: 52886
- items_count: 4
```

---

## Troubleshooting

### Order Not Found
```bash
Order 52886 not found in ShipStation
```
**Possible Reasons:**
- Order hasn't synced to ShipStation yet
- Order ID is incorrect
- Order is in a different status (cancelled, shipped)

### No Database Connection
```bash
SQLSTATE[HY000] [1130] Host not allowed to connect
```
**Solution:** This is normal in local environment. Use production server for testing.

### Permission Denied
```bash
permission denied: storage/logs/laravel.log
```
**Solution:**
```bash
chmod -R 775 storage/logs
```

---

## Quick Testing Workflow

1. **Find Order ID** (from ShipStation or admin panel)
2. **Run Test Command:**
   ```bash
   php artisan shipstation:sync-from-api --order-id=52886 --dry-run
   ```
3. **Review Dry Run Output** (check what will change)
4. **Apply Changes:**
   ```bash
   php artisan shipstation:sync-from-api --order-id=52886
   ```
5. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```
6. **Verify in ShipStation** (check packing slip)

---

## Features Tested

✅ **Bundle SKU Removal** - Removes BUND-* SKUs from packing slips  
✅ **SKIO Subscription Backtracking** - Finds original pricing for auto-orders  
✅ **Image URL Override** - Updates specific SKU images (REG-BCHRY-1)  
✅ **Component Pricing** - Calculates accurate pricing for bundle components  
✅ **Order Consolidation** - Merges duplicate SKUs  

---

## Support

For issues or questions, check:
- `storage/logs/laravel.log` - Application logs
- ShipStation API status page
- Database connection settings

**Perfect for testing any single order!** 🎉

