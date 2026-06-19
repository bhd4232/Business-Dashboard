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

For small single-server or SQLite installs, keep `SESSION_DRIVER=file`, `CACHE_STORE=file`, and `QUEUE_CONNECTION=sync`. For higher traffic MySQL deployments, use Redis where available, or database queue/cache with a dedicated queue worker.

## First Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
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
git pull origin main
composer install --no-dev --prefer-dist --no-interaction --no-progress
npm ci
npm run build
php artisan down --retry=60
php artisan backup:database
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

If a deploy fails while maintenance mode is enabled:

```bash
php artisan up
```

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
