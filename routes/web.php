<?php

use App\Http\Controllers\Admin\AppUpgradeController;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\CompanySwitchController;
use App\Http\Controllers\Admin\ConversationMediaController;
use App\Http\Controllers\Admin\CustomerCsvController;
use App\Http\Controllers\Admin\LegacyAdminClusterRedirectController;
use App\Http\Controllers\Admin\OrderPdfController;
use App\Http\Controllers\Admin\ProductCsvController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\ReportPdfController;
use App\Http\Controllers\Admin\SupplierCsvController;
use App\Http\Controllers\Admin\VoucherAttachmentDownloadController;
use App\Http\Controllers\Admin\VoucherReceiptController;
use App\Http\Controllers\ChatOrderController;
use App\Http\Controllers\CourierWebhookController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\QuotationPublicController;
use App\Http\Controllers\Storefront\AccountAuthController as StorefrontAccountAuthController;
use App\Http\Controllers\Storefront\AccountOrdersController as StorefrontAccountOrdersController;
use App\Http\Controllers\Storefront\AccountProfileController as StorefrontAccountProfileController;
use App\Http\Controllers\Storefront\CartController as StorefrontCartController;
use App\Http\Controllers\Storefront\CheckoutController as StorefrontCheckoutController;
use App\Http\Controllers\Storefront\ContactController as StorefrontContactController;
use App\Http\Controllers\Storefront\HomeController as StorefrontHomeController;
use App\Http\Controllers\Storefront\OrderTrackController as StorefrontOrderTrackController;
use App\Http\Controllers\Storefront\PageController as StorefrontPageController;
use App\Http\Controllers\Storefront\PreviewController as StorefrontPreviewController;
use App\Http\Controllers\Storefront\ProductIndexController as StorefrontProductIndexController;
use App\Http\Controllers\Storefront\ProductShowController as StorefrontProductShowController;
use App\Http\Controllers\Storefront\ResellerController;
use App\Http\Controllers\ZiniPayWebhookController;
use App\Http\Middleware\ResolveCompanyFromDomain;
use App\Models\Order;
use App\Models\User;
use App\Services\AppUpdateService;
use App\Services\CompanySettingsService;
use App\Support\AppDeployment;
use App\Support\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(ResolveCompanyFromDomain::class.':optional')
    ->get('/', StorefrontHomeController::class)
    ->name('marketing.home');

Route::get('/quotation/{quotationNumber}', [QuotationPublicController::class, 'show'])
    ->name('quotation.public');

Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])
    ->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])
    ->name('webhooks.meta.handle');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/o/{token}', [ChatOrderController::class, 'show'])
        ->name('chat-order.show');
    Route::post('/o/{token}', [ChatOrderController::class, 'store'])
        ->name('chat-order.store');
});

Route::middleware(ResolveCompanyFromDomain::class.':optional')
    ->get('/pages/{slug}', [StorefrontPageController::class, 'show'])
    ->name('storefront.pages.show');

Route::get('/storefront', [StorefrontPreviewController::class, 'home'])
    ->name('storefront.preview.index');

Route::prefix('/storefront/{company:slug}')->group(function (): void {
    Route::get('/', [StorefrontPreviewController::class, 'home'])
        ->name('storefront.preview.show');

    Route::get('/products', [StorefrontPreviewController::class, 'products'])
        ->name('storefront.preview.products.index');

    Route::get('/category/{slug}', [StorefrontPreviewController::class, 'products'])
        ->name('storefront.preview.categories.show');

    Route::get('/product/{slug}', [StorefrontPreviewController::class, 'product'])
        ->name('storefront.preview.products.show');

    Route::get('/cart', [StorefrontCartController::class, 'showPreview'])
        ->name('storefront.preview.cart.show');

    Route::post('/cart/items/{slug}', [StorefrontCartController::class, 'addPreview'])
        ->name('storefront.preview.cart.add');

    Route::patch('/cart/items/{slug}', [StorefrontCartController::class, 'updatePreview'])
        ->name('storefront.preview.cart.update');

    Route::delete('/cart/items/{slug}', [StorefrontCartController::class, 'removePreview'])
        ->name('storefront.preview.cart.remove');

    Route::get('/checkout', [StorefrontCheckoutController::class, 'showPreview'])
        ->name('storefront.preview.checkout.show');

    Route::post('/checkout', [StorefrontCheckoutController::class, 'storePreview'])
        ->name('storefront.preview.checkout.store');

    Route::get('/checkout/success/{order}', [StorefrontCheckoutController::class, 'successPreview'])
        ->name('storefront.preview.checkout.success');

    Route::get('/track', [StorefrontOrderTrackController::class, 'indexPreview'])
        ->name('storefront.preview.track.index');

    Route::get('/track/{orderNo}', [StorefrontOrderTrackController::class, 'showPreview'])
        ->name('storefront.preview.track.show');

    Route::get('/account/orders', [StorefrontAccountOrdersController::class, 'indexPreview'])
        ->name('storefront.preview.account.orders');

    Route::post('/account/orders/{orderNo}/reorder', [StorefrontAccountOrdersController::class, 'reorderPreview'])
        ->name('storefront.preview.account.reorder');

    Route::get('/reseller', [ResellerController::class, 'showPreview'])
        ->name('storefront.preview.reseller.show');

    Route::post('/reseller', [ResellerController::class, 'storePreview'])
        ->name('storefront.preview.reseller.store');

    Route::get('/pages/{slug}', [StorefrontPageController::class, 'showPreview'])
        ->name('storefront.preview.pages.show');

    Route::get('/contact', [StorefrontContactController::class, 'showPreview'])
        ->name('storefront.preview.contact');
});

