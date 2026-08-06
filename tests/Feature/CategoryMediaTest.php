<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\StorefrontSetting;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\CompanyMedia;
use App\Support\StorefrontCategoryIcons;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_category_admin_form_exposes_image_and_icon_fields(): void
    {
        $user = User::factory()->create();
        app(CompanyContext::class)->set($user->defaultCompany());

        $this->actingAs($user);

        Livewire::test(CreateCategory::class)
            ->assertFormFieldExists('image')
            ->assertFormFieldExists('icon')
            ->assertSee('Choose a category icon')
            ->assertSee('Search icons by name or style')
            ->assertSee('data-zz-category-icon-search-input', false)
            ->assertSee('data-zz-category-icon-option', false);

        $this->assertSame(
            ['image', 'icon'],
            collect(CategoryForm::mediaFields())->map->getName()->all(),
        );
        $this->assertSame(
            ['name', 'description', 'image', 'icon', 'is_active'],
            collect(ProductForm::categoryCreateOptionForm())->map->getName()->all(),
        );

        $pickerView = File::get(resource_path('views/filament/forms/components/category-icon-picker.blade.php'));
        $searchScript = File::get(resource_path('views/filament/partials/category-icon-search.blade.php'));

        $this->assertSame(2, substr_count($pickerView, '$entangle'));
        $this->assertStringContainsString('data-zz-category-icon-search-input', $pickerView);
        $this->assertStringContainsString('data-zz-category-icon-search', $pickerView);
        $this->assertStringContainsString("document.addEventListener('input'", $searchScript);
        $this->assertStringContainsString("document.addEventListener('keydown'", $searchScript);
        $this->assertStringContainsString("option.style.display = isMatch ? '' : 'none'", $searchScript);
    }

    public function test_category_icon_options_are_validated_and_persisted(): void
    {
        $company = $this->createCompany('category-icons.example.test');
        app(CompanyContext::class)->set($company);

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Networking',
                'slug' => 'networking',
                'icon' => 'heroicon-o-wifi',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::query()->where('slug', 'networking')->firstOrFail();

        $this->assertSame('heroicon-o-wifi', $category->fresh()->icon);
        $this->assertSame('Wifi (Outline)', StorefrontCategoryIcons::label($category->icon));
        $this->assertSame('heroicon-o-wifi', StorefrontCategoryIcons::normalize('networking'));
        $this->assertNull(StorefrontCategoryIcons::normalize('unregistered-icon'));
        $this->assertCount(count(Heroicon::cases()), StorefrontCategoryIcons::catalog());
        $this->assertArrayHasKey('heroicon-o-wifi', StorefrontCategoryIcons::options());
        $this->assertArrayHasKey('heroicon-s-wifi', StorefrontCategoryIcons::options());
        $this->assertLessThanOrEqual(
            80,
            collect(StorefrontCategoryIcons::catalog())->max(fn (array $icon): int => strlen($icon['value'])),
        );
    }

    public function test_storefront_uses_category_image_then_icon_then_initial(): void
    {
        $company = $this->createCompany('category-media.example.test');
        app(CompanyContext::class)->set($company);

        StorefrontSetting::query()->create([
            'company_id' => $company->getKey(),
            'theme_color' => '#0F766E',
            'is_published' => true,
        ]);

        $imagePath = $company->storageRoot().'/public/categories/appliances.webp';
        $categories = [
            Category::query()->create(['name' => 'Appliances', 'slug' => 'appliances', 'image' => $imagePath, 'icon' => 'appliances', 'is_active' => true]),
            Category::query()->create(['name' => 'Networking', 'slug' => 'networking', 'icon' => 'networking', 'is_active' => true]),
            Category::query()->create(['name' => 'Office', 'slug' => 'office', 'is_active' => true]),
        ];

        foreach ($categories as $index => $category) {
            Product::query()->create([
                'name' => 'Category Product '.($index + 1),
                'sku' => 'CATEGORY-MEDIA-'.($index + 1),
                'price' => 500,
                'sale_price' => 500,
                'cost_price' => 300,
                'stock' => 5,
                'unit' => 'pcs',
                'reorder_level' => 1,
                'vat_rate' => 0,
                'is_active' => true,
                'status' => Product::STATUS_AVAILABLE,
                'category_id' => $category->getKey(),
            ]);
        }

        $response = $this->get('http://category-media.example.test/');

        $response
            ->assertOk()
            ->assertSee(CompanyMedia::publicUrl($imagePath, $company), false)
            ->assertSee('data-category-icon="heroicon-o-wifi"', false)
            ->assertSee('data-category-initial="O"', false)
            ->assertDontSee('data-category-icon="appliances"', false);
    }

    private function createCompany(string $domain): Company
    {
        return Company::query()->create([
            'name' => 'Category Media Store',
            'slug' => str($domain)->before('.')->slug()->value(),
            'domain' => $domain,
            'domain_verified' => true,
            'invoice_prefix' => 'CM'.random_int(10, 99),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
    }
}
