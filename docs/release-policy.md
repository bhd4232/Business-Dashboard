# Release Policy

Business Dashboard uses semantic versioning:

```txt
MAJOR.MINOR.PATCH
```

## Version Types

| Type | Example | Use when |
|---|---:|---|
| Major Version Update | `2.0.0` | Breaking behavior, major workflow changes, or schema changes that need special release planning |
| Minor Feature Update | `1.1.0` | New backward-compatible features, pages, reports, or settings |
| Patch/Fix Update | `1.0.1` | Backward-compatible bug fixes and small UI fixes |
| Critical Fix Update | `1.0.2` | Urgent production bug fix that must be deployed quickly |
| Security Update | `1.0.3` | Security dependency update, auth fix, permission fix, or vulnerability mitigation |
| Hotfix Update | `1.0.4` | Small emergency fix prepared from the current live branch |
| Maintenance Update | `1.0.5` | Documentation, build, backup, or operational-only changes |

## Required Release Files

Every production release must update:

- `APP_VERSION`, `APP_RELEASE_TYPE`, and `APP_RELEASE_DATE` in production environment values.
- `CHANGELOG.md` with what was added, changed, fixed, secured, or migrated.
- GitHub commit message, tag, or release notes with the same version and summary.
- Deployment notes when migrations, queues, cron, storage, or environment values change.

## GitHub Release Flow

1. Confirm tests pass locally or in CI.
2. Confirm a production database backup exists.
3. Commit with a release-focused message.
4. Tag the release:

```bash
git tag -a v1.0.0 -m "v1.0.0"
git push origin v1.0.0
```

5. Create a GitHub release from the tag and paste the matching `CHANGELOG.md` entry.

## Data Safety Rules

- Use only forward migrations in production.
- Do not use `migrate:fresh`, `migrate:refresh`, `migrate:reset`, or `db:wipe` on live data.
- Do not run broad seeders on a live database after real customer, purchase, product, or user data exists.
- Back up before deploy, then run `php artisan migrate --force`.
- Test restore steps before relying on backups.
