# ZamZam ERP - Phase-Based Master Roadmap

This roadmap defines how the ZamZam ERP project should mature phase by phase. Before marking any phase complete, code, tests, manual flows, permissions, reports, deployment impact, and documentation must be verified.

Related planning documents:

- `PROJECT_GUIDE.md` provides developer and maintainer onboarding context.
- `business-dashboard-roadmap.md` keeps detailed correction notes and improvement planning.
- `ECOMMERCE_PLAN.md` contains the detailed e-commerce specification for Phase 10 onward.

---

## Phase 0: Project Stabilization

**Goal:** Stabilize the existing Laravel and Filament project so future ERP modules can be built without old blockers.

**Status:** Done.

**Completed:**

- Laravel 12 and Filament 4 admin structure stabilized.
- Product mass assignment issues fixed.
- Core model relationships reviewed.
- Historical no-op migrations documented.
- Basic project guide and roadmap added.
- Test workflow established.

**Done Criteria:**

- `php artisan test` passes.
- Core admin panel loads.
- A new developer can understand the project from the documentation.

---

## Phase 1: Product and Inventory Foundation

**Goal:** Build the product catalog and stock tracking foundation.

**Status:** Done.

**Completed:**

- Category module.
- Product module.
- Product details: description, barcode, unit, brand, cost price, sale price, reorder level, VAT, image, and active status.
- Product status: `available`, `coming_soon`.
- Coming Soon placeholder product support.
- Product table filters: category, status, active/inactive, low stock, and brand.
- Stock Movement module.
- Stock recalculation from movements.
- Opening stock backfill.
- Sale stock validation.
- Signed adjustment movement support.
- Product view stock movement history.
- Tests for stock movement behavior.

**Future Polish:**

- Stock reconciliation command or report.
- Better stock movement approval flow.
- More user-friendly validation messages.

**Done Criteria:**

- Product CRUD works.
- Stock history is visible.
- Insufficient stock is blocked.
- Low stock products can be found.
- `php artisan test --filter=StockMovementTest` passes.

---

## Phase 2: Supplier, Purchase, and China-to-BD Costing

**Goal:** Build supplier purchase flow and China-to-Bangladesh wholesale purchase costing.

**Status:** Done, with optional future costing refinements.

**Completed:**

- Supplier module.
- Purchase module.
- Purchase Items.
- Purchase statuses: `draft`, `received`, `cancelled`.
- Received purchases increase stock.
- Draft and cancelled purchases do not affect stock.
- Cancelling a received purchase removes stock movement when safe.
- Cancellation is blocked if stock would go negative.
- Optional product cost price update from purchase item cost.
- Supplier current balance syncs from received purchase due minus supplier payments.
- Purchase form sections are collapsible.
- China-to-BD fixed cost fields:
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
- Fixed cost fields are optional and included in total and due.
- Add new custom cost field button.
- Custom field modal accepts field name and amount.
- Custom fields stored in `purchases.custom_costs`.
- Custom costs included in purchase total and due.
- Custom Fields UI remains hidden until custom fields exist.
- View Purchase shows fixed and custom costs.
- Purchase list exposes China-to-BD cost total and custom field summary.
- Purchase report dynamically shows custom cost labels as columns.
- Purchase CSV export includes fixed cost columns and dynamic custom cost columns.
- Tests cover purchase totals, stock, supplier balance, custom costs, and reports.
- Supplier field in purchase creation supports selecting existing suppliers and inline creation.
- Product field in purchase items supports selecting existing products and inline creation.
- Per-product landed costs are allocated and stored on purchase items.
- Purchase LC, PI, and CI reference/date tracking is available.
- Company-scoped container and shipment tracking is available in Filament.

**Important Implementation Notes:**

- Fixed purchase costs are columns on `purchases`.
- Custom purchase costs are JSON in `purchases.custom_costs`.
- Purchase custom costs are purchase-level costs, not product-line costs.
- Coming Soon placeholder products are inactive and should not appear in active product dropdowns.

