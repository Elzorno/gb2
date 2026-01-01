# GB2 — Grounding Board 2 (Behavior + Chores)

GB2 is a clean, mobile-first PHP + SQLite web app for:
- Mon–Fri **daily** chore rotation (5 kids / 5 chore slots)
- Weekend “project/bonus mode” (no base rotation)
- Weekly, first-come **bonus chore** claims with photo proof
- Kid login via **PIN + device session token**
- Admin review queue (approve/reject), audit log

## Requirements
- Linux host with **Apache + PHP 8.2**
- PHP extensions: sqlite3, pdo_sqlite, gd, mbstring, openssl, fileinfo
- Writable directories:
  - `data/` (SQLite + runtime files)
  - `data/uploads/` (proof photos)

## Installation (drop-in for existing deployments)
1. Copy this repo into your web root (example):
   - `/var/www`

2. Ensure your runtime config exists **outside the repo**:
   - Create `/var/www/data/config.php` by copying `config.sample.php` and editing values.
   - The tracked `/var/www/config.php` is a *shim* that loads `/var/www/data/config.php`.

3. Ensure the runtime directories exist and are writable by your web server user (`www-data` on Debian/Ubuntu):
   - `/var/www/data`
   - `/var/www/data/uploads`

4. Point Apache at the repo (or put it under a vhost) and browse to:
   - Parent/Guardian setup: `/admin/setup.php`

## Cron jobs
Run weekday rotation and weekly bonus reset:

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
- All state-changing actions use CSRF protection.
- Admin actions require an admin unlock (password).
- Security headers include a conservative Content-Security-Policy (no inline scripts).
- Session cookies automatically set the `Secure` flag when HTTPS is detected (override via config: `session.cookie_secure`).
- Important actions write to the audit log.

## Repo hygiene
- Runtime files under `data/` are intentionally not committed.
- Local editor backups (e.g. `*.bak.*`) and support bundles should not be committed.



## Notes (Jan 2026)

- **Rules page now includes a read-only weekly chore chart** (Mon–Fri + Weekend) for quick “at a glance” expectations.
- **Bonuses are cash-only incentives.** GB2 no longer uses “banked device minutes” for bonus chores.
  - Admin can edit bonus projects at **/admin/bonus_defs.php**.
  - Kids can view their bonus earnings on **/app/bonuses.php** and a summary on their dashboard.
