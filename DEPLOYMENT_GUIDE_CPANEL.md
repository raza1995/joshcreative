# 🚀 Live Deployment Guide - ShipStation SKU Consolidation

## 📋 Pre-Deployment Checklist

```
[ ] Backup database
[ ] Backup .env file
[ ] Test on staging/local (✅ Done!)
[ ] Verify ShipStation API credentials
[ ] Verify email (MAIL_) configuration in .env
[ ] Confirm ShipStation auto-sync is enabled
[ ] Review consolidation logic
```

---

## 🔧 Step 1: Push Code to Production

```bash
# On your local machine
git add .
git commit -m "Add ShipStation pull-based SKU consolidation system with scheduler"
git push origin master
```

Then on **cPanel**:
- Go to **Git Version Control**
- Click **Update** or **Pull** on your repository

---

## 🗄️ Step 2: Run Database Migration

**Via cPanel Terminal** or **SSH:**

```bash
cd /home/yourusername/public_html  # Adjust to your Laravel root
php artisan migrate --force
```

**Expected Output:**
```
Running migrations.
2025_10_09_000004_create_shipstation_processed_orders_table ....... DONE
```

---

## ⚙️ Step 3: Verify Environment Variables

**Edit `.env` file in cPanel File Manager:**

```env
# ShipStation API Credentials (REQUIRED)
SHIPSTATION_API_KEY=your_api_key_here
SHIPSTATION_API_SECRET=your_api_secret_here
SHIPSTATION_BASE_URL=https://ssapi.shipstation.com

# Pull-Based Sync Settings
SHIPSTATION_CONSOLIDATE_SKUS=true

# Optional: Disable webhook-based push if using pull-only
SHIPSTATION_AUTO_PUSH=false

# Email Configuration (REQUIRED for alerts)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

**After editing, clear cache:**
```bash
php artisan config:clear
php artisan cache:clear
```

---

## ⏰ Step 4: Setup Laravel Scheduler in cPanel

### ✅ Scheduler is Already Configured!

The sync command is now in `app/Console/Kernel.php`:
- **Runs every 3 minutes** ⏱️
- **Pulls orders from last 5 minutes**
- **Sends email to razakkhanafridi@gmail.com on failure** 📧
- **Won't overlap** if previous run is still going
- **Runs in background** for performance

### 4.1 Setup Single Cron Job

**You only need ONE cron job for Laravel Scheduler:**

1. Log into **cPanel**
2. Go to **Cron Jobs**
3. Add this **ONE** cron job:

**Schedule:** Every Minute
```
Minute: *
Hour: *
Day: *
Month: *
Weekday: *
```

**Command:**
```bash
cd /home/yourusername/public_html && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Important: Replace:**
- `/home/yourusername/public_html` → Your actual Laravel root path
- `/usr/bin/php` → Your PHP path

### 4.2 Find Your PHP Path (if needed)

Run this in cPanel Terminal:
```bash
which php
```

Common cPanel PHP paths:
- `/usr/bin/php`
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php82/root/usr/bin/php` (for PHP 8.2)
- `/opt/cpanel/ea-php81/root/usr/bin/php` (for PHP 8.1)

---

## 🧪 Step 5: Test the Setup

### Test 1: Verify Scheduler
```bash
cd /home/yourusername/public_html
php artisan schedule:list
```

**Expected Output:**
```
0 */3 * * * php artisan facebook:sync-daily ......... Next Due: 3 hours from now
*/3 * * * * php artisan shipstation:sync-from-api ... Next Due: 1 minute from now
...
```

### Test 2: Manual Dry Run
```bash
php artisan shipstation:sync-from-api --dry-run --minutes=1440
```

**Expected:** Should show orders pulled from ShipStation

### Test 3: Process Specific Order
```bash
php artisan shipstation:process-order 52260
```

**Expected:** Should show consolidation details

### Test 4: Run Scheduler Manually
```bash
php artisan schedule:run
```

**Expected Output:**
```
Running scheduled command: php artisan shipstation:sync-from-api --minutes=5
```

### Test 5: Check Logs
```bash
tail -50 storage/logs/laravel.log
```

**Look for:**
- `Pulling orders from ShipStation`
- `Pulled orders from ShipStation`
- `Consolidating order from ShipStation`

---

## 📊 Step 6: Monitor First Run

### 6.1 Wait 3 Minutes After Cron Setup

Then check:

```bash
# Check if scheduler ran
php artisan schedule:list

# Check Laravel logs
tail -100 storage/logs/laravel.log | grep "ShipStation"