Route::middleware(ResolveCompanyFromDomain::class)->group(function (): void {
    Route::get('/products', StorefrontProductIndexController::class)
        ->name('storefront.products.index');

    Route::get('/category/{slug}', StorefrontProductIndexController::class)
        ->name('storefront.categories.show');

    Route::get('/product/{slug}', StorefrontProductShowController::class)
        ->name('storefront.products.show');

    Route::get('/cart', [StorefrontCartController::class, 'show'])
        ->name('storefront.cart.show');

    Route::post('/cart/items/{slug}', [StorefrontCartController::class, 'add'])
        ->name('storefront.cart.add');

    Route::patch('/cart/items/{slug}', [StorefrontCartController::class, 'update'])
        ->name('storefront.cart.update');

    Route::delete('/cart/items/{slug}', [StorefrontCartController::class, 'remove'])
        ->name('storefront.cart.remove');

    Route::get('/checkout', [StorefrontCheckoutController::class, 'show'])
        ->name('storefront.checkout.show');

    Route::post('/checkout', [StorefrontCheckoutController::class, 'store'])
        ->name('storefront.checkout.store');

    Route::get('/checkout/success/{order}', [StorefrontCheckoutController::class, 'success'])
        ->name('storefront.checkout.success');

    Route::get('/track', [StorefrontOrderTrackController::class, 'index'])
        ->name('storefront.track.index');

    Route::get('/track/{orderNo}', [StorefrontOrderTrackController::class, 'show'])
        ->name('storefront.track.show');

    Route::get('/account/orders', [StorefrontAccountOrdersController::class, 'index'])
        ->name('storefront.account.orders');

    Route::post('/account/orders/{orderNo}/reorder', [StorefrontAccountOrdersController::class, 'reorder'])
        ->name('storefront.account.reorder');

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::get('/account/login', [StorefrontAccountAuthController::class, 'showLogin'])
            ->name('storefront.account.login');
        Route::post('/account/login', [StorefrontAccountAuthController::class, 'login'])
            ->name('storefront.account.login.store');
        Route::get('/account/register', [StorefrontAccountAuthController::class, 'showRegister'])
            ->name('storefront.account.register');
        Route::post('/account/register', [StorefrontAccountAuthController::class, 'register'])
            ->name('storefront.account.register.store');
        Route::get('/account/reset-password', [StorefrontAccountAuthController::class, 'showResetPassword'])
            ->name('storefront.account.reset-password');
        Route::post('/account/reset-password', [StorefrontAccountAuthController::class, 'resetPassword'])
            ->name('storefront.account.reset-password.store');
    });

    Route::middleware('throttle:5,1')->group(function (): void {
        Route::get('/account/forgot-password', [StorefrontAccountAuthController::class, 'showForgotPassword'])
            ->name('storefront.account.forgot-password');
        Route::post('/account/forgot-password', [StorefrontAccountAuthController::class, 'forgotPassword'])
            ->name('storefront.account.forgot-password.store');
    });

    Route::post('/account/logout', [StorefrontAccountAuthController::class, 'logout'])
        ->name('storefront.account.logout');

    Route::get('/account/profile', [StorefrontAccountProfileController::class, 'show'])
        ->name('storefront.account.profile');
    Route::patch('/account/profile', [StorefrontAccountProfileController::class, 'update'])
        ->name('storefront.account.profile.update');
    Route::put('/account/password', [StorefrontAccountProfileController::class, 'updatePassword'])
        ->name('storefront.account.password.update');

    Route::get('/reseller', [ResellerController::class, 'show'])
        ->name('storefront.reseller.show');

    Route::post('/reseller', [ResellerController::class, 'store'])
        ->name('storefront.reseller.store');

    Route::get('/contact', [StorefrontContactController::class, 'show'])
        ->name('storefront.contact');

});

Route::view('/pricing', 'marketing.pricing')->name('marketing.pricing');

Route::view('/docs', 'marketing.docs')->name('marketing.docs');

