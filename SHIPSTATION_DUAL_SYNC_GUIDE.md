# ShipStation Dual-Sync Setup Guide

## 🎯 The Scenario

You have **two systems** syncing orders to ShipStation:

1. **ShipStation Auto-Sync** (every hour) → Pulls from Shopify → **Unconsolidated SKUs**
2. **Our Middleware** (webhook/real-time) → Consolidates duplicate SKUs → **Consolidated SKUs**

---

## ⚠️ The Problem

### Race Condition
```
Shopify: Order #12345 created with duplicate SKUs
    ↓
    ├─ ShipStation auto-sync (hourly) → Creates order with duplicates ❌
    │
    └─ Our webhook → Consolidates → Tries to push → CONFLICT!
```

### What Can Go Wrong

1. **Duplicate orders** in ShipStation
2. **Overwriting** - ShipStation sync overwrites our consolidated version
3. **Timing issues** - Order ships before consolidation

---

## ✅ Recommended Solutions

### **Option 1: Disable ShipStation Auto-Sync (BEST)**

#### Setup
1. Go to ShipStation → **Settings** → **Stores**
2. Find your **Shopify store**
3. Click **Edit Settings**
4. **Disable** automatic order import
5. Save

#### Configuration in `.env`
```bash
# Our system is the ONLY source
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_SYNC_STRATEGY=update_if_exists
SHIPSTATION_WEBHOOK_DELAY_MINUTES=0  # Immediate
SHIPSTATION_ONLY_UPDATE_EXISTING=false
```

#### Pros
✅ Single source of truth  
✅ All orders have consolidated SKUs  
✅ Real-time sync (not hourly)  
✅ No race conditions  
✅ No duplicates  

#### Cons
❌ Depends on our webhook reliability  
❌ Need good error handling  

---

### **Option 2: Let ShipStation Sync, We Update Later (CURRENT)**

#### Setup
1. **Keep** ShipStation auto-sync enabled (every hour)
2. Our middleware waits, then updates with consolidated SKUs

#### Configuration in `.env`
```bash
# Let ShipStation create, we update after
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_SYNC_STRATEGY=update_if_exists
SHIPSTATION_WEBHOOK_DELAY_MINUTES=65  # Wait for hourly sync
SHIPSTATION_UPDATE_EXISTING=true
SHIPSTATION_ONLY_UPDATE_EXISTING=false  # Create if not exists yet
```

#### How It Works
```
T+0min:  Shopify order created
         ↓
T+0min:  Our webhook → Consolidate → Queue job (delayed 65 min)
         ↓
T+60min: ShipStation auto-sync → Creates order (unconsolidated)
         ↓
T+65min: Our job runs → Update order with consolidated SKUs ✅
```

#### Pros
✅ ShipStation is primary source (reliable)  
✅ Fallback if webhook fails  
✅ We enhance existing orders  

#### Cons
❌ Orders exist with duplicates for 1+ hour  
❌ If order ships within that hour, wrong quantities  
❌ Delay before consolidation  

---

### **Option 3: Only Update (Never Create)**

#### Setup
1. **Keep** ShipStation auto-sync enabled
2. Our middleware **only updates** existing orders
3. Never creates new orders

#### Configuration in `.env`
```bash
# Only update what ShipStation already created
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_SYNC_STRATEGY=update_if_exists
SHIPSTATION_WEBHOOK_DELAY_MINUTES=65
SHIPSTATION_UPDATE_EXISTING=true
SHIPSTATION_ONLY_UPDATE_EXISTING=true  # ← Key setting
```

#### How It Works
```
T+0min:  Shopify order created
         ↓
T+0min:  Our webhook → Consolidate → Queue job (delayed 65 min)
         ↓
T+60min: ShipStation auto-sync → Creates order
         ↓
T+65min: Our job runs → Check if exists → UPDATE ✅
         (If not exists yet → Skip, will retry later)
```

#### Pros
✅ No risk of duplicate orders  
✅ ShipStation controls order creation  
✅ We just enhance with consolidation  

#### Cons
❌ Orders not in ShipStation yet are skipped  
❌ Need retry mechanism  

---

## 📋 Configuration Comparison

| Setting | Option 1 (Disable Auto-Sync) | Option 2 (Delayed Update) | Option 3 (Only Update) |
|---------|------------------------------|---------------------------|------------------------|
| ShipStation Auto-Sync | ❌ Disabled | ✅ Enabled | ✅ Enabled |
| `SHIPSTATION_AUTO_PUSH` | `true` | `true` | `true` |
| `SHIPSTATION_WEBHOOK_DELAY_MINUTES` | `0` | `65` | `65` |
| `SHIPSTATION_ONLY_UPDATE_EXISTING` | `false` | `false` | `true` |
| When Orders Synced | Immediately | 65+ min delay | 65+ min delay |
| Duplicate Risk | None | Low | None |
| Consolidation Speed | Immediate | 1+ hour | 1+ hour |

---

## 🎯 My Recommendation

