# Agent Rules (Claude Code)

These rules are mandatory for any AI agent working in this repository.

## Release notes are part of every commit

- **Before any `git commit` / `git push`, write the release note first.** Add or update the entry in `CHANGELOG.md` describing the change (follow `docs/release-policy.md`: categorize as major, minor, patch, security, hotfix, or maintenance, with Added/Fixed/Security/Technical Notes sections). A commit without a matching CHANGELOG entry is not allowed.
- Also keep the working log in `UPDATE_NOTES.md` current — it describes what a pending commit contains, ending with the commit status line.

## Commit policy

- Never `git commit` or `git push` without the owner's explicit approval in the conversation.
- Never run destructive commands (`migrate:fresh`, broad seeders) against non-demo databases.

## Business rules

- Never invent demo/placeholder business rules — real rules only, gathered from the owner. External credentials (payment, SMS, WhatsApp, WooCommerce) are always admin-configurable encrypted settings fields; the owner plugs in keys.
- Storefront-visible content must be manageable from the Filament admin panel, never hardcoded in views.

## Multi-company isolation

- Every new company-owned model must use `BelongsToCompany` + `CompanyScope` and be added to `MultiCompanyIsolationTest::test_every_company_owned_model_uses_the_company_scope_contract`.
- `SetCurrentCompany` must stay pinned before `SubstituteBindings` in `bootstrap/app.php`; new queued jobs set/clear `CompanyContext` explicitly; scheduled commands loop per company.

## Verification before handoff

- Run the affected test files plus the full `php artisan test` suite, and `npm run build` when frontend assets change.
