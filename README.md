# Business Dashboard

Business Dashboard is a Laravel and Filament based inventory, purchase costing, sales, accounts, and reporting system for small businesses, importers, and wholesale operations.

It helps teams manage products, stock movements, customers, suppliers, sales orders, purchases, expenses, payments, dues, backups, and reports from one admin panel.

## Features

- Product, category, stock, and low-stock management
- Sales orders with invoice print and PDF export
- Customer due and payment tracking
- Supplier, purchase, landed cost, and payment tracking
- China-to-Bangladesh/import costing fields and custom purchase costs
- Cash/bank accounts, expenses, and transaction ledger
- Role-based access control with custom roles
- Company profile, logo, currency, timezone, and date-format settings
- Audit logs for sensitive business changes
- CSV import/export for products, customers, and suppliers
- CSV/PDF business reports
- Database backup management
- Demo data seeder for product walkthroughs

## Tech Stack

- Laravel 12
- PHP 8.2+
- Filament 4
- Vite 6
- Tailwind CSS 4
- Node 20
- PHPUnit

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js 20
- MySQL, MariaDB, or SQLite for local development

## Installation

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

For local development, run:

```bash
php artisan serve
npm run dev
```

Open the Filament admin panel at:

```txt
/admin
```

## Environment

Set these values before seeding or deploying:

```env
APP_NAME="Business Dashboard"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

ADMIN_NAME="Super Admin"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=
```

`ADMIN_PASSWORD` is required by the database seeder. Use at least 12 characters with uppercase and lowercase letters, numbers, and symbols.

## Seed Data

Create the first admin user:

```bash
php artisan db:seed
```

Load demo business data:

```bash
php artisan db:seed --class=DemoDataSeeder
```

## Reports

Available report exports include:

- Sales report
- Purchase report
- Product profit report
- Stock report
- Low stock report
- Customer due report
- Supplier due report
- Expense report
- Account transaction report

## Security Notes

- Keep `.env` out of git.
- Set a strong `ADMIN_PASSWORD` before seeding or running `admin:ensure-super`.
- Only trusted users should receive `super_admin`.
- Financial delete actions are restricted.
- Review deployment secrets before enabling automatic production deploys.

## Deployment

Recommended production flow:

```bash
php artisan down --retry=60
php artisan backup:database
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

Do not run destructive migration commands or broad seeders against a live database after real business data exists. See [Production Update Safety](docs/update-safety.md).

Required GitHub Actions secrets for the included deploy workflow:

```txt
SERVER_HOST
SERVER_USER
SERVER_SSH_KEY
SERVER_PORT
DEPLOY_PATH
```

## Documentation

- [Project Guide](PROJECT_GUIDE.md)
- [ERP Phase Roadmap](ERP_PHASE_ROADMAP.md)
- [Business Dashboard Roadmap](business-dashboard-roadmap.md)
- [Audit Report](business_dashboard_audit_report.md)
- [Deployment Guide](docs/deployment.md)
- [Production Update Safety](docs/update-safety.md)
- [Release Policy](docs/release-policy.md)
- [Changelog](CHANGELOG.md)
- [Roles and Permissions](docs/roles-and-permissions.md)
- [Import and Export Guide](docs/import-export.md)
- [Backup and Restore Guide](docs/backup-restore.md)

## Roadmap

The recommended product path is:

1. Clean single-business installable product
2. Business-ready version tested with real users
3. White-label sellable product
4. SaaS version with multi-tenancy and subscription billing
