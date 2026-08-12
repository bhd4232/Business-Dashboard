<?php

namespace Tests\Feature;

use App\Filament\Clusters\Crm;
use App\Filament\Clusters\Finance;
use App\Filament\Clusters\Inventory;
use App\Filament\Clusters\Purchasing;
use App\Filament\Clusters\Reports as ReportsCluster;
use App\Filament\Clusters\Sales;
use App\Filament\Clusters\Settings;
use App\Filament\Clusters\Storefront;
use App\Filament\Pages\AiAssistantSettings;
use App\Filament\Pages\Backups;
use App\Filament\Pages\CloudStorageSettings;
use App\Filament\Pages\Inbox;
use App\Filament\Pages\MetaCapiSettings;
use App\Filament\Pages\ProductSetup;
use App\Filament\Pages\ReleaseNotes;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CompanyFaqs\CompanyFaqResource;
use App\Filament\Resources\ConversationChannels\ConversationChannelResource;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FundSources\FundSourceResource;
use App\Filament\Resources\FundTransfers\FundTransferResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ProductCarousels\ProductCarouselResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Quotations\QuotationResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StorefrontPages\StorefrontPageResource;
use App\Filament\Resources\StorefrontPayments\StorefrontPaymentResource;
use App\Filament\Resources\StorefrontSettings\StorefrontSettingResource;
use App\Filament\Resources\StorefrontSlides\StorefrontSlideResource;
use App\Filament\Resources\SupplierPayments\SupplierPaymentResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\TransactionLedgers\TransactionLedgerResource;
use App\Filament\Resources\UserRoles\UserRoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\Account;
use App\Models\FundTransfer;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CompanyContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminNavigationClustersTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_modules_use_native_collapsible_sidebar_submenus(): void
    {
        $components = [
            Storefront::class => [
                StorefrontSlideResource::class,
                StorefrontSettingResource::class,
                StorefrontPageResource::class,
                ProductCarouselResource::class,
                StorefrontPaymentResource::class,
                MetaCapiSettings::class,
            ],
            Crm::class => [
                LeadResource::class,
                QuotationResource::class,
                Inbox::class,
                ConversationChannelResource::class,
                AiAssistantSettings::class,
                CompanyFaqResource::class,
            ],
            Finance::class => [
                VoucherResource::class,
                FundSourceResource::class,
                FundTransferResource::class,
                AccountResource::class,
                ExpenseResource::class,
                ExpenseCategoryResource::class,
                TransactionLedgerResource::class,
            ],
            Sales::class => [
                CustomerResource::class,
                OrderResource::class,
                CustomerPaymentResource::class,
            ],
            Purchasing::class => [
                SupplierResource::class,
                PurchaseResource::class,
                SupplierPaymentResource::class,
            ],
            Inventory::class => [
                ProductResource::class,
                StockMovementResource::class,
                CategoryResource::class,
            ],
            ReportsCluster::class => [Reports::class],
            Settings::class => [
                UserResource::class,
                UserRoleResource::class,
                ProductSetup::class,
                AuditLogResource::class,
                Backups::class,
                CloudStorageSettings::class,
                ReleaseNotes::class,
            ],
        ];

        foreach ($components as $cluster => $clusteredComponents) {
            $this->assertFalse($cluster::shouldRegisterSubNavigation());

            foreach ($clusteredComponents as $component) {
                $this->assertSame($cluster, $component::getCluster());
            }
        }

        $this->assertSame('CRM', Crm::getClusterBreadcrumb());
        $this->assertSame('Inventory', Inventory::getNavigationLabel());
        $this->assertSame('Inventory', Inventory::getClusterBreadcrumb());
        $this->assertSame('Products', ProductResource::getNavigationLabel());
        $this->assertSame('Categories', CategoryResource::getNavigationLabel());
        $this->assertSame('Stock Movement', StockMovementResource::getNavigationLabel());
    }

    public function test_active_cluster_navigation_contains_authorized_child_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($admin->defaultCompany());
        $this->actingAs($admin);

        $financeItem = Finance::getNavigationItems()[0];
        $financeChildItems = collect($financeItem->getChildItems());
        $childLabels = $financeChildItems
            ->map(fn ($item): string => $item->getLabel())
            ->all();

        $this->assertContains('Vouchers', $childLabels);
        $this->assertContains('Accounts', $childLabels);
        $this->assertContains('Expenses', $childLabels);
        $this->assertNotContains('Fund Sources', $childLabels);
        $this->assertNotContains('Fund Transfers', $childLabels);
        $this->assertSame('Finance', $financeItem->getLabel());
        $this->assertNotNull($financeItem->getIcon());
        $this->assertNull($financeItem->getUrl());
        $this->assertSame('Finance', $financeItem->getExtraAttributes()['data-zz-sidebar-menu']);

        foreach ($financeChildItems as $financeChildItem) {
            $this->assertNull($financeChildItem->getGroup());
            $this->assertNotNull($financeChildItem->getIcon());
            $this->assertStringContainsString('zz-sidebar-child-item', $financeChildItem->getExtraAttributes()['class']);

            $encodedIcon = $financeChildItem->getExtraAttributes()['data-zz-sidebar-icon'];
            $iconMarkup = base64_decode($encodedIcon, strict: true);
            $iconDocument = new \DOMDocument;

            $this->assertIsString($iconMarkup);
            $this->assertTrue(@$iconDocument->loadXML($iconMarkup));
            $this->assertSame('svg', $iconDocument->documentElement->localName);
            $this->assertStringContainsString('zz-sidebar-child-icon', $iconDocument->documentElement->getAttribute('class'));
        }

        $productModuleLabels = collect(Inventory::getNavigationItems()[0]->getChildItems())
            ->map(fn ($item): string => $item->getLabel())
            ->all();

        $this->assertSame(['Products', 'Categories', 'Stock Movement'], $productModuleLabels);
    }

    public function test_storefront_cluster_uses_concise_site_navigation_labels(): void
    {
        $this->assertSame('Site', Storefront::getNavigationLabel());
        $this->assertSame('Site', Storefront::getClusterBreadcrumb());
        $this->assertSame('Hero Slides', StorefrontSlideResource::getNavigationLabel());
        $this->assertSame('Site Theme', StorefrontSettingResource::getNavigationLabel());
        $this->assertSame('Pages', StorefrontPageResource::getNavigationLabel());
        $this->assertSame('Homepage Carousels', ProductCarouselResource::getNavigationLabel());
        $this->assertSame('Payments', StorefrontPaymentResource::getNavigationLabel());
    }

    public function test_cluster_roots_open_the_first_authorized_page(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $company = $admin->defaultCompany();

        $routes = [
            '/admin/storefront' => 'filament.admin.storefront.resources.storefront-slides.index',
            '/admin/crm' => 'filament.admin.crm.resources.leads.index',
            '/admin/finance' => 'filament.admin.finance.resources.vouchers.index',
            '/admin/sales' => 'filament.admin.sales.resources.customers.index',
            '/admin/purchasing' => 'filament.admin.purchasing.resources.suppliers.index',
            '/admin/inventory' => 'filament.admin.inventory.resources.products.index',
            '/admin/accounts' => 'filament.admin.finance.resources.accounts.index',
            '/admin/reports' => 'filament.admin.reports.pages.reports',
            '/admin/settings' => 'filament.admin.settings.resources.users.index',
        ];

        foreach ($routes as $clusterUrl => $firstPageRoute) {
            $this->actingAs($admin)
                ->withSession(['current_company_id' => $company->getKey()])
                ->get($clusterUrl)
                ->assertRedirect(route($firstPageRoute));
        }
    }

    public function test_finance_navigation_uses_accounts_and_hides_legacy_fund_pages(): void
    {
        $this->assertTrue(AccountResource::shouldRegisterNavigation());
        $this->assertFalse(FundSourceResource::shouldRegisterNavigation());
        $this->assertFalse(FundTransferResource::shouldRegisterNavigation());
        $this->assertSame([], FundSourceResource::getPages());
        $this->assertSame([], FundTransferResource::getPages());
        $this->assertFalse(Route::has('filament.admin.finance.resources.fund-sources.index'));
        $this->assertFalse(Route::has('filament.admin.finance.resources.fund-transfers.index'));
    }

    public function test_vouchers_page_contains_the_merged_fund_transfers_table(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $admin->defaultCompany()->getKey()])
            ->get('/admin/finance/vouchers')
            ->assertOk()
            ->assertSee('Fund Transfers');

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $admin->defaultCompany()->getKey()])
            ->get('/admin/finance/vouchers/create')
            ->assertOk()
            ->assertSee('Voucher Type')
            ->assertSee('Fund Transfer')
            ->assertSee('Receiving Account')
            ->assertSee('Amount')
            ->assertSee('Confirmed Via')
            ->assertSee('Transaction / Reference ID')
            ->assertSee('Order Invoices')
            ->assertSee('Customer Account')
            ->assertSee('Notes & Attachments')
            ->assertDontSee('Payment Method')
            ->assertDontSee('Parties & Account');
    }

    public function test_voucher_form_creates_a_fund_transfer(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $company = $admin->defaultCompany();
        app(CompanyContext::class)->set($company);

        $from = Account::query()->create([
            'name' => 'Main Bank',
            'type' => 'bank',
            'opening_balance' => 10000,
        ]);
        $to = Account::query()->create([
            'name' => 'Petty Cash',
            'type' => 'cash',
            'opening_balance' => 0,
        ]);

        Livewire::actingAs($admin)
            ->test(CreateVoucher::class)
            ->fillForm([
                'type' => Voucher::TYPE_DEBIT,
                'transaction_type' => 'refund',
            ])
            ->assertSchemaComponentVisible('order_id')
            ->fillForm([
                'type' => 'fund_transfer',
                'from_account_id' => $from->getKey(),
                'to_account_id' => $to->getKey(),
                'amount' => 1500,
                'transaction_cost' => 25,
                'remarks' => 'Office cash float',
            ])
            ->assertSchemaComponentHidden('order_id')
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(VoucherResource::getUrl('index'));

        $this->assertDatabaseHas(FundTransfer::class, [
            'company_id' => $company->getKey(),
            'from_account_id' => $from->getKey(),
            'to_account_id' => $to->getKey(),
            'amount' => 1500,
            'transaction_cost' => 25,
            'requested_by' => $admin->getKey(),
            'status' => FundTransfer::STATUS_PENDING,
        ]);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_cluster_page_renders_filament_collapsible_sidebar_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['current_company_id' => $admin->defaultCompany()->getKey()])
            ->get('/admin/sales/customers')
            ->assertOk()
            ->assertSee('zz-sidebar-menu-parent', escape: false)
            ->assertSee('zz-sidebar-submenu-open', escape: false)
            ->assertSee('data-zz-sidebar-menu="Sales"', escape: false)
            ->assertSee('zz-sidebar-child-item', escape: false)
            ->assertSee('data-zz-sidebar-icon=', escape: false)
            ->assertSee('fi-body-has-sidebar-collapsible-on-desktop', escape: false)
            ->assertSee('zzHoverExpansionReady', escape: false)
            ->assertSee('__zzCategoryIconSearchReady', escape: false)
            ->assertSee('Customers')
            ->assertSee('Orders')
            ->assertSee('Customer Payments');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $sidebarNavigation = $xpath->query(
            '//nav[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-nav ")]'
        )->item(0);

        $this->assertNotNull($sidebarNavigation);

        $sidebarHtml = $document->saveHTML($sidebarNavigation);

        $this->assertStringContainsString('Dashboard', $sidebarHtml);
        $this->assertStringContainsString('data-zz-sidebar-menu="Sales"', $sidebarHtml);
        $this->assertStringContainsString('Customers', $sidebarHtml);
        $this->assertStringContainsString('Orders', $sidebarHtml);
        $this->assertStringContainsString('Customer Payments', $sidebarHtml);
        $this->assertTrue(Filament::getPanel('admin')->isSidebarCollapsibleOnDesktop());
        $this->assertFalse(Filament::getPanel('admin')->isSidebarFullyCollapsibleOnDesktop());
    }

    public function test_hidden_purchasing_resources_do_not_bypass_cluster_permissions(): void
    {
        $salesStaff = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($salesStaff)->get('/admin/purchasing')->assertForbidden();
    }

    public function test_settings_cluster_opens_the_page_available_to_non_admin_users(): void
    {
        $salesStaff = User::factory()->create([
            'role' => 'sales_staff',
            'is_active' => true,
        ]);

        $this->actingAs($salesStaff)
            ->get('/admin/settings')
            ->assertRedirect(route('filament.admin.settings.pages.release-notes'));
    }

    public function test_legacy_cluster_links_preserve_deep_paths_and_query_strings(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/inbox?conversation=4&channel=whatsapp')
            ->assertRedirect('/admin/crm/inbox?channel=whatsapp&conversation=4');

        $this->actingAs($admin)
            ->get('/admin/orders/99/edit?tab=items')
            ->assertRedirect('/admin/sales/orders/99/edit?tab=items');
    }

    public function test_legacy_fund_pages_redirect_to_their_merged_finance_destinations(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/finance/fund-sources?search=bank')
            ->assertRedirect('/admin/finance/accounts?search=bank');

        $this->actingAs($admin)
            ->get('/admin/finance/fund-transfers/create?from=2')
            ->assertRedirect('/admin/finance/vouchers?from=2');
    }

    public function test_legacy_report_filters_survive_the_cluster_root_redirect(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/reports?report_type=profit&date_from=2026-07-01')
            ->assertRedirect('/admin/reports/reports?date_from=2026-07-01&report_type=profit');
    }

    public function test_existing_custom_admin_routes_are_not_captured_by_legacy_redirects(): void
    {
        $this->assertSame(
            'orders.pdf',
            Route::getRoutes()->match(Request::create('/admin/orders/99/pdf'))->getName(),
        );
        $this->assertSame(
            'products.export.csv',
            Route::getRoutes()->match(Request::create('/admin/products/export/csv'))->getName(),
        );
        $this->assertSame(
            'backups.download',
            Route::getRoutes()->match(Request::create('/admin/backups/download/example.zip'))->getName(),
        );
    }
}
