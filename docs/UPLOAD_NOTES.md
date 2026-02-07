# GB2 FTP Upload Notes (Read Before Upload)

GB2 uses a **two-layer config model**:

- `/var/www/config.php` — **repo-tracked shim** (PHP). Safe to overwrite from the repo.
- `/var/www/data/config.php` — runtime loader (**NOT committed**)
- `/var/www/data/config.local.php` — secrets/local settings (**NOT committed**)

## Critical rule

**Never overwrite `/var/www/data/*` from a zip upload.**

If you overwrite the runtime config files under `/data`, GB2 can break (classic symptom: a page renders a literal path like `/var/www/data/config.php`).

## Safe upload method

1. Upload and overwrite everything **except** the `data/` directory.
2. Confirm these still exist on the server after upload:
   - `/var/www/data/app.sqlite`
   - `/var/www/data/config.php`
   - `/var/www/data/config.local.php`

## Quick validation commands (on the server)

```bash
cd /var/www
php -l config.php lib/common.php lib/ui.php
php -r 'require "lib/common.php"; $c=gb2_config(); echo "OK brand=".$c["branding"]["brand"]."\n";'
```
