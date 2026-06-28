# Changelog

All notable production changes to Business Dashboard are documented here.

## [1.2.0] - 2026-06-24

**Release type:** Minor Version Update

### Added

- Added explainable Customer Success and Risk Score profiles, courier success/return/cancel ratios, immutable order check history, and idempotent delivery events.
- Added global/company blacklist management and booking-time blacklist enforcement.
- Added Customer and Order risk badges plus booking-form risk visibility.
- Added Customer Success dashboard alerts, risk review approvals, risk event visibility, and configurable rule settings.

### Technical Notes

- Added disposable SQLite backup restore verification through `php artisan backup:verify`.
- Bulk Main Company data reassignment is intentionally not planned; new records will be entered under the correct company and rare historical exceptions reviewed manually.

## [1.1.0] - 2026-06-23

**Release type:** Minor Version Update

### Added

- Added backup-gated, dry-run company data reassignment tooling with transactional child-record migration.
- Added complete company-owned model isolation contract coverage.
- Added courier provider contracts, manager, Manual and Steadfast adapters, API retry/timeouts, signed idempotent queued webhooks, operational log resources, booking actions, and report aggregates.
- Added company-scoped shipment and container tracking inside each Purchase record, with status-aware draft planning and read-only received/cancelled history.

### Technical Notes

- Live Pathao, RedX, and E-Courier adapters require their official current API contracts and merchant credentials.
- Production company-data reassignment must use a reviewed mapping and pre-migration backup.

### Fixed

- Fixed Filament select, textarea, and checkbox components failing behind an HTTPS reverse proxy because lazy-loaded JavaScript URLs were generated with `http://`.

## [1.0.0] - 2026-06-21

**Release type:** Major Version Update

### Added

- Added visible app release metadata with version, release type, release date, and source commit support.
- Added an admin Release Notes page so deployed changes are visible inside the app.
- Added release policy documentation for major, minor, patch, security, hotfix, and maintenance releases.

### Fixed

- Fixed Top Business Performers cards so light mode uses light backgrounds while dark mode remains preserved.

### Technical Notes

- Added production update safety documentation covering backups, migrations, rollback, and forbidden destructive commands.
- Production updates must create a database backup before running migrations.
- Routine production deploys must not run seeders or destructive migration commands against live data.
