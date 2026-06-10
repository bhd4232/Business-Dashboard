# ZamZam ERP - Phase Based Roadmap

এই roadmap অনুযায়ী project ধাপে ধাপে mature হবে। প্রতিটি phase শেষ করার আগে code, tests, manual flow, permissions, reports, and documentation verify করতে হবে।

## Phase 0: Project Stabilization

Goal: Existing Laravel/Filament project stable করা, যাতে future ERP modules build করতে গিয়ে পুরনো blocker না থাকে।

Status: Done.

Completed:

- Laravel 12 + Filament 4 admin structure stabilized.
- Product mass assignment issues fixed.
- Core model relationships reviewed.
- Historical no-op migrations documented.
- Basic project guide and roadmap added.
- Test workflow established.

Done criteria:

- `php artisan test` passes.
- Core admin panel loads.
- New developer can understand project from docs.

## Phase 1: Product and Inventory Foundation

Goal: Product catalog and stock tracking foundation তৈরি করা।

Status: Done.

Completed:

- Category module.
- Product module.
- Product details: description, barcode, unit, brand, cost price, sale price, reorder level, VAT, image, active status.
- Product status: `available`, `coming_soon`.
- Coming Soon placeholder product support.
- Product table filters: category, status, active/inactive, low stock, brand.
- Stock Movement module.
- Stock recalculation from movements.
- Opening stock backfill.
- Sale stock validation.
- Signed adjustment movement support.
- Product view stock movement history.
- Tests for stock movement behavior.

Future polish:

- Stock reconciliation command/report.
- Better stock movement approval flow.
- More user-friendly validation messages.

Done criteria:

- Product CRUD works.
- Stock history is visible.
- Insufficient stock is blocked.
- Low stock products can be found.
- `php artisan test --filter=StockMovementTest` passes.

## Phase 2: Supplier, Purchase, and China-to-BD Costing

Goal: Supplier purchase flow and China-to-BD wholesale purchase costing তৈরি করা।

Status: Mostly done and actively evolving.

Completed:

- Supplier module.
- Purchase module.
- Purchase Items.
- Purchase statuses: `draft`, `received`, `cancelled`.
- Received purchases increase stock.
- Draft/cancelled purchases do not affect stock.
- Cancelling received purchase removes stock movement when safe.
- Cancellation is blocked if stock would go negative.
- Optional product cost price update from purchase item cost.
- Supplier current balance syncs from received purchase due minus supplier payments.
- Purchase form sections are collapsible.
- China to BD fixed cost fields added:
  - Machine Purchase
  - Inspection
  - Freight to Ctg
  - Duty
  - C&F
  - Misc
  - Truck
  - Load & Unload
  - Spare Parts
  - CAM
  - Positive Feeder
  - Cylinder
- Fixed cost fields are optional and included in total/due.
- Add new custom cost field button added.
- Custom field modal accepts field name and amount.
- Custom fields stored in `purchases.custom_costs`.
- Custom costs included in purchase total/due.
- Custom Fields UI remains hidden until custom fields exist.
- View Purchase shows fixed and custom costs.
- Purchase list exposes China-to-BD cost total and custom field summary.
- Purchase report dynamically shows custom cost labels as columns.
- Purchase CSV export includes fixed cost columns and dynamic custom cost columns.
- Tests cover purchase totals, stock, supplier balance, custom costs, and reports.

Important implementation notes:

- Fixed purchase costs are columns on `purchases`.
- Custom purchase costs are JSON in `purchases.custom_costs`.
- Purchase custom costs are purchase-level costs, not product lines.
- Coming Soon placeholder products are inactive and should not appear in active product dropdowns.

Future work:

- Per-product landed cost allocation.
- Purchase LC/PI/CI document tracking.
- Container/shipment tracking.
- China supplier and local clearing agent separation.
- Purchase expense category mapping.
- Better purchase costing summary widget.

Done criteria:

- Supplier CRUD works.
- Purchase create/edit/view/list works.
- Fixed and custom China-to-BD costs save correctly.
- Purchase totals include all purchase-level costs.
- Received purchases update stock.
- Purchase reports/export include dynamic custom fields.
- `php artisan test --filter=PurchaseTest` passes.
- `php artisan test --filter=ReportsTest` passes.

## Phase 3: Sales and Order Management

Goal: Customer sales/order flow production-ready করা।

Status: Done.

Completed:

- Customer module.
- Multi-product sales invoice workflow.
- Order Items are the source of invoice lines.
- Order totals from items, discount, VAT, and paid amount.
- Confirmed/completed invoices create sale stock movements.
- Draft/cancelled invoices do not affect stock.
- Customer current balance syncs from invoice due minus payments.
- Printable invoice page at `/admin/orders/{order}/print`.
- Tests for sales totals, stock sync, customer due, and insufficient stock blocking.

Future work:

- PDF invoice export.
- Return/refund workflow.
- Delivery challan.
- Customer credit limit.

Done criteria:

- Multi-product sale works.
- Stock decreases only for confirmed/completed invoices.
- Customer due is visible.
- Printable invoice works.
- `php artisan test --filter=SalesOrderTest` passes.

## Phase 4: Accounts and Payments

Goal: Business cash flow, due, expense, payment tracking করা।

Status: Done.

Completed:

- Accounts module.
- Customer Payments.
- Supplier Payments.
- Expense Categories.
- Expenses.
- Transaction Ledger.
- Account current balance sync.
- Customer and supplier overpayment protection.
- Supplier payments and expenses blocked if account balance would go negative.
- Expense category inline creation.
- Account view transaction history.
- Tests for payments, expenses, ledger entries, balances, and admin pages.

