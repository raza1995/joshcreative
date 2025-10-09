# ShipStation Integration - Quick Start Guide

## ⚡ 5-Minute Setup

### 1. Add to .env
```bash
SHIPSTATION_API_KEY=your_api_key_here
SHIPSTATION_API_SECRET=your_api_secret_here
SHIPSTATION_CONSOLIDATE_SKUS=true
SHIPSTATION_AUTO_PUSH=false
```

### 2. Run Migrations
```bash
php artisan migrate
```

### 3. Test Connection
```bash
php artisan tinker
>>> app(\App\Services\ShipStationApiService::class)->testConnection()
```

### 4. Test Consolidation
```bash
php artisan shipstation:test-consolidation --order-number=YOUR_ORDER_NUMBER --dry-run
```

### 5. Push Order
```bash
php artisan shipstation:push-orders --order-number=YOUR_ORDER_NUMBER --sync
```

---

## 🚀 Common Commands

### Test consolidation without saving
```bash
php artisan shipstation:test-consolidation --order-number=5234567890 --dry-run
```

### Consolidate and save to DB (no push)
```bash
php artisan shipstation:test-consolidation --order-number=5234567890
```

### Push single order synchronously
```bash
php artisan shipstation:push-orders --order-number=5234567890 --sync
```

### Push orders from last 7 days
```bash
php artisan shipstation:push-orders --since="2025-10-02"
```

### Push with queue (async)
```bash
php artisan shipstation:push-orders --order-number=5234567890
# Start queue worker: php artisan queue:work --queue=shipstation
```

---

## 🧪 Test Webhook Locally

### Using cURL
```bash
curl -X POST http://localhost/shopify/webhook/orders \
  -H "Content-Type: application/json" \
  -d @tests/fixtures/shopify_order_duplicate_sku.json
```

### Expected Response
```json
{
  "message": "Webhook received, saved, and tracked."
}
```

### Check Logs
```bash
tail -f storage/logs/laravel.log | grep ShipStation
```

---

## 📊 Monitor in Database

```sql
-- View consolidated orders
SELECT 
    order_number, 
    consolidation_status, 
    shipstation_order_id, 
    pushed_at 
FROM shipstation_orders 
ORDER BY created_at DESC 
LIMIT 10;

-- View consolidated line items
SELECT 
    o.order_number,
    li.sku,
    li.quantity,
    li.unit_price,
    li.is_consolidated,
    li.original_line_count
FROM shipstation_line_items li
JOIN shipstation_orders o ON o.id = li.shipstation_order_id
WHERE o.order_number = '5234567890';

-- View sync logs (last 24 hours)
SELECT 
    action,
    status,
    message,
    items_before,
    items_after,
    created_at
FROM shipstation_sync_logs
WHERE created_at >= NOW() - INTERVAL 24 HOUR
ORDER BY created_at DESC;

-- Check for errors
SELECT * FROM shipstation_sync_logs 
WHERE status = 'failed' 
ORDER BY created_at DESC 
LIMIT 10;
```

---

## 🎛️ Enable Auto-Push

After testing, enable automatic pushing:

```bash
# In .env
SHIPSTATION_AUTO_PUSH=true
```

Now all new Shopify orders will:
1. ✅ Automatically consolidate duplicate SKUs
2. ✅ Queue for push to ShipStation
3. ✅ Appear in ShipStation dashboard within seconds

---

## 🔍 Troubleshooting

### Orders not consolidating?
```bash
# Check config
php artisan tinker
>>> config('shipstation.consolidate_skus')
// Should return: true
```

### Queue not processing?
```bash
# Check queue worker
ps aux | grep "queue:work"

# Start if not running
php artisan queue:work --queue=shipstation
```

### API errors?
```bash
# Check recent errors
php artisan tinker
>>> \App\Models\ShipStationSyncLog::recentErrors(24)->get()
```

---

## 📈 Performance Tips

1. **Use Queue Workers** - Always run queue workers in production
2. **Rate Limiting** - Default 1.5s delay between requests (handles 40/min limit)
3. **Batch Processing** - Use `--limit` flag for large date ranges
4. **Test Mode** - Use `SHIPSTATION_TEST_MODE=true` for testing without API calls

---

## 🎯 Success Checklist

- [ ] API connection successful
- [ ] Test consolidation shows correct merged SKUs
- [ ] Single order pushes successfully to ShipStation
- [ ] Order appears in ShipStation dashboard
- [ ] Packing slip shows consolidated quantities
- [ ] Queue worker running in production
- [ ] Auto-push enabled (when ready)

---

## 📞 Need Help?

1. Check `storage/logs/laravel.log`
2. Query `shipstation_sync_logs` table
3. Review `SHIPSTATION_SETUP_GUIDE.md` for detailed docs

---

**You're all set!** 🎉

