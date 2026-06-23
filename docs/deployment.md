# Deployment Guide

This guide describes the production deployment flow for Business Dashboard.

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
APP_VERSION=1.0.0
APP_RELEASE_TYPE=major
APP_RELEASE_DATE=2026-06-21

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
