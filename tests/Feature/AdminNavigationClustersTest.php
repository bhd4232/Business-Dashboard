<?php

namespace Tests\Feature;

use App\Filament\Clusters\Accounts;
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
use App\Filament\Resources\Vouchers\VoucherResource;
use App\Models\User;
use Filament\Pages\Enums\SubNavigationPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNavigationClustersTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_modules_use_native_top_navigation_clusters(): void
    {
        $components = [
            Storefront::class => [
                StorefrontSlideResource::class,
                StorefrontSettingResource::class,
                StorefrontPageResource::class,
                ProductCarouselResource::class,
                StorefrontPaymentResource::class,
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
            Accounts::class => [
                AccountResource::class,
                ExpenseResource::class,
                ExpenseCategoryResource::class,
                TransactionLedgerResource::class,
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
            $this->assertSame(SubNavigationPosition::Top, $cluster::getSubNavigationPosition());

            foreach ($clusteredComponents as $component) {
                $this->assertSame($cluster, $component::getCluster());
            }
        }

        $this->assertSame('CRM', Crm::getClusterBreadcrumb());
    }

    public function test_storefront_cluster_uses_concise_site_navigation_labels(): void
    {
        $this->assertSame('Site', Storefront::getNavigationLabel());
        $this->assertSame('Site', Storefront::getClusterBreadcrumb());
        $this->assertSame('Hero Slides', StorefrontSlideResource::getNavigationLabel());
        $this->assertSame('Settings', StorefrontSettingResource::getNavigationLabel());
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
            '/admin/accounts' => 'filament.admin.accounts.resources.accounts.index',
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

    public function test_cluster_page_renders_filament_page_selector_items(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $admin->defaultCompany()->getKey()])
            ->get('/admin/sales/customers')
            ->assertOk()
            ->assertSee('Customers')
            ->assertSee('Orders')
            ->assertSee('Customer Payments');
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
