---
paths:
  - '**'
---

# General

## Config cache breaks `php artisan test` (419 CSRF)
A cached config (bootstrap/cache/config.php) bakes in APP_ENV=local, so `php artisan test` boots with env=local instead of testing and the CSRF middleware is no longer skipped (all POSTs return 419, PasswordReset notifications never fire). Before running or debugging the test suite, run `php artisan config:clear` and leave the config uncached (do NOT re-run config:cache).
