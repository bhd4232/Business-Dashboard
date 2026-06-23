# Backup and Restore Guide

Business Dashboard includes database backups, full app backups, backup downloads, and optional Google Drive upload.

## Backup Types

| Type | Contents | Location |
|---|---|---|
| Database backup | SQLite or MySQL/MariaDB dump | `storage/app/private/backups/database` |
| Full app backup | App files plus database backup | `storage/app/private/backups/app` |
| Google Drive backup | Uploaded full app backup | Configured Drive folder |

## Create Database Backup

From CLI:

```bash
php artisan backup:database
```

From the admin panel:

```txt
/admin/backups
```

Database backup supports SQLite and MySQL/MariaDB.

Create a database backup before every production update, before running migrations, and before changing server database/storage settings.

## Create Full App Backup

Use the Backups page in the admin panel:

```txt
/admin/backups
```

Full app backups require PHP Zip extension.

## Retention

Configure retention in `.env`:

```env
BACKUP_RETAIN_FILES=10
```

Old backups beyond the retention count are cleaned automatically by the backup services.

## Google Drive Upload

Configure:

```env
GOOGLE_DRIVE_BACKUP_ENABLED=true
GOOGLE_DRIVE_BACKUP_AUTO_UPLOAD=true
GOOGLE_DRIVE_BACKUP_FOLDER_ID=
GOOGLE_DRIVE_SERVICE_ACCOUNT_PATH=
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=
```

Use either service account JSON content or a path to a service account JSON file.

## Download Permissions

Backup download requires:

- Super Admin, or
- `backups.manage`

Backup downloads are written to the audit log as `backup_downloaded`.

## Restore SQLite Backup

1. Put the app in maintenance mode:

```bash
php artisan down
```

2. Copy the selected backup over the active database file:

```bash
cp storage/app/private/backups/database/database-backup-YYYYMMDD-HHMMSS-sqlite.sqlite database/database.sqlite
```

3. Clear caches and bring the app back:

```bash
php artisan optimize:clear
php artisan up
```

## Restore MySQL/MariaDB Backup

1. Put the app in maintenance mode:

```bash
php artisan down
```

2. Import the selected SQL dump:

```bash
mysql -u DB_USERNAME -p DB_DATABASE < storage/app/private/backups/database/database-backup-YYYYMMDD-HHMMSS-mysql.sql
```

3. Run migrations if the code is newer than the backup:

```bash
php artisan migrate --force
```

4. Clear caches and bring the app back:

```bash
php artisan optimize:clear
php artisan up
```

## Restore Checklist

1. Confirm the backup file date and environment.
2. Download a copy before restoring.
3. Put the app in maintenance mode.
4. Restore database.
5. Run migrations if needed.
6. Clear caches.
7. Verify admin login.
8. Check dashboard totals.
9. Check recent orders, purchases, payments, and stock.
10. Review audit logs.
11. Verify company settings and invoice branding.
12. Confirm `/health/version` shows the expected app version after the restore or redeploy.
