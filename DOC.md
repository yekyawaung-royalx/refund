PHP Initial Config:
nano /etc/php/8.3/fpm/php.ini

1.File Upload (60MB)
upload_max_filesize = 64M
post_max_size = 70M
rule: post_max_size > upload_max_filesize ဖြစ်ရမယ်

2.Execution Time (CSV import heavy)
max_execution_time = 300
max_input_time = 300
tip: bulk insert (120k rows) အတွက် timeout မဖြစ်အောင်

3.Memory (important for CSV parsing)
memory_limit = 512M
tip: CSV parse + array + insert batching အတွက်

4.File Handling / Streams
max_file_uploads = 20
tip: (default 20 OK, မပြောင်းလည်းရ)

5.Output Buffer (CSV export)
output_buffering = Off
large CSV export (100k rows) မှာ memory explode မဖြစ်အောင်

6.Realpath Cache (performance)
realpath_cache_size = 4096K
realpath_cache_ttl = 600

7.OPCache (Must for performance)
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
tip: production မှာ validate_timestamps=0 (faster)

8.PHP-FPM Pool (IMPORTANT)
nano /etc/php/8.3/fpm/pool.d/www.conf

VPS 4 CPU / 4GB RAM (Recommand)
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500

explanation:
max_children = request parallel count
RAM 4GB → over မတင်ရ

MySQL tuning (very important for bulk insert)
nano /etc/mysql/mysql.conf.d/mysqld.cnf
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
max_connections = 100
innodb_flush_log_at_trx_commit = 2

sudo systemctl restart mysql

Cann't start MySQL
sudo systemctl stop mysql
sudo rm /var/lib/mysql/ib_logfile*
sudo systemctl start mysql

Quick Check
SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
value = 1073741824 (≈ 1GB) ဖြစ်ရမယ်

Install Supervisor to run queue job
Step 1: Supervisor install
sudo apt update
sudo apt install supervisor -y

Step 2: config file create
sudo nano /etc/supervisor/conf.d/laravel-worker.conf

Step 3: Config
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/refund/artisan queue:work --sleep=3 --tries=3 --timeout=120
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/refund/storage/logs/worker.log
stopwaitsecs=3600

Important points
- numprocs=3 (4 CPU / 4GB RAM)
- --timeout=120 (CSV import/export)
- --sleep=3 (queue empty ဖြစ်ရင် CPU မစားအောင်)

Step 4: Supervisor reload
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*

Check status
sudo supervisorctl status

output:
laravel-worker:laravel-worker_00   RUNNING
laravel-worker:laravel-worker_01   RUNNING
laravel-worker:laravel-worker_02   RUNNING

Restart (important after deploy)
sudo supervisorctl restart laravel-worker:*

1.Code update လုပ်ပြီးရင်
php artisan queue:restart

2.Log check
tail -f /var/www/refund/storage/logs/worker.log

3.Failed jobs
php artisan queue:failed


Check Nginx Log
tail -f /var/log/nginx/laravel_error.log
tail -f /var/log/nginx/laravel_access.log


Block rate limiting for security
/etc/nginx/nginx.conf (http block)
http {
    # Rate limiting zone definition (global)
    limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;

    # Gzip compression (optional but good for CSV download)
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Include site configs
    include /etc/nginx/sites-enabled/*;
}