Future work:

- Bank reconciliation.
- Transfer between accounts.
- Cheque/payment status tracking.
- Payment receipt print/export.

Done criteria:

- Cash/bank balances are visible.
- Customer/supplier due can be managed.
- Every money movement creates ledger entry.
- Negative account balance is blocked where needed.
- `php artisan test --filter=AccountsAndPaymentsTest` passes.

## Phase 5: Ledger, Dashboard, and Reporting

Goal: Owner/admin can understand business health from dashboard and reports.

Status: Done, with future reporting improvements planned.

Completed:

- `ReportService` centralizes report calculations.
- Dashboard business overview widget.
- Reports page.
- Date range filters.
- CSV exports.
- Sales report.
- Purchase report.
- Product profit report.
- Stock report.
- Low stock report.
- Customer due report.
- Supplier due report.
- Expense report.
- Account ledger report.
- Purchase report includes China-to-BD cost total.
- Purchase report/export includes dynamic custom cost fields.
- Report export permission protection.
- Tests for reports and CSV export.

Future work:

- Daily summary report.
- Monthly profit/loss report.
- Purchase landed cost report per product.
- Supplier-wise purchase analysis.
- Customer-wise sales analysis.
- PDF exports.
- Charts for sales, purchase, due, and profit trends.

Done criteria:

- Dashboard shows key metrics.
- Reports match transaction data.
- CSV export works for core reports.
- Purchase dynamic custom fields appear in report/export.
- `php artisan test --filter=ReportsTest` passes.

## Phase 6: User, Role, Permission, and Audit

Goal: ERP access secure and role-based করা।

Status: Done.

Completed:

- User role fields.
- User management resource.
- Roles:
  - Super Admin
  - Manager
  - Sales Staff
  - Inventory Staff
  - Accountant
- Gate-based resource access.
- Reports view/export permission.
- Inactive user block.
- Audit logs for core business model create/update/delete.
- Audit Log resource for Super Admin.
- Audit detail view.
- Self-deactivation protection.
- Last active Super Admin protection.
- Sensitive edit/delete restrictions for payments, stock movements, accounts, expenses, and order deletion.
- Tests for permission and audit flows.

Future work:

- More granular permission UI.
- Approval workflow for high-risk actions.
- Login/session audit.

Done criteria:

- Different roles see permitted modules only.
- Critical changes are traceable.
- Report export is protected.
- `php artisan test --filter=PhaseSixPermissionsTest` passes.

## Phase 7: Business Automation

Goal: Repetitive work automate করা।

Status: Planned.

Ideas:

- Low stock notifications.
- Due payment reminders.
- Daily sales/purchase summary.
- Better sequential invoice and purchase numbers.
- Product barcode generation/printing.
- Stock adjustment approval.
- Purchase arrival reminder.
- Supplier payable reminders.

Done criteria:

- Admin dashboard becomes actionable.
- Manual follow-up decreases.
- Automated jobs are tested and observable.

## Phase 8: UI/UX Polish and Production Readiness

Goal: ERP usable, clean, fast, and deployable করা।

Status: In progress.

Completed:

- Purchase form sections made collapsible.
- China-to-BD cost UI added.
- Custom Fields section hidden when empty.
- Coolify deployment guidance documented in `PROJECT_GUIDE.md`.

Tasks:

- Decide Bengali/English language consistency.
- Clean remaining UI labels.
- Improve empty states.
- Polish print templates.
- Optimize large tables.
- Add backup/recovery plan.
- Add production monitoring/logging notes.
- Add database backup schedule.
- Add deployment checklist for Coolify.

Done criteria:

- Non-technical admin can use the system comfortably.
- Deployment documentation exists.
- Backup and recovery plan exists.
- `npm run build` passes.

## Phase 9: Production Operations

Goal: Production hosting, backups, and maintenance process stable করা।

Status: Planned.

Recommended deployment stack:

- GitHub repository
- Coolify application
- Nixpacks build
- MySQL/MariaDB database
- Persistent storage for Laravel storage
- HTTPS domain

Production checklist:

- `APP_ENV=production`
- `APP_DEBUG=false`
- Valid `APP_KEY`
- Correct `APP_URL`
- Database env vars set
- `php artisan migrate --force`
- `php artisan storage:link`
- Laravel caches generated after deploy
- Storage persisted
- Database backup configured
- Admin password changed

Done criteria:

- GitHub push triggers deployment.
- Database migrations run safely.
- Uploads persist after redeploy.
- Backups can be restored.

## Recommended Immediate Next Work

Priority:

1. Run `npm run build` and fix any frontend build issue.
2. Do manual purchase costing smoke test.
3. Add landed-cost allocation per product.
4. Add shipment/container tracking.
5. Add PDF export for invoice and purchase report.
6. Add backup/restore documentation.
7. Decide final production hosting and domain.

## Development Rule for Every Phase

Every new module or business feature should follow this pattern:

1. Migration.
2. Model.
3. Relationships.
4. Filament Resource.
5. Form schema.
6. Table columns and filters.
7. Infolist/view.
8. Business logic or service.
9. Report/export updates if relevant.
10. Permission/audit checks if relevant.
11. Automated tests.
12. Manual verification.
13. `PROJECT_GUIDE.md` update.
14. `ERP_PHASE_ROADMAP.md` update.

## Phase Completion Checklist

Before marking any phase complete:

- `php artisan test`
- `npm run build`
- Admin CRUD manual test
- Data calculation check
- Report/export check
- Permission/security check
- Audit check if relevant
- Deployment impact check
- Documentation update
