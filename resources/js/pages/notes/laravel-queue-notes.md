
# Laravel Queue & Worker Setup Notes

Date: 2026-03-22  
Author: Ye Kyaw Aung  

---

## 1. Job Config

### CheckNoRefundFileJob

- Purpose: Validate CSV file and check duplicates in database.  
- Timeout: 1800s (30 mins)  
- Retries: 3 times  
- Expected Columns: 33  
- DB Chunk Size: 1000  
- Dispatch Import Job on `import` queue:
```php
ImportNoRefundFileJob::dispatch($this->uploadId, $this->filePath)->onQueue('import');
```
---

### ImportNoRefundFileJob

- Purpose: Import validated CSV into `upload_data` table.  
- Timeout: 3600s (1 hour)  
- Retries: 3 times  
- Batch Insert: 1000 rows per transaction (InnoDB optimized)  
- Efficient memory usage via `SplFileObject` streaming  
- Clean string values (UTF-8 + max length)  

---

### Queue Connection

- `.env`:
```
QUEUE_CONNECTION=database
```

- Queue names:
  - `default` → lightweight / daily jobs  
  - `import` → heavy CSV import jobs  

---

## 2. Separate Job & Supervisor Config

### Supervisor Configuration
Create or update at `nano /etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-default]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/refund/artisan queue:work redis --queue=default --sleep=1 --tries=3 --timeout=120 --memory=256 --max-jobs=100
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/refund/storage/logs/default-worker.log
stopwaitsecs=180

[program:laravel-import]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/refund/artisan queue:work redis --queue=import --sleep=1 --tries=3 --timeout=120 --memory=256 --max-jobs=100
autostart=true
autorestart=true
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/refund/storage/logs/import-worker.log
stopwaitsecs=180
```

- Workers split by queue:
  - `laravel-default` → default queue  
  - `laravel-import` → import queue (2 workers)  

### Supervisor Commands

```bash
supervisorctl reread
supervisorctl update
supervisorctl start all
supervisorctl status
```

---

## 3. Check Logs

### Tail logs

```bash
tail -f /var/www/refund/storage/logs/import-worker.log
tail -f /var/www/refund/storage/logs/default-worker.log
```

### Failed Jobs

- List failed jobs:
```bash
php artisan queue:failed
```

- Flush failed jobs:
```bash
php artisan queue:flush
```

- Forget single failed job:
```bash
php artisan queue:forget {failed-job-id}
```

---

## 4. Notes & Best Practices

- Always specify `.onQueue('queue-name')` when dispatching jobs for separation.  
- Heavy jobs (CSV import) should be on a dedicated queue & worker.  
- Use `SplFileObject` for streaming large CSV files to reduce memory usage.  
- Batch inserts to DB improve performance and avoid InnoDB log issues.  
- Update progress periodically for long-running jobs (e.g., every 2000 rows).  
- Supervisor workers should have logs separated by queue.  
- Restart workers after flushing failed jobs to reset state.  

---

## 5. Optional Upgrades

- Use **Redis** queue for faster performance (2–5x faster than DB queue).  
- Use **Laravel Horizon** for monitoring queues, failed jobs, and throughput.  
- Consider **priority queues** (`--queue=high,default`) for urgent jobs.  
- Monitor memory usage and server load for production optimization.

---

*End of Notes*
