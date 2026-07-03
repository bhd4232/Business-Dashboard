# Project Rules

- Do not create Git commits without the user's explicit permission.
- Before pushing changes to the GitHub repository, ask the user whether there are any additional changes to include.
- Do not push changes to GitHub without the user's explicit permission.
- If the user asks to keep an update ready for commit, document the update in `UPDATE_NOTES.md` with the date, reason, important changed files, and verification results so the user can identify what belongs in that commit.
- When adding or changing a module, update `PROJECT_GUIDE.md` with what changed, the important files/routes, and how to verify it. Do not leave major implementation details only in chat.
- Dashboard/admin UI must use Filament's default UI components and patterns only. If a custom dashboard module or element is needed, build it with Filament's default UI instead of custom-styled dashboard components.