**Future Work:**

- China supplier and local clearing agent separation.
- Purchase expense category mapping.
- Better purchase costing summary widget.

**Done Criteria:**

- Supplier CRUD works.
- Purchase create, edit, view, and list workflows work.
- Fixed and custom China-to-BD costs save correctly.
- Purchase totals include all purchase-level costs.
- Received purchases update stock.
- Purchase reports and exports include dynamic custom fields.
- `php artisan test --filter=PurchaseTest` passes.
- `php artisan test --filter=ReportsTest` passes.

---

## Phase 3: Sales and Order Management

**Goal:** Make the customer sales and order flow production-ready.

**Status:** Done.

**Completed:**

- Customer module.
- Multi-product sales invoice workflow.
- Order Items are the source of invoice lines.
- Order totals from items, discount, VAT, and paid amount.
- Confirmed and completed invoices create sale stock movements.
- Draft and cancelled invoices do not affect stock.
- Customer current balance syncs from invoice due minus payments.
- Printable invoice page at `/admin/orders/{order}/print`.
- Tests for sales totals, stock sync, customer due, and insufficient stock blocking.

**Future Work:**

- PDF invoice export.
- Return and refund workflow.
- Delivery challan.
- Customer credit limit.

**Done Criteria:**

- Multi-product sale works.
- Stock decreases only for confirmed or completed invoices.
- Customer due is visible.
- Printable invoice works.
- `php artisan test --filter=SalesOrderTest` passes.

---

## Phase 4: Accounts and Payments

**Goal:** Track business cash flow, due amounts, expenses, and payments.

**Status:** Done.

**Completed:**

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

**Future Work:**

- Bank reconciliation.
- Transfer between accounts.
- Cheque or payment status tracking.
- Payment receipt print and export.

**Done Criteria:**

- Cash and bank balances are visible.
- Customer and supplier due can be managed.
- Every money movement creates a ledger entry.
- Negative account balance is blocked where needed.
- `php artisan test --filter=AccountsAndPaymentsTest` passes.

---

## Phase 5: Ledger, Dashboard, and Reporting

**Goal:** Help the owner and admin understand business health from dashboard and reports.

**Status:** Done, with future reporting improvements planned.

**Completed:**

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
- Purchase report and export include dynamic custom cost fields.
- Report export permission protection.
- Tests for reports and CSV export.

**Future Work:**

- Daily summary report.
- Monthly profit/loss report.
- Purchase landed cost report per product.
- Supplier-wise purchase analysis.
- Customer-wise sales analysis.
- PDF exports.
- Charts for sales, purchase, due, and profit trends.

**Done Criteria:**

- Dashboard shows key metrics.
- Reports match transaction data.
- CSV export works for core reports.
- Purchase dynamic custom fields appear in report and export.
- `php artisan test --filter=ReportsTest` passes.

---

## Phase 6: User, Role, Permission, and Audit

**Goal:** Make ERP access secure, role-based, and traceable.

**Status:** Done.

**Completed:**

- User role fields.
- User management resource.
- Default roles:
  - Super Admin
  - Manager
  - Sales Staff
  - Inventory Staff
  - Accountant
- Custom user role model and table.
- Role creation from the user management flow.
- Default roles remain available while custom roles can be added with the plus action.
- Gate-based resource access.
- Reports view and export permission.
- Inactive user block.
- Audit logs for core business model create, update, and delete actions.
- Audit Log resource for Super Admin.
- Audit detail view.
- Self-deactivation protection.
- Last active Super Admin protection.
- Sensitive edit and delete restrictions for payments, stock movements, accounts, expenses, and order deletion.
- Tests for permission and audit flows.

**Future Work:**

- More granular permission UI.
- Approval workflow for high-risk actions.
- Login and session audit.

**Done Criteria:**

- Different roles see permitted modules only.
- Critical changes are traceable.
- Report export is protected.
- `php artisan test --filter=PhaseSixPermissionsTest` passes.

---

