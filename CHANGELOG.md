# Changelog

All notable production changes to Business Dashboard are documented here.

## [1.1.0] - 2026-06-23

**Release type:** Minor Version Update

### Added

- Added backup-gated, dry-run company data reassignment tooling with transactional child-record migration.
- Added complete company-owned model isolation contract coverage.
- Added courier provider contracts, manager, Manual and Steadfast adapters, API retry/timeouts, signed idempotent queued webhooks, operational log resources, booking actions, and report aggregates.
- Added company-scoped shipment and container tracking inside each Purchase record, with status-aware draft planning and read-only received/cancelled history.

### Operations

- Live Pathao, RedX, and E-Courier adapters require their official current API contracts and merchant credentials.
- Production company-data reassignment must use a reviewed mapping and pre-migration backup.

## [1.0.0] - 2026-06-21

**Release type:** Major Version Update

### Added

- Added visible app release metadata with version, release type, release date, and source commit support.
- Added an admin Release Notes page so deployed changes are visible inside the app.
- Added production update safety documentation covering backups, migrations, rollback, and forbidden destructive commands.
- Added release policy documentation for major, minor, patch, security, hotfix, and maintenance releases.

### Fixed

- Fixed Top Business Performers cards so light mode uses light backgrounds while dark mode remains preserved.

### Operations

- Production updates must create a database backup before running migrations.
- Routine production deploys must not run seeders or destructive migration commands against live data.
