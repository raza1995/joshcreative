# ShipStation SKU Consolidation Middleware - Setup Guide

## 🎯 Overview

This middleware automatically consolidates duplicate SKUs in Shopify orders before pushing them to ShipStation, ensuring accurate packing slips and preventing warehouse picking errors.

---

## 📋 Prerequisites

- Laravel application with existing Shopify integration
- ShipStation account with API access
- Queue worker running (for async processing)
- PHP 8.1+ with Composer

---

## 🔧 Installation Steps

### 1. Add Environment Variables

Copy the contents of `.env.shipstation.example` to your `.env` file:

```bash
# Get your API credentials from: https://ss.shipstation.com/#/settings/api
SHIPSTATION_API_KEY=your_api_key_here
SHIPSTATION_API_SECRET=your_api_secret_here
SHIPSTATION_BASE_URL=https://ssapi.shipstation.com

# Start with these settings for testing
SHIPSTATION_AUTO_PUSH=false
SHIPSTATION_CONSOLIDATE_SKUS=true
SHIPSTATION_TEST_MODE=false
SHIPSTATION_PRICE_STRATEGY=weighted_average
```

### 2. Run Database Migrations

```bash
php artisan migrate
```

This will create 3 new tables:
- `shipstation_orders` - Consolidated orders ready for ShipStation
- `shipstation_line_items` - Consolidated line items (merged SKUs)
- `shipstation_sync_logs` - Audit trail of all transformations

### 3. Test API Connection

```bash
php artisan tinker
```

```php
$api = app(\App\Services\ShipStationApiService::class);
$result = $api->testConnection();
dd($result); // Should return ['success' => true, 'message' => 'Connection successful']
```

### 4. Test Consolidation (Dry-Run)

Find a recent order with duplicate SKUs:

```bash
php artisan shipstation:test-consolidation --order-number=5234567890 --dry-run
```

Expected output:
```
🧪 Testing SKU Consolidation Logic

📦 Order: 5234567890
📅 Date: 2025-10-09 15:30:00
👤 Customer: John Doe

📋 Original Line Items: 4

BEFORE CONSOLIDATION:
+---+----------+---------------------------+-----+--------+----------+
| # | SKU      | Product                   | Qty | Price  | Discount |
+---+----------+---------------------------+-----+--------+----------+
| 1 | ABC123   | Mushroom Coffee (30-day)  | 1   | $50.00 | $5.00    |
| 2 | ABC123   | Mushroom Coffee (30-day)  | 2   | $48.00 | $0.00    |
| 3 | XYZ789   | Immunity Blend            | 1   | $35.00 | $0.00    |
| 4 | ABC123   | Mushroom Coffee (30-day)  | 1   | $50.00 | $0.00    |
+---+----------+---------------------------+-----+--------+----------+

🔍 DRY RUN MODE - No data will be saved

AFTER CONSOLIDATION (SIMULATED):
+---+----------+---------------------------+-----------+-----------+-------------------+
| # | SKU      | Product                   | Total Qty | Avg Price | Consolidated      |
+---+----------+---------------------------+-----------+-----------+-------------------+
| 1 | ABC123   | Mushroom Coffee (30-day)  | 4         | $49.00    | ✅ Yes (3)       |
| 2 | XYZ789   | Immunity Blend            | 1         | $35.00    | No                |
+---+----------+---------------------------+-----------+-----------+-------------------+

📊 CONSOLIDATION SUMMARY:
+-----------------------+-------+
| Metric                | Value |
+-----------------------+-------+
| Original Items        | 4     |
| Consolidated Items    | 2     |
| Reduction             | 2     |
+-----------------------+-------+
```

---

## 🚀 Usage

### Method 1: Automatic (Webhook Trigger)

Once configured, orders are automatically consolidated when Shopify webhooks fire:

1. Set `SHIPSTATION_CONSOLIDATE_SKUS=true`
2. (Optional) Set `SHIPSTATION_AUTO_PUSH=true` to enable automatic push to ShipStation
3. New Shopify orders will trigger consolidation automatically

### Method 2: Manual Processing

#### Process Specific Order
```bash
php artisan shipstation:push-orders --order-number=5234567890
```

#### Process Orders Since Date
```bash
php artisan shipstation:push-orders --since="2025-10-01"
```

#### Process with Custom Limit
```bash
php artisan shipstation:push-orders --since="2025-10-01" --limit=50
```

#### Synchronous Push (No Queue)
```bash
php artisan shipstation:push-orders --order-number=5234567890 --sync
```

#### Force Re-consolidation
```bash
php artisan shipstation:push-orders --order-number=5234567890 --force
```

---

## 📊 Monitoring & Logs

### View Sync Logs

```php
use App\Models\ShipStationSyncLog;

// Recent errors
$errors = ShipStationSyncLog::recentErrors(24)->get();

// All consolidations today
$logs = ShipStationSyncLog::where('action', 'consolidate')
    ->whereDate('created_at', today())
    ->get();
```

### Check Order Status

```php
use App\Models\ShipStationOrder;

// Ready to push
$ready = ShipStationOrder::readyToPush()->get();

// Failed orders
$failed = ShipStationOrder::failed()->get();

// Specific order
$order = ShipStationOrder::where('order_number', '5234567890')->first();
echo $order->consolidation_status; // pending, consolidated, pushed, failed
```

