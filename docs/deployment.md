# Deployment Guide

This guide describes the production deployment flow for Business Dashboard.

> A ready-to-edit production template lives at [`.env.production.example`](../.env.production.example).
> Copy it to `.env` on the server rather than `.env.example` (which targets local dev).

## Production Hardening (must-do)

These three settings are safe for local development but **must be changed for
production**, or the app is either insecure or unreliable under load:

1. **`APP_ENV=production` and `APP_DEBUG=false`.** The dev template ships
   `APP_ENV=local` / `APP_DEBUG=true`; leaving those on in production leaks
   full stack traces and configuration to any visitor.
2. **Do not run on SQLite (`DB_CONNECTION=sqlite`).** SQLite serializes all
   writes to a single writer. The storefront (checkout, cart), admin panel,
   courier webhooks, and scheduled jobs write concurrently and will hit
   `database is locked` errors. Use MySQL 8+ or PostgreSQL.
3. **Do not use the `sync` queue driver.** With `sync`, queued jobs run inside
   the web request — courier webhook processing and the external courier fraud
   check (which logs into merchant panels) would block checkout/webhook
   responses, and retry/backoff never fires. Set `QUEUE_CONNECTION=database`
   (or `redis`) and run `php artisan queue:work` as a supervised process.

## Server Requirements

- PHP 8.2 or newer
- Composer
- Node.js 20
- MySQL or MariaDB for production
- Nginx or Apache pointed to `public/`
- Cron enabled for Laravel scheduler
- Queue worker enabled if `QUEUE_CONNECTION` is not `sync`

## Required Environment Values

Set these values in production:

```env
APP_NAME="Business Dashboard"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
ASSET_URL=https://your-domain.com
TRUSTED_PROXIES=*
APP_VERSION=1.21.0
APP_RELEASE_TYPE=minor
APP_RELEASE_DATE=2026-07-23

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=business_dashboard
DB_USERNAME=...
DB_PASSWORD=...

ADMIN_NAME="Super Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=

QUEUE_CONNECTION=sync
SESSION_DRIVER=file
CACHE_STORE=file
```

Never commit `.env` or production credentials. `ADMIN_PASSWORD` must be at least 12 characters and include uppercase and lowercase letters, numbers, and symbols.

`APP_URL` and `ASSET_URL` must both use `https://` in production. Coolify/Traefik terminates TLS at the reverse proxy, so `TRUSTED_PROXIES=*` allows Laravel to honor its forwarded HTTPS scheme. Restrict this value to known proxy addresses if the application container is also directly exposed to untrusted traffic.

For small single-server or SQLite installs, keep `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `QUEUE_CONNECTION=sync`. For higher traffic MySQL deployments, use Redis where available, or database queue/cache with a dedicated queue worker.

## First Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If this is a brand-new installation with no real business data yet, create the first admin user with the guarded seeder or the admin command:

```bash
php artisan db:seed --force
```

Create or reset the admin user later with:

```bash
php artisan admin:ensure-super --email=admin@example.com --password="..."
```

## GitHub Actions Deploy

The included workflow deploys pushes to `main`. Configure these repository secrets:

```txt
SERVER_HOST
SERVER_USER
SERVER_SSH_KEY
SERVER_PORT
DEPLOY_PATH
```

The deploy workflow runs tests before SSH deploy. On the server it:

1. Pulls latest code.
2. Installs PHP and Node dependencies.
3. Builds frontend assets.
4. Enables maintenance mode.
5. Creates a database backup.
6. Runs migrations.
7. Caches config, routes, and views.
8. Restarts queues.
9. Disables maintenance mode.

## Manual Update Deploy

```bash
cd /path/to/project
php artisan down --retry=60
php artisan backup:database
git pull origin main
composer install --no-dev --prefer-dist --no-interaction --no-progress
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Do not run `php artisan db:seed --force` during routine updates after real customers, purchases, products, or users exist.

## User-Controlled Admin App Upgrade

`npm run build` now writes `public/build/deployment.json` after Vite finishes. Do not skip or reorder that step: the file gives every release a deterministic identity from the source tree, built assets, and Git/platform commit when available.

After `php artisan migrate --force`, existing users retain their last acknowledged deployment. When the new build is stable:

- the open admin app continues without an automatic full reload;
- **Upgrade App** appears above **Sign out** in the avatar menu;
- the Filament bell receives one persistent app-update notification per user/build;
- the user can review Release Notes, save unfinished work, and choose when to reload.

The alert is inserted synchronously and therefore does not depend on the queue worker. The scheduler remains a recovery path, and this command can be run immediately after deployment:

```bash
php artisan release:notify-deploy
```

Verify the identity and cache policy:

```bash
curl -i https://your-domain.com/health/version
```

Expect a non-empty `deployment_id`, `built_at`, `"ready": true`, and `Cache-Control: no-store`. A `ready: false` response prevents upgrade prompts and normally means deployment metadata is missing, the actual Vite manifest hash differs, or runtime commit metadata and built files do not belong to the same release; rebuild before exposing that instance.

During a rolling replacement, keep build clocks synchronized. Server and browser
use `built_at` to reject responses from older nodes, and the upgrade POST must
match the exact deployment ID the user confirmed before it can acknowledge or
clear cached files.

This feature holds the already-loaded frontend shell until consent. The deployed PHP backend is still the active backend. Whole-stack old/new coexistence requires sticky blue/green routing and backward-compatible database changes.

If a deploy fails while maintenance mode is enabled:

```bash
php artisan up
```

## Live Data Safety

Application updates should keep live data when they use forward migrations and the existing production database. Data loss risk comes from destructive commands, destructive migrations, unsafe seeders, or pointing the app to the wrong database/storage.

Never run these commands against live production data:

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:reset
php artisan db:wipe
```

Before every production update:

1. Confirm the current code version and the target `APP_VERSION`.
2. Create a database backup with `php artisan backup:database`.
3. Run only reviewed migrations with `php artisan migrate --force`.
4. Verify admin login, dashboard totals, recent orders, purchases, payments, and stock after deploy.
5. Update `CHANGELOG.md` and GitHub release notes for the deployed version.

## Scheduler

Add this cron entry:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Queue Worker

If `QUEUE_CONNECTION=sync`, no queue worker is required.

For database queues:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Use Supervisor or your hosting panel to keep the worker alive.

## Production Checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- Strong `ADMIN_PASSWORD` with uppercase and lowercase letters, numbers, and symbols
- Company profile, currency, timezone, and logo configured
- HTTPS enabled
- `.env` not committed
- Scheduler running
- Queue worker running
- Backups tested
- File permissions set for `storage/` and `bootstrap/cache/`
- Admin users reviewed
- GitHub deploy secrets configured