## Phase 7: Business Automation

**Goal:** Automate repetitive business follow-up work.

**Status:** In progress.

**Completed:**

- Rule-based Customer Success and Risk Score profiles.
- Delivered/returned/cancelled courier ratios.
- Explainable per-order risk check snapshots.
- Global and company-specific blacklist management.
- Blacklist enforcement before courier booking.
- Risk badges and booking-time score visibility.
- Idempotent delivery risk events.
- Dashboard Customer Success & Risk stats and high-risk/blacklisted alert table.
- Manager approval workflow for high-risk courier booking.
- Owner approval workflow for blacklisted courier booking.
- Configurable risk rule thresholds and deduction weights.
- Risk review and risk event admin visibility.

**Ideas:**

- Low stock notifications.
- Due payment reminders.
- Daily sales and purchase summary.
- Better sequential invoice and purchase numbers.
- Product barcode generation and printing.
- Stock adjustment approval.
- Purchase arrival reminder.
- Supplier payable reminders.
- Additional automation rules after storefront traffic starts.

**Done Criteria:**

- Admin dashboard becomes actionable.
- Manual follow-up decreases.
- Automated jobs are tested and observable.

---

## Phase 8: UI/UX Polish and Production Readiness

**Goal:** Make the ERP clean, usable, fast, and deployable.

**Status:** In progress.

**Completed:**

- Purchase form sections made collapsible.
- Courier Provider `Delivery Partner` section made collapsible.
- Repeated `Courier` wording removed from the four menu labels inside the `Courier` sidebar group.
- China-to-BD cost UI added.
- Custom Fields section hidden when empty.
- Coolify deployment guidance documented in `PROJECT_GUIDE.md`.
- Public login link added for admin access.
- Node and Nixpacks deployment settings adjusted for production.
- Frontend production build verified with Vite 7.
- Node dependency audit cleaned with safe package upgrades and overrides.

**Tasks:**

- Decide final language consistency for admin UI.
- Clean remaining UI labels.
- Improve empty states.
- Polish print templates.
- Optimize large tables.
- Add backup and recovery plan.
- Add production monitoring and logging notes.
- Add database backup schedule.
- Add deployment checklist for Coolify.

**Done Criteria:**

- Non-technical admin users can use the system comfortably.
- Deployment documentation exists.
- Backup and recovery plan exists.
- `npm run build` passes.

---

## Phase 9: Production Operations

**Goal:** Stabilize production hosting, backups, deployment, and maintenance process.

**Status:** Planned.

**Recommended Deployment Stack:**

- GitHub repository.
- Coolify application.
- Nixpacks build.
- MySQL or MariaDB database.
- Persistent storage for Laravel storage.
- HTTPS domain.

**Production Checklist:**

- `APP_ENV=production`
- `APP_DEBUG=false`
- Valid `APP_KEY`
- Correct `APP_URL`
- Database environment variables set
- `php artisan migrate --force`
- `php artisan storage:link`
- Laravel caches generated after deploy
- Storage persisted
- Database backup configured
- Admin password changed

**Done Criteria:**

- GitHub push triggers deployment.
- Database migrations run safely.
- Uploads persist after redeploy.
- Backups can be restored.

---

## Phase 10: E-Commerce Foundation

**Goal:** Launch the public storefront structure, product catalog, core API, and basic SEO foundation.

**Status:** Planned.

**Tasks:**

- Finalize frontend stack decision.
- Add public route structure.
- Build homepage and storefront layout.
- Build category pages.
- Build product catalog pages.
- Add search, filters, sorting, and pagination.
- Build product detail page.
- Show stock availability and price clearly.
- Add product and category API endpoints if needed.
- Add SEO-friendly product and category URLs.
- Add basic localization structure.

**Done Criteria:**

- Customers can browse products publicly.
- Search and filters work.
- Product details are visible.
- Stock visibility is accurate.
- Storefront is usable on mobile.

---

## Phase 11: Cart, Checkout, and Orders