### For Production: **Option 1 (Disable Auto-Sync)**

**Why:**
- Fastest consolidation (immediate)
- No duplicate orders
- No race conditions
- Packing slips always correct

**Setup:**
```bash
# .env
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_SYNC_STRATEGY=update_if_exists
SHIPSTATION_WEBHOOK_DELAY_MINUTES=0
SHIPSTATION_ONLY_UPDATE_EXISTING=false
SHIPSTATION_UPDATE_EXISTING=true  # Still update if order exists

# In ShipStation
Disable: Settings → Stores → [Your Shopify] → Auto Import: OFF
```

**Fallback Plan:**
- If webhook fails, manually push: `php artisan shipstation:push-orders --since="2025-10-09"`
- Monitor logs: `tail -f storage/logs/laravel.log | grep ShipStation`
- Set up alerts for failed jobs

---

### For Testing/Transition: **Option 2 (Delayed Update)**

**Use this while:**
- Testing the middleware
- Building confidence in the system
- Want ShipStation as backup

**Setup:**
```bash
# .env
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_WEBHOOK_DELAY_MINUTES=65  # After hourly sync
SHIPSTATION_ONLY_UPDATE_EXISTING=false
```

**After 1-2 weeks of successful operation:**
→ Switch to Option 1 (disable auto-sync)

---

## 🔍 How to Check Current Setup

### Check if Order Was Auto-Synced or Created by Us

```bash
php artisan tinker
```

```php
// Check in our database
$order = \App\Models\ShipStationOrder::where('order_number', '12345')->first();

if ($order) {
    echo "Created by us at: " . $order->created_at . "\n";
    echo "Pushed at: " . $order->pushed_at . "\n";
    echo "Status: " . $order->consolidation_status . "\n";
}

// Check in ShipStation
$api = app(\App\Services\ShipStationApiService::class);
$ssOrder = $api->getOrderByKey('SHOPIFY-12345');

if ($ssOrder) {
    echo "In ShipStation: " . $ssOrder['orderId'] . "\n";
    echo "Created: " . $ssOrder['createDate'] . "\n";
    echo "Modified: " . $ssOrder['modifyDate'] . "\n";
}
```

---

## 📊 Monitoring

### Check Sync Status

```sql
-- Orders created by us
SELECT 
    order_number,
    consolidation_status,
    pushed_at,
    created_at
FROM shipstation_orders
WHERE pushed_at IS NOT NULL
ORDER BY created_at DESC
LIMIT 10;

-- Check actions taken
SELECT 
    action,
    COUNT(*) as count,
    DATE(created_at) as date
FROM shipstation_sync_logs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY action, DATE(created_at)
ORDER BY date DESC, action;
```

### Check for Conflicts

```sql
-- Orders that were updated (not created)
SELECT *
FROM shipstation_sync_logs
WHERE action = 'update'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;
```

---

## 🚀 Migration Plan

### Week 1: Testing with Option 2
```bash
# .env - Delayed update mode
SHIPSTATION_AUTO_PUSH=true
SHIPSTATION_WEBHOOK_DELAY_MINUTES=65
SHIPSTATION_ONLY_UPDATE_EXISTING=false
```

**Monitor:**
- Are orders being updated correctly?
- Any failed updates?
- Consolidation working as expected?

### Week 2-3: Build Confidence
```bash
# Continue monitoring
tail -f storage/logs/laravel.log | grep ShipStation
```

**Check:**
- Update success rate > 95%
- No missed orders
- Packing slips showing correct quantities

### Week 4: Switch to Option 1
```bash
# .env - Immediate mode
SHIPSTATION_WEBHOOK_DELAY_MINUTES=0
```

**Then:**
1. Disable ShipStation auto-sync
2. Test a few orders
3. Monitor closely for 2-3 days
4. Full production

---

## ⚠️ Common Issues

### Issue 1: Order in ShipStation but not in our DB
**Cause:** ShipStation synced before our webhook ran  
**Solution:** Use Option 2 (delayed update) or Option 3 (only update)

### Issue 2: Duplicate orders in ShipStation
**Cause:** Both systems creating orders  
**Solution:** Use Option 1 (disable auto-sync) or Option 3 (only update)

### Issue 3: Updates not applying
**Cause:** ShipStation overwrites after we update  
**Solution:** Disable auto-sync (Option 1) or increase delay

---

## 📞 Quick Decision Guide

**Question 1:** Are orders time-sensitive (ship within 1 hour)?
- **Yes** → Use **Option 1** (immediate consolidation)
- **No** → Can use Option 2 or 3

**Question 2:** How reliable is your webhook/queue system?
- **Very reliable** → Use **Option 1**
- **Not sure yet** → Start with **Option 2**, move to Option 1 later

**Question 3:** Want absolutely zero duplicate risk?
- **Yes** → Use **Option 3** (only update existing)
- **No** → Use **Option 1** or **Option 2**

---

**Recommendation: Start with Option 2 for testing, then move to Option 1 for production.** 🎯

