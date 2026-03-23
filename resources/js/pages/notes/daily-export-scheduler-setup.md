# Daily Export Scheduler Setup (Laravel 12)

## Step 1: Open `routes/console.php`
```bash
nano routes/console.php
```
---

## Step 2: Add Schedule
```bash
use Illuminate\Support\Facades\Schedule;

Schedule::command('export:daily')
    ->dailyAt('04:00')
    ->timezone('Asia/Yangon');
```
---

## Step 3: Add Cron Job on VPS (IMPORTANT)
```bash
crontab -e
```

Add the following line:
```bash
* * * * * cd /var/www/your-project && php artisan schedule:run >> /dev/null 2>&1
```
This runs Laravel's scheduler every minute, and the `export:daily` command will execute at 4:00 AM Yangon time.
---

## Step 4: Test the Schedule
```bash
php artisan schedule:list
```
You should see `export:daily` scheduled at 04:00 with timezone `Asia/Yangon`.

## Step 5: Manual Run Test
```bash
php artisan schedule:run
```
This allows you to verify that the command works immediately.

Quick Check (Optional)
```bash
php artisan schedule:list
```