Route::get('/health/version', function () {
    $roleOptions = User::roleOptions();
    $release = AppRelease::current();
    $publishedRelease = AppRelease::latestPublished();
    $deployment = AppDeployment::current();
    $ready = app(AppUpdateService::class)->isDeploymentEligible($deployment);

    return response()
        ->json([
            'app' => 'zamzam-erp',
            'app_name' => config('app.name'),
            'version' => $release['version'],
            'published_version' => $publishedRelease['version'],
            'release_type' => $release['type'],
            'release_label' => $release['type_label'],
            'release_date' => $release['date'],
            'marker' => 'roles-built-in-v2',
            'commit' => $deployment['commit'],
            'deployment_id' => $deployment['deployment_id'],
            'deployment_ready' => $deployment['ready'],
            'ready' => $ready,
            'built_at' => $deployment['built_at'],
            'source_id' => $deployment['source_id'],
            'assets_id' => $deployment['assets_id'],
            'role_option_keys' => array_keys($roleOptions),
            'has_sales_staff_role' => array_key_exists('sales_staff', $roleOptions),
        ])
        ->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Expires' => '0',
            'Pragma' => 'no-cache',
        ]);
})->name('health.version');

Route::get('/install', [InstallController::class, 'create'])->name('install.create');
Route::post('/install', [InstallController::class, 'store'])->name('install.store');

Route::post('/webhooks/couriers/{provider}', CourierWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('couriers.webhook');

Route::post('/webhooks/zinipay/{payment}', ZiniPayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('zinipay.webhook');

Route::middleware('auth')->get('/admin/orders/{order}/print', function (Order $order, Request $request) {
    abort_unless($request->user()?->canPerformModelAbility('view', Order::class), 403);

    return view('orders.print', [
        'order' => $order->load(['company', 'customer', 'items.product', 'latestCourierBooking.provider']),
        'company' => app(CompanySettingsService::class)->profile($order->company),
        'invoice' => app(CompanySettingsService::class)->invoice($order->company),
    ]);
})->name('orders.print');

Route::middleware('auth')
    ->get('/admin/orders/{order}/pdf', OrderPdfController::class)
    ->name('orders.pdf');

Route::middleware('auth')
    ->get('/admin/reports/export/{type}', ReportExportController::class)
    ->name('reports.export');

Route::middleware('auth')
    ->get('/admin/reports/export/{type}/pdf', ReportPdfController::class)
    ->name('reports.export.pdf');

Route::middleware('auth')->group(function (): void {
    Route::post('/admin/app-updates/sync', [AppUpgradeController::class, 'synchronize'])
        ->middleware('throttle:12,1')
        ->name('admin.app-updates.sync');

    Route::post('/admin/app-upgrade', [AppUpgradeController::class, 'upgrade'])
        ->middleware('throttle:6,1')
        ->name('admin.app-upgrade');

    Route::redirect('/admin/companies', '/admin/company-management/companies')
        ->name('admin.companies.legacy');

    Route::redirect('/admin/company-settings', '/admin/company-management/company-settings')
        ->name('admin.company-settings.legacy');

    Route::post('/admin/company/switch', CompanySwitchController::class)
        ->name('admin.company.switch');

    Route::get('/admin/products/export/csv', [ProductCsvController::class, 'export'])
        ->name('products.export.csv');

    Route::get('/admin/products/import/sample', [ProductCsvController::class, 'sample'])
        ->name('products.import.sample');

    Route::get('/admin/customers/export/csv', [CustomerCsvController::class, 'export'])
        ->name('customers.export.csv');

    Route::get('/admin/customers/import/sample', [CustomerCsvController::class, 'sample'])
        ->name('customers.import.sample');

    Route::get('/admin/suppliers/export/csv', [SupplierCsvController::class, 'export'])
        ->name('suppliers.export.csv');

    Route::get('/admin/suppliers/import/sample', [SupplierCsvController::class, 'sample'])
        ->name('suppliers.import.sample');

    Route::get('/admin/backups/download/{filename}', BackupDownloadController::class)
        ->where('filename', '[A-Za-z0-9._-]+')
        ->name('backups.download');

    Route::get('/admin/conversation-messages/{message}/media', ConversationMediaController::class)
        ->whereNumber('message')
        ->name('conversation-messages.media');

    Route::get('/admin/voucher-attachments/{attachment}/download', VoucherAttachmentDownloadController::class)
        ->whereNumber('attachment')
        ->name('voucher-attachments.download');

    Route::get('/admin/{legacy}/{path?}', LegacyAdminClusterRedirectController::class)
        ->whereIn('legacy', LegacyAdminClusterRedirectController::legacySegments())
        ->where('path', '.*')
        ->name('admin.clusters.legacy');
});

// Shareable Money Receipt: signed URL, no login required, signature can't be guessed.
Route::middleware('signed')
    ->get('/vouchers/{voucher:voucher_number}/receipt', VoucherReceiptController::class)
    ->name('vouchers.receipt');
