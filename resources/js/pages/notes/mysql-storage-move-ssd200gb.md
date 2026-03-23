# MySQL Data Directory Migration (Move to 200GB Storage)
Date: 2026-03-22  
Author: Ye Kyaw Aung  

---
## Step 1: Stop MySQL Service
```bash
sudo systemctl stop mysql
```
---

## Step 2: Create New Data Directory & Set Permissions
```bash
sudo mkdir -p /mnt/ssd200gb/mysql
sudo chown -R mysql:mysql /mnt/ssd200gb/mysql
sudo chmod 750 /mnt/ssd200gb/mysql
```
---

## Step 3: Copy Existing MySQL Data (Safe Migration)
```bash
sudo rsync -av --progress /data/mysql/ /mnt/ssd200gb/mysql/
```

### Optional Backup
```bash
sudo mv /data/mysql /data/mysql.bak
```
---

## Step 4: Update MySQL Configuration
Edit config file:
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Update [mysqld] section:
```bash
[mysqld]
user = mysql
pid-file = /var/run/mysqld/mysqld.pid
socket = /var/run/mysqld/mysqld.sock
port = 3306

datadir = /mnt/ssd200gb/mysql
tmpdir = /mnt/ssd200gb/mysql/tmp

bind-address = 127.0.0.1
mysqlx-bind-address = 127.0.0.1

key_buffer_size = 16M
myisam-recover-options = BACKUP

log_error = /var/log/mysql/error.log
max_binlog_size = 100M
```
---

## Step 5: Update AppArmor Profile (Ubuntu Only)
Edit profile:
```bash
sudo nano /etc/apparmor.d/usr.sbin.mysqld
```

Add these lines:
```bash
/mnt/ssd200gb/mysql/ r,
/mnt/ssd200gb/mysql/** rwk,
```

Reload AppArmor:
```bash
sudo apparmor_parser -r /etc/apparmor.d/usr.sbin.mysqld
```
---

## Step 6: Start MySQL Service
```bash
sudo systemctl daemon-reexec
sudo systemctl start mysql
sudo systemctl status mysql
```
---

## Step 7: If MySQL Fails to Start
Check error log:
```bash
sudo tail -n 50 /var/log/mysql/error.log
```
---

## Step 8: Fix Permissions (If Needed)
```bash
sudo chown -R mysql:mysql /mnt/ssd200gb/mysql
sudo chmod 750 /mnt/ssd200gb/mysql
sudo chmod 750 /mnt/ssd200gb/mysql/tmp
```
---

## Step 9: Restart & Enable MySQL
```bash
sudo systemctl daemon-reexec
sudo systemctl start mysql
sudo systemctl enable mysql
sudo systemctl status mysql
```
---

## Step 10: Verify Storage Mount
```bash
mount | grep ssd200gb
```
OR
```bash
df -h
```
---

## Notes
- Always stop MySQL before copying data
- Use rsync to prevent data corruption
- Ensure correct ownership (mysql:mysql)
- AppArmor must allow the new data directory
- Always check logs if MySQL fails to start

---

*End of Notes*