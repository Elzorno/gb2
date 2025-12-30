# GB2 — Behavior + Chores (Fresh Rebuild)

This is a clean, mobile-first PHP + SQLite web app for:
- Mon–Fri **daily** chore rotation (5 kids / 5 chore slots)
- Weekend “project/bonus mode” (no base rotation)
- Weekly, first-come **bonus chore** claims with photo proof
- Kid login via **PIN + device session token**
- Admin review queue (approve/reject), audit log

## Requirements
- Debian Bookworm (or similar)
- Apache + PHP 8.2
- PHP extensions: sqlite3, pdo_sqlite, gd, mbstring, openssl, fileinfo
- Writable data directory

## Quick install (Debian/Bookworm)
Run as root:

```bash
apt-get update
apt-get -y install apache2 php php-sqlite3 php-gd php-mbstring
a2enmod headers rewrite
systemctl restart apache2
```

## Deploy
Copy this folder into your Apache docroot (example: /var/www/gb2).

Make sure these are writable by the web server user (www-data):
- `data/` (and `data/uploads/`)

Example:
```bash
chown -R www-data:www-data data
find data -type d -exec chmod 775 {} \;
find data -type f -exec chmod 664 {} \;
```

## First run
1. Open `/admin/setup.php`
2. Set admin password + create kids + set kid PINs (6 digits recommended)
3. Configure (or accept) the default rotation rule

## Rotation schedule
- Rotation is generated **Mon–Fri @ 00:05** (cron)
- Bonus week resets **Mon @ 00:10** (cron)

Install cron jobs (as root):
```bash
crontab -e
```
Add:
```cron
5 0 * * 1-5 /usr/bin/php /var/www/cron/rotate_weekday.php
10 0 * * 1 /usr/bin/php /var/www/cron/reset_bonus_week.php
```

## Security notes
- Kids authenticate with PIN; a device session token is stored in a cookie.
- All write actions require CSRF.
- Admin actions require admin unlock (password).
- Every important action writes to the audit log.

