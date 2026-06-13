# Configuration PHP, MySQL & Ngnix
## Step 1 (PHP): Open `nano /etc/php/8.3/fpm/php.ini`
```bash
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300
```
---

## Step 2 (MySQL): Open `nano /etc/mysql/mysql.conf.d/mysqld.cnf`
```bash
[mysqld]
user            = mysql
pid-file        = /var/run/mysqld/mysqld.pid
socket          = /var/run/mysqld/mysqld.sock
port            = 3306
datadir         = /mnt/ssd200gb/mysql
tmpdir          = /mnt/ssd200gb/mysql/tmp

innodb_buffer_pool_size = 4G
innodb_buffer_pool_instances = 4
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
tmp_table_size = 256M
max_heap_table_size = 256M
max_connections = 200
max_binlog_size   = 100M
log_error = /var/log/mysql/error.log
```
---

## Step 3 (PHP FPM): Open `/etc/php/8.3/fpm/pool.d/www.conf`
```bash
pm = dynamic
pm.max_children = 40
pm.start_servers = 8
pm.min_spare_servers = 4
pm.max_spare_servers = 12
pm.max_requests = 500
```
---

## MySQL Status
```bash
mysql> SHOW PROCESSLIST;
+-----+-----------------+-----------+------+---------+-------+------------------------+------------------+
| Id  | User            | Host      | db   | Command | Time  | State                  | Info             |
+-----+-----------------+-----------+------+---------+-------+------------------------+------------------+
|   5 | event_scheduler | localhost | NULL | Daemon  | 90311 | Waiting on empty queue | NULL             |
| 308 | root            | localhost | NULL | Query   |     0 | init                   | SHOW PROCESSLIST |
```

```bash
mysql> SHOW GLOBAL STATUS LIKE 'Threads_running';
+-----------------+-------+
| Variable_name   | Value |
+-----------------+-------+
| Threads_running | 2     |
+-----------------+-------+
```

```bash
mysql> SHOW GLOBAL STATUS LIKE 'Connections';
+---------------+-------+
| Variable_name | Value |
+---------------+-------+
| Connections   | 8   |
+---------------+-------+
```

```bash
mysql> SHOW VARIABLES;

```

```bash
mysql> SHOW VARIABLES LIKE 'innodb_buffer_pool_size';
+-------------------------+------------+
| Variable_name           | Value      |
+-------------------------+------------+
| innodb_buffer_pool_size | 4294967296 |
+-------------------------+------------+
```

```bash
mysql> SHOW VARIABLES LIKE 'max_connections';
+-----------------+-------+
| Variable_name   | Value |
+-----------------+-------+
| max_connections | 200   |
+-----------------+-------+
```

```bash
mysql> SHOW VARIABLES LIKE 'tmp_table_size';
+----------------+-----------+
| Variable_name  | Value     |
+----------------+-----------+
| tmp_table_size | 268435456 |
+----------------+-----------+
```

```bash
mysql> SHOW VARIABLES LIKE 'max_heap_table_size';
+---------------------+-----------+
| Variable_name       | Value     |
+---------------------+-----------+
| max_heap_table_size | 268435456 |
+---------------------+-----------+
```

```bash
mysql> SHOW VARIABLES LIKE 'sort_buffer_size';
+------------------+---------+
| Variable_name    | Value   |
+------------------+---------+
| sort_buffer_size | 4194304 |
+------------------+---------+
```

```bash
mysql> SHOW VARIABLES LIKE 'join_buffer_size';
+------------------+---------+
| Variable_name    | Value   |
+------------------+---------+
| join_buffer_size | 4194304 |
+------------------+---------+
```

```bash
mysql> SHOW VARIABLES LIKE 'read_buffer_size';
+------------------+---------+
| Variable_name    | Value   |
+------------------+---------+
| read_buffer_size | 1048576 |
+------------------+---------+
```

```bash
mysql> SELECT
    -> table_schema,
    -> ROUND(SUM(data_length + index_length)/1024/1024,2) AS MB
    -> FROM information_schema.tables
    -> GROUP BY table_schema;
+--------------------+--------+
| TABLE_SCHEMA       | MB     |
+--------------------+--------+
| information_schema |   0.00 |
| mysql              |   2.91 |
| performance_schema |   0.00 |
| refund_db          | 201.98 |
| sys                |   0.02 |
+--------------------+--------+
```

```bash
mysql> SHOW ENGINE INNODB STATUS\G
```
---