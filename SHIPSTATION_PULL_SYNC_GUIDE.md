# ShipStation Pull & Consolidate Sync

## 🎯 Overview

This is a **separate, independent system** from the webhook-based push system. It works by:

1. **ShipStation auto-syncs** orders from Shopify (hourly)
2. **We pull** new orders from ShipStation (every 10 min)
3. **Check** if order has duplicate SKUs
4. **Consolidate** if needed
5. **Update** back to ShipStation
6. **Track** processed orders (don't re-process)

---

## 📦 Setup

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `shipstation_processed_orders` table to track what we've already processed.

### Step 2: Test Pull

```bash
# Pull orders from last 60 minutes (default)
php artisan shipstation:sync-from-api

# Pull from last 2 hours
php artisan shipstation:sync-from-api --minutes=120

# Dry run (see what would happen)
php artisan shipstation:sync-from-api --dry-run

# Force reprocess already processed orders
php artisan shipstation:sync-from-api --force
```

### Step 3: Schedule It (Run Every 10 Minutes)

Edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Pull from ShipStation and consolidate every 10 minutes
    $schedule->command('shipstation:sync-from-api --minutes=15')
             ->everyTenMinutes()
             ->withoutOverlapping()
             ->runInBackground();
}
```

Make sure your scheduler is running:

```bash
# Add this to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔍 How It Works

### Pull Orders

```php
$pullService = app(ShipStationPullService::class);

// Pull orders modified in last 60 minutes
$orders = $pullService->pullNewOrders(60);
```

### Check for Duplicates

```php
if ($pullService->hasDuplicateSKUs($order)) {
    // Order has duplicate SKUs
}
```

### Consolidate Items

```php
$originalItems = $order['items'];
$consolidatedItems = $pullService->consolidateItems($originalItems);

// Update order
$order['items'] = $consolidatedItems;
```

### Track Processed Orders

```php
use App\Models\ShipStationProcessedOrder;

// Check if already processed
if (ShipStationProcessedOrder::wasProcessed($orderId)) {
    // Skip
}

// Mark as processed
ShipStationProcessedOrder::markAsProcessed(
    $orderId,
    $orderNumber,
    $orderKey,
    'consolidated', // or 'skipped_no_duplicates', 'skipped_shipped', 'failed'
    $itemsBefore,
    $itemsAfter,
    'Successfully consolidated',
    ['reduction' => 2] // optional metadata
);
```

---

## 📊 Monitoring

### View Processed Orders

```php
// Get all consolidated orders
$consolidated = ShipStationProcessedOrder::where('action', 'consolidated')->get();

// Get failed orders
$failed = ShipStationProcessedOrder::where('action', 'failed')->get();

// Get orders processed today
$today = ShipStationProcessedOrder::whereDate('processed_at', today())->get();
```

### Check Logs

```bash
tail -f storage/logs/laravel.log | grep "ShipStation"
```

---

## 🚀 Testing

### Test with Specific Order

```bash
# First, run a dry-run to see what would happen
php artisan shipstation:sync-from-api --dry-run --minutes=1440

# If it looks good, run for real
php artisan shipstation:sync-from-api --minutes=1440
```

### Force Reprocess Order

```bash
# Delete from processed table
php artisan tinker
>>> ShipStationProcessedOrder::where('order_number', '6875259011382')->delete();
>>> exit

# Then sync again
php artisan shipstation:sync-from-api --minutes=1440
```

---

## ⚙️ Configuration

All existing ShipStation config still applies from `config/shipstation.php`.

### Recommended Settings for Pull Sync

```env
# Keep webhook processing OFF for this approach
SHIPSTATION_AUTO_PUSH=false

# Or, run both systems (not recommended but possible)
SHIPSTATION_AUTO_PUSH=true  # Webhook-based
# AND schedule the pull sync command
```

---

## 🔄 Operating Modes

### Mode 1: Pull-Based Only (RECOMMENDED)

```env
SHIPSTATION_AUTO_PUSH=false
```

1. Let ShipStation auto-sync from Shopify
2. Run `shipstation:sync-from-api` every 10 minutes
3. We consolidate and update orders back to ShipStation

**Pros:**
- Simpler
- ShipStation is source of truth
- No webhook complexity

**Cons:**
- Slight delay (up to 10 minutes)

---

### Mode 2: Webhook-Based Only (OLD)

```env
SHIPSTATION_AUTO_PUSH=true
```

1. Shopify webhook triggers on order create
2. We consolidate immediately
3. Push to ShipStation

**Pros:**
- Real-time
- Orders arrive pre-consolidated

**Cons:**
- ShipStation auto-sync might create duplicates
- More complex

---

### Mode 3: Hybrid (NOT RECOMMENDED)

Both systems running. Can cause conflicts.

---

## 📈 Performance

- **API Calls:** 1 per sync run + 1 per order needing consolidation
- **Rate Limits:** ShipStation allows 40 calls/minute
- **Batch Size:** Pulls 100 orders per sync (can be adjusted)

---

## 🐛 Troubleshooting

### Orders Not Being Pulled

```bash
# Check if ShipStation API is working
php artisan shipstation:test-connection

# Check last modified time
php artisan shipstation:sync-from-api --minutes=1440 --dry-run
```

### Orders Not Being Consolidated

```bash
# Check if duplicates exist
php artisan tinker
>>> $service = app(\App\Services\ShipStationPullService::class);
>>> $order = $service->getOrder('119606579'); // ShipStation order ID
>>> $service->hasDuplicateSKUs($order);
```

### Orders Being Reprocessed

```bash
# Check processed table
php artisan tinker
>>> \App\Models\ShipStationProcessedOrder::where('order_number', 'ORDER_NUM')->get();
```

---

## 📋 Summary Table

| Feature | Pull-Based (NEW) | Webhook-Based (OLD) |
|---------|------------------|---------------------|
| Source | ShipStation | Shopify |
| Timing | Every 10 min | Real-time |
| Complexity | Low | Medium |
| Duplicates | Handled | Can occur |
| Recommended | ✅ Yes | ❌ No (if using ShipStation auto-sync) |

---

## ✅ Next Steps

1. Run migration
2. Test with: `php artisan shipstation:sync-from-api --dry-run`
3. If good: Run for real
4. Schedule it in `Kernel.php`
5. Monitor logs and `shipstation_processed_orders` table
6. Disable webhook-based push if not needed

