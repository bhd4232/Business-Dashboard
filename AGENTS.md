# Project Rules

- Do not create Git commits without the user's explicit permission.
- Before pushing changes to the GitHub repository, ask the user whether there are any additional changes to include.
- Do not push changes to GitHub without the user's explicit permission.
- If the user asks to keep an update ready for commit, document the update in `UPDATE_NOTES.md` with the date, reason, important changed files, and verification results so the user can identify what belongs in that commit.
- After every implementation, configuration, UI, or behavior change, update `CHANGELOG.md` in the same task with a concise entry under the appropriate `Unreleased` section (`Added`, `Changed`, `Fixed`, `Security`, or `Technical Notes`). Do not defer the release-note update to a later request. Documentation-only edits whose sole purpose is maintaining `CHANGELOG.md`, `UPDATE_NOTES.md`, `PROJECT_GUIDE.md`, or agent instructions do not require a recursive changelog entry.
- When adding or changing a module, update `PROJECT_GUIDE.md` with what changed, the important files/routes, and how to verify it. Do not leave major implementation details only in chat.
- Dashboard/admin UI must use Filament's default UI components and patterns only. If a custom dashboard module or element is needed, build it with Filament's default UI instead of custom-styled dashboard components.