### View Consolidated Line Items

```php
$order = ShipStationOrder::with('lineItems')->find(1);

foreach ($order->lineItems as $item) {
    if ($item->is_consolidated) {
        echo $item->getConsolidationSummary();
        // Output: Merged 3 line items (Total Qty: 4, Avg Price: $49.00)
    }
}
```

---

## 🔄 Queue Worker

For async processing, ensure your queue worker is running:

```bash
# Start queue worker
php artisan queue:work --queue=shipstation

# Or use Supervisor (recommended for production)
# See: config/supervisor/shipstation-worker.conf
```

---

## 🧪 Testing Workflow

### Phase 1: Test Consolidation Logic
```bash
# 1. Test dry-run
php artisan shipstation:test-consolidation --order-number=YOUR_ORDER --dry-run

# 2. Test actual consolidation (saves to DB but doesn't push)
php artisan shipstation:test-consolidation --order-number=YOUR_ORDER
```

### Phase 2: Test API Push (Test Mode)
```bash
# 1. Enable test mode
# Set: SHIPSTATION_TEST_MODE=true

# 2. Push order (will simulate API call)
php artisan shipstation:push-orders --order-number=YOUR_ORDER --sync
```

### Phase 3: Test Live API Push
```bash
# 1. Disable test mode
# Set: SHIPSTATION_TEST_MODE=false

# 2. Push single order
php artisan shipstation:push-orders --order-number=YOUR_ORDER --sync

# 3. Verify in ShipStation dashboard
# https://ship.shipstation.com/#/orders/awaiting-shipment
```

### Phase 4: Enable Auto-Push
```bash
# Set in .env
SHIPSTATION_AUTO_PUSH=true

# New orders will now automatically consolidate and push to ShipStation
```

---

## 🎛️ Configuration Options

### Price Consolidation Strategies

When merging duplicate SKUs with different prices, you can choose:

```bash
# Weighted Average (default) - (qty1*price1 + qty2*price2) / total_qty
SHIPSTATION_PRICE_STRATEGY=weighted_average

# Use lowest price
SHIPSTATION_PRICE_STRATEGY=lowest

# Use highest price
SHIPSTATION_PRICE_STRATEGY=highest

# Use first occurrence price
SHIPSTATION_PRICE_STRATEGY=first
```

### Validation Rules

Edit `config/shipstation.php`:

```php
'validation' => [
    'require_shipping_address' => true,
    'require_line_items' => true,
    'minimum_order_total' => 0, // Skip orders below this amount
],
```

---

## 📈 Performance Considerations

### Rate Limiting
ShipStation API allows **40 requests per minute**. The middleware handles this automatically:

```bash
SHIPSTATION_RATE_LIMIT=40
SHIPSTATION_REQUEST_DELAY_MS=1500  # 1.5s delay between requests
```

### Queue Processing
- Orders are queued for async processing by default
- Consolidation: **O(n)** where n = line items
- API push: **O(1)** per order
- Use `--sync` flag only for testing/debugging

### Database Indexing
The migrations include proper indexes on:
- `order_number`
- `order_key`
- `consolidation_status`
- `sku`

---

## 🔍 Troubleshooting

### Issue: Orders not consolidating automatically

**Check:**
1. `SHIPSTATION_CONSOLIDATE_SKUS=true` in `.env`
2. Webhook is firing (check `storage/logs/laravel.log`)
3. Order has `raw_json` field populated

**Debug:**
```bash
tail -f storage/logs/laravel.log | grep ShipStation
```

### Issue: API push failing

**Check:**
1. API credentials are correct
2. Test connection: `$api->testConnection()`
3. Check sync logs for error details

**Debug:**
```php
$logs = \App\Models\ShipStationSyncLog::recentErrors(24)->get();
foreach ($logs as $log) {
    echo $log->error_message . "\n";
}
```

### Issue: Queue jobs not processing

**Check:**
1. Queue worker is running: `ps aux | grep queue:work`
2. Check failed jobs: `php artisan queue:failed`

**Debug:**
```bash
# Retry failed jobs
php artisan queue:retry all

# Clear and restart queue
php artisan queue:restart
php artisan queue:work --queue=shipstation
```

---

## 🔐 Security Notes

- Store API credentials in `.env` (never commit)
- Use HTTPS for webhook endpoints
- Validate Shopify webhook signatures (already implemented)
- Restrict database access to sync logs

---

## 📞 Support

For issues or questions:
1. Check `storage/logs/laravel.log`
2. Review `shipstation_sync_logs` table
3. Enable verbose logging: `SHIPSTATION_LOG_API_RESPONSES=true`

---

## 🎉 Success Criteria

✅ Orders consolidate duplicate SKUs automatically  
✅ Packing slips show correct quantities in ShipStation  
✅ API calls respect rate limits  
✅ Failed orders are logged and retryable  
✅ Audit trail exists for all transformations  

---

**Ready to deploy!** 🚀

1. Shopify Order Created (#12345)
   ↓
2. Webhook fires → ShopifyWebhookController
   ↓
3. Save to shopify_orders table
   ↓
4. Consolidate duplicate SKUs
   ↓
5. Save to shipstation_orders + shipstation_line_items
   ↓
6. Queue PushOrderToShipStationJob (immediate)
   ↓
7. Push to ShipStation API
   ↓
8. Order appears in ShipStation (consolidated) ✅