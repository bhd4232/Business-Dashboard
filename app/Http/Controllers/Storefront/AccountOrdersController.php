<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Order;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AccountOrdersController extends Controller
{
    public function __construct(protected CompanyContext $context) {}

    public function index(Request $request): View
    {
        [$company, $setting] = $this->domainStorefront($request);

        return $this->ordersView($request, $company, $setting);
    }

    public function indexPreview(Request $request, Company $company): View
    {
        $setting = $this->previewStorefront($company);

        return $this->ordersView($request, $company, $setting, $company->slug);
    }

    protected function ordersView(
        Request $request,
        Company $company,
        StorefrontSetting $setting,
        ?string $previewSlug = null,
    ): View {
        $phone = trim((string) $request->string('phone'));
        $orders = $phone === '' ? collect() : $this->ordersForPhone($company, $phone);

        return view('storefront.account.orders', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $previewSlug,
            'phone' => $phone,
            'orders' => $orders,
            'hasSearched' => $request->filled('phone'),
        ]);
    }

    protected function ordersForPhone(Company $company, string $phone): Collection
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        return Order::query()
            ->with(['customer', 'items.product'])
            ->where('company_id', $company->getKey())
            ->where('source', Order::SOURCE_STOREFRONT)
            ->whereHas('customer', function ($query) use ($phone, $digits): void {
                $query->where('phone', $phone)
                    ->orWhere('phone', $digits)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', '') LIKE ?", ['%'.$digits.'%']);
            })
            ->latest('order_date')
            ->latest('id')
            ->get();
    }

    protected function domainStorefront(Request $request): array
    {
        $company = $request->attributes->get('storefront_company');

        abort_unless($company instanceof Company && $company->storefrontSetting?->is_published, 404);

        $this->context->set($company);

        return [$company, $company->storefrontSetting];
    }

    protected function previewStorefront(Company $company): StorefrontSetting
    {
        abort_unless(app()->environment(['local', 'testing']) || auth()->check(), 404);

        $this->context->set($company);

        return $company->storefrontSetting ?: new StorefrontSetting([
            'company_id' => $company->getKey(),
            'theme_color' => '#F59E0B',
            'meta_title' => $company->name,
            'is_published' => true,
        ]);
    }
}