**Goal:** Allow customers to create carts, check out, place orders, and track order status.

**Status:** Planned.

**Tasks:**

- Shopping cart for guest and logged-in customers.
- Checkout flow.
- Customer registration and login.
- Customer address management.
- Order placement.
- Customer order history.
- Order tracking by account or order number.
- Email or SMS order notifications.
- Cash on delivery.
- Initial payment gateway integration.

**Done Criteria:**

- Customers can place an order from the storefront.
- Orders are saved with customer, item, address, payment, and shipping details.
- Customers can track their order.
- Admin users can see incoming e-commerce orders.

---

## Phase 12: Admin E-Commerce Control

**Goal:** Give admin users full control over e-commerce operations from the ERP panel.

**Status:** Planned.

**Tasks:**

- E-commerce dashboard.
- Order management dashboard.
- Order status flow: pending, processing, shipped, delivered, cancelled, returned, refunded.
- Banner and slider management.
- Featured product management.
- Discount and coupon management.
- Shipping method and rate management.
- Payment verification.
- PDF invoice generation.
- Admin audit trail for e-commerce actions.

**Done Criteria:**

- Admin users can manage the full e-commerce order lifecycle.
- Payment verification is traceable.
- Stock and invoice behavior are consistent with ERP rules.
- Reports include e-commerce activity where relevant.

---

## Phase 13: Advanced E-Commerce Features

**Goal:** Add stronger customer engagement, marketing, and support workflows.

**Status:** Planned.

**Tasks:**

- Product reviews and ratings.
- Wishlist.
- Product comparison.
- Related products.
- Promotional campaigns.
- Abandoned cart recovery.
- Support ticket system.
- Return and refund workflow.
- Back-in-stock notifications.
- Product variants such as size and color.
- Analytics integration.

**Done Criteria:**

- Customers have useful account and engagement features.
- Marketing features can be managed by admin users.
- Returns and refunds are controlled and auditable.

---

## Phase 14: Scale and Optimization

**Goal:** Prepare the ERP and e-commerce platform for higher production traffic and operational maturity.

**Status:** Planned.

**Tasks:**

- CDN integration.
- Redis cache optimization.
- Database index optimization.
- Image CDN and resize pipeline.
- Load testing.
- Monitoring setup.
- Queue-based notifications.
- Background job monitoring.
- Backup restore testing.
- Performance review of large reports and product listings.

**Done Criteria:**

- Storefront and admin panel perform reliably under expected traffic.
- Background jobs are observable.
- Backups are tested.
- Performance bottlenecks are documented and addressed.

---

## Recommended Immediate Next Work

Priority:

1. Do a manual purchase costing smoke test in the target production environment.
2. Decide final production hosting and domain.
3. Run a supervised backup restore drill on disposable infrastructure.
4. Obtain official API access/contracts for Pathao, RedX, and E-Courier before implementing their live adapters.
5. Reassign verified Main Company production data using a reviewed dry-run mapping and mandatory backup.
6. Start Phase 10 e-commerce foundation after these operational readiness items are stable.

---

## Development Rule for Every Phase

Every new module or business feature should follow this pattern:

1. Migration.
2. Model.
3. Relationships.
4. Filament Resource or controller.
5. Form schema.
6. Table columns and filters.
7. Infolist, detail page, or view.
8. Business logic or service.
9. Report or export updates if relevant.
10. Permission and audit checks if relevant.
11. Automated tests.
12. Manual verification.
13. `PROJECT_GUIDE.md` update.
14. `ERP_PHASE_ROADMAP.md` update.
15. `ECOMMERCE_PLAN.md` update when the work affects e-commerce.
16. Ask the user before pushing to confirm whether there are additional changes to include.

---

## Phase Completion Checklist

Before marking any phase complete:

- `php artisan test`
- `npm run build`
- Admin CRUD manual test
- Customer-facing flow test if relevant
- Data calculation check
- Report and export check
- Permission and security check
- Audit check if relevant
- Deployment impact check
- Documentation update
