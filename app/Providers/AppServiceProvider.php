<?php

namespace App\Providers;

use App\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Account;
use App\Models\Category;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerBlacklist;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\StorefrontPage;
use App\Models\StorefrontSetting;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\TransactionLedger;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\StorageSettingsService;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
        $this->app->alias(CompanyContext::class, 'company.context');
        $this->app->singleton(StorageSettingsService::class);
        $this->app->singleton(CompanyStorageService::class);
    }

    public function boot(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureCloudStorage();

        // Queue workers are long-lived. Refresh DB-backed storage settings
        // before each worker loop so an admin disk switch does not leave chat
        // media jobs writing with stale credentials until a process restart.
        Queue::looping(function (): void {
            try {
                $settings = $this->app->make(StorageSettingsService::class);
                $settings->forgetCachedSettings();
                $settings->configureNamedDisks();
                $this->app->make(CompanyStorageService::class)->forgetLocations();
            } catch (Throwable) {
                // The worker will surface the actual storage/database error on
                // the job; a failed refresh must not terminate its loop.
            }
        });

        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            $subject = $arguments[0] ?? null;
            $modelClass = is_object($subject) ? $subject::class : $subject;

            if (! is_string($modelClass) || ! array_key_exists($modelClass, User::MODEL_MODULES)) {
                return null;
            }

            return $user->canPerformModelAbility($ability, $modelClass);
        });

        foreach ([
            Account::class,
            Category::class,
            Company::class,
            Customer::class,
            CustomerPayment::class,
            CustomerBlacklist::class,
            Expense::class,
            ExpenseCategory::class,
            Order::class,
            OrderItem::class,
            Product::class,
            Purchase::class,
            PurchaseItem::class,
            StockMovement::class,
            StorefrontPage::class,
            StorefrontSetting::class,
            Supplier::class,
            SupplierPayment::class,
            TransactionLedger::class,
            User::class,
        ] as $model) {
            $model::observe(AuditObserver::class);
        }

        Livewire::component('app.filament.resources.products.pages.list-products', ListProducts::class);
        Livewire::component('app.filament.resources.products.pages.create-product', CreateProduct::class);
        Livewire::component('app.filament.resources.products.pages.edit-product', EditProduct::class);
        Livewire::component('app.filament.resources.category-resource.pages.list-categories', ListCategories::class);
        Livewire::component('app.filament.resources.category-resource.pages.create-category', CreateCategory::class);
        Livewire::component('app.filament.resources.category-resource.pages.edit-category', EditCategory::class);
        Livewire::component('filament.livewire.notifications', Notifications::class);
    }

    /**
     * Hydrate the stable R2 disk names from encrypted database settings.
     * The local "public" and "local" disks retain their configured meaning
     * so legacy objects can still be located and migrated safely.
     *
     * Wrapped defensively because this also runs before fresh installs have
     * a database or app_settings table.
     */
    protected function configureCloudStorage(): void
    {
        try {
            $this->app->make(StorageSettingsService::class)->configureNamedDisks();
        } catch (Throwable) {
            // Database not reachable yet (fresh install, artisan key:generate,
            // etc.) — fall back to the default local "public" disk.
        }
    }
}