# Check processed orders table
php artisan tinker
>>> \App\Models\ShipStationProcessedOrder::count();
>>> \App\Models\ShipStationProcessedOrder::latest()->take(5)->get();
>>> exit
```

### 6.2 Verify in ShipStation

1. Log into ShipStation
2. Find an order that had duplicate SKUs
3. Check if line items are now consolidated

---

## 📧 Email Alerts

You will receive emails at **razakkhanafridi@gmail.com** when:
- ❌ The sync command fails
- ❌ API connection errors occur
- ❌ Database errors happen

**Email will include:**
- Error message
- Stack trace
- Timestamp

---

## 🔍 Step 7: Troubleshooting

### Issue: Scheduler Not Running

**Check if cron is active:**
```bash
crontab -l  # Should show your Laravel scheduler cron
```

**Check scheduler output manually:**
```bash
php artisan schedule:run -v
```

### Issue: Emails Not Sending

**Test email configuration:**
```bash
php artisan tinker
>>> Mail::raw('Test email from ShipStation sync', function($msg) {
    $msg->to('razakkhanafridi@gmail.com')->subject('Test');
});
>>> exit
```

**If using Gmail:**
- Use App Password (not regular password)
- Enable "Less secure app access" OR use App-specific password
- Go to: https://myaccount.google.com/apppasswords

### Issue: Permission Denied

**Fix storage permissions:**
```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
chown -R yourusername:yourusername storage
```

### Issue: Orders Not Being Pulled

**Test API connection:**
```bash
php artisan shipstation:test-connection
```

**Check credentials:**
```bash
php artisan tinker
>>> config('shipstation.api_key')
>>> config('shipstation.api_secret')
>>> exit
```

---

## 🎯 How It Works

```
┌─────────────────────────────────────────────────────┐
│ Every 3 minutes:                                    │
│                                                     │
│ 1. Laravel Scheduler triggers                       │
│ 2. Pulls orders from ShipStation (last 5 min)      │
│ 3. Checks for duplicate SKUs                        │
│ 4. Consolidates if needed                           │
│ 5. Updates back to ShipStation                      │
│ 6. Logs everything                                  │
│ 7. Sends email if fails ✉️                          │
└─────────────────────────────────────────────────────┘
```

**Timeline:**
- **3 minutes:** Max delay for new orders
- **5 minutes:** Lookback window (catches any missed)
- **Background:** Won't slow down your app

---

## 📝 Post-Deployment Checklist

```
[ ] Database migration ran successfully
[ ] .env file updated (ShipStation + Email config)
[ ] Laravel scheduler cron job added (* * * * *)
[ ] Test run completed successfully (schedule:run)
[ ] Manual order processing tested
[ ] Scheduler listed command correctly
[ ] Logs showing successful pulls
[ ] First automated run completed (wait 3 min)
[ ] Verified consolidation in ShipStation
[ ] Test email alert sent successfully
[ ] Email alerts working (send test failure)
```

---

## 🚨 Rollback Plan (If Needed)

If something goes wrong:

```bash
# 1. Disable the scheduler (comment out in Kernel.php)
# Edit app/Console/Kernel.php, comment line 39-43

# 2. Deploy the change
git add app/Console/Kernel.php
git commit -m "Disable ShipStation sync temporarily"
git push origin master
# Pull in cPanel

# 3. Rollback database migration (if needed)
php artisan migrate:rollback --step=1

# 4. Clear cache
php artisan config:clear
php artisan cache:clear
```

---

## ⚙️ Adjusting Sync Frequency

Edit `app/Console/Kernel.php` line 39-43:

### Every 1 Minute (Fastest)
```php
$schedule->command('shipstation:sync-from-api --minutes=2')
         ->everyMinute()
```

### Every 3 Minutes (Current - RECOMMENDED)
```php
$schedule->command('shipstation:sync-from-api --minutes=5')
         ->everyThreeMinutes()
```

### Every 5 Minutes (Lower API usage)
```php
$schedule->command('shipstation:sync-from-api --minutes=10')
         ->everyFiveMinutes()
```

### Every 10 Minutes (Minimal API usage)
```php
$schedule->command('shipstation:sync-from-api --minutes=15')
         ->everyTenMinutes()
```

After changing, deploy and run:
```bash
php artisan config:clear
php artisan schedule:list
```

---

## 📊 Success Metrics

After 24 hours, check:

```bash
php artisan tinker
>>> $stats = \App\Models\ShipStationProcessedOrder::selectRaw('
    action, 
    COUNT(*) as count,
    SUM(items_before - items_after) as total_items_reduced
')->groupBy('action')->get();
>>> $stats;

>>> // Today's stats
>>> $today = \App\Models\ShipStationProcessedOrder::whereDate('processed_at', today())
    ->selectRaw('action, COUNT(*) as count')
    ->groupBy('action')
    ->get();
>>> $today;

>>> exit
```

**Expected Results:**
- `consolidated`: Orders with duplicate SKUs fixed
- `skipped_no_duplicates`: Orders that didn't need fixing
- `total_items_reduced`: Total line items merged

---

## 🎉 You're Live!

The system is now:
✅ Running every 3 minutes via Laravel Scheduler  
✅ Pulling orders from ShipStation automatically  
✅ Detecting and consolidating duplicate SKUs  
✅ Updating orders back to ShipStation  
✅ Tracking all processed orders  
✅ Sending email alerts on failure  
✅ Generating clean packing slips  

**Questions? Check logs at:** `storage/logs/laravel.log`

---

## 📞 Support Commands

```bash
# Check scheduler status
php artisan schedule:list

# Run scheduler now (manual trigger)
php artisan schedule:run

# Check system status
php artisan shipstation:test-connection

# View processed orders
php artisan tinker
>>> \App\Models\ShipStationProcessedOrder::latest()->take(10)->get()

# Manual sync now
php artisan shipstation:sync-from-api

# Process specific order
php artisan shipstation:process-order ORDER_NUMBER

# Clear all processed orders (reprocess everything)
php artisan tinker
>>> \App\Models\ShipStationProcessedOrder::truncate()

# Send test email
php artisan tinker
>>> Mail::raw('ShipStation sync is working!', function($msg) {
    $msg->to('razakkhanafridi@gmail.com')->subject('Test Alert');
});
```

---

## 🔐 Security Notes

1. **Never commit** `.env` file to Git
2. Use **App Passwords** for Gmail (not regular password)
3. Restrict **cPanel access** to trusted IPs
4. Keep **ShipStation API keys** secure
5. Regularly **rotate API credentials**

---

## 📈 Performance Impact

- **CPU:** Minimal (runs in background)
- **Memory:** ~50-100MB per run
- **Database:** ~1-2KB per processed order
- **API Calls:** ~2-5 calls per 3 minutes
- **ShipStation Rate Limit:** 40 calls/minute (we use ~2)

**Safe for production!** ✅

---

Save and deploy! 🚀

