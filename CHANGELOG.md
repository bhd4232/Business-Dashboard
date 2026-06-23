# Changelog

All notable production changes to Business Dashboard are documented here.

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
