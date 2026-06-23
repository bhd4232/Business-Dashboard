# Production Update Safety

Live data should remain safe during app updates when deployments use backups and forward migrations correctly. The usual risk is not `git pull` itself; data loss happens when a deploy runs destructive database commands, destructive migrations, unsafe seeders, or points the app to the wrong database/storage volume.

## What Can Cause Data Loss

- Running `php artisan migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe` in production.
- Writing a migration that drops tables, drops columns, truncates records, or rewrites values without a rollback plan.
- Running seeders on the live database after real data exists.
- Replacing the production database file, database volume, or upload storage during deployment.
- Deploying without a restorable backup.
- Running app code and database schema versions that do not match.

## Safe Update Checklist

1. Confirm users are not actively entering critical transactions.
2. Put the app in maintenance mode.
3. Create a database backup.
4. Pull/build the new code.
5. Run `php artisan migrate --force`.
6. Clear and rebuild Laravel caches.
7. Restart queues if queue workers are used.
8. Bring the app back online.
9. Verify login, dashboard totals, recent orders, purchases, payments, and stock.

## Safe Manual Update Commands

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

## Commands Never To Run On Live Data

```bash
php artisan migrate:fresh
php artisan migrate:refresh
php artisan migrate:reset
php artisan db:wipe
php artisan demo:refresh --database=/path/to/live/database
```

## Seeder Rule

Use `php artisan db:seed --force` only during first installation before real business data exists, or when a specific seeder is reviewed and confirmed safe for existing production data.

## Rollback Rule

If an update fails after migration, do not guess by manually editing data. Put the app in maintenance mode, preserve the failed state for inspection if possible, restore the latest verified backup, and redeploy the last known good release.
