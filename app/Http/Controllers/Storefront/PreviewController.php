<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCarousel;
use App\Models\StorefrontSetting;
use App\Services\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PreviewController extends Controller
{
    public function __construct(protected CompanyContext $context) {}

    public function home(?Company $company = null): View
    {
        $company = $this->previewCompany($company);
        $setting = $this->previewSetting($company);

        return view('storefront.home', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $company->slug,
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->where('is_active', true)->where('status', Product::STATUS_AVAILABLE))
                ->orderBy('name')
                ->take(8)
                ->get(),
            'products' => Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('status', Product::STATUS_AVAILABLE)
                ->latest()
                ->take(12)
                ->get(),
            'carousels' => ProductCarousel::forHomepage(),
        ]);
    }

    public function products(Request $request, Company $company, ?string $slug = null): View
    {
        $setting = $this->previewSetting($company);

        $category = $slug
            ? Category::query()->where('slug', $slug)->where('is_active', true)->firstOrFail()
            : null;

        $sort = $request->string('sort')->value();

        return view('storefront.products.index', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $company->slug,
            'category' => $category,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->with('category')
                ->where('is_active', true)
                ->where('status', Product::STATUS_AVAILABLE)
                ->when($category, fn ($query) => $query->whereBelongsTo($category))
                ->when($sort === 'price_asc', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) asc'))
                ->when($sort === 'price_desc', fn ($query) => $query->orderByRaw('COALESCE(sale_price, price) desc'))
                ->when(! in_array($sort, ['price_asc', 'price_desc'], true), fn ($query) => $query->latest())
                ->paginate(24)
                ->withQueryString(),
            'sort' => $sort,
        ]);
    }

    public function product(Company $company, string $slug): View
    {
        $setting = $this->previewSetting($company);

        $product = Product::query()
            ->with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->firstOrFail();

        $related = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->where('status', Product::STATUS_AVAILABLE)
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();

        return view('storefront.products.show', [
            'company' => $company,
            'setting' => $setting,
            'previewSlug' => $company->slug,
            'product' => $product,
            'related' => $related,
        ]);
    }

    protected function previewCompany(?Company $company): Company
    {
        abort_unless(app()->environment(['local', 'testing']) || auth()->check(), 404);

        $company ??= StorefrontSetting::query()
            ->where('is_published', true)
            ->with('company')
            ->first()
            ?->company;

        $company ??= Company::defaultCompany();

        abort_unless($company instanceof Company, 404);

        $this->context->set($company);

        return $company;
    }

    protected function previewSetting(Company $company): StorefrontSetting
    {
        $this->context->set($company);

        return $company->storefrontSetting ?: new StorefrontSetting([
            'company_id' => $company->getKey(),
            'theme_color' => '#F59E0B',
            'meta_title' => $company->name,
            'is_published' => true,
        ]);
    }
}
