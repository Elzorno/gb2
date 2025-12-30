# GB2 Working Agreement (stability first)

This project is being iterated in small, safe steps. The goal is **no surprises**.

## Source of truth
- The **zip you deploy** is the source of truth.
- You will not hand-edit production files.
- Every change is delivered as a **full replacement file** (or a patch you can apply verbatim) and then bundled into a new zip.

## Guardrails
- No guessing about paths or function names.
- No infra changes.
- Copy/paste safe.
- Keep UX trauma-informed: calm language, predictable UI, no shaming.

## Config rule (important)
- `config.php` is a loader and **must return an array**.
- Persistent config lives at: `/var/www/data/config.php` (not committed).
- Defaults live at: `/var/www/config.sample.php`.

If you see errors like "return value must be of type array" or the UI suddenly ignores the admin password, run:

```bash
/var/www/tools/gb2-preflight.sh /var/www
```

It will print which config/db path is active.
