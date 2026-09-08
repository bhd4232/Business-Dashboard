<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use App\Services\CompanyContext;
use App\Support\FileUploadClearAction;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner request: an image field whose preview is stuck on "Loading / Waiting
 * for size" (a slow or failing media URL, an offline webview) can't be cleared
 * with FilePond's built-in "×" — that button only appears once a file finishes
 * loading. A label-row "Remove" hint action, added to every FileUpload in
 * AppServiceProvider, gives a way out. See App\Support\FileUploadClearAction.
 */
class FileUploadClearActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
    }

    public function test_every_file_upload_field_carries_the_remove_hint_action(): void
    {
        $user = User::factory()->create();
        app(CompanyContext::class)->set($user->defaultCompany());
        $this->actingAs($user);

        $category = Category::query()->create([
            'name' => 'Kettles', 'slug' => 'kettles', 'is_active' => true,
        ]);

        $upload = collect(
            Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
                ->instance()
                ->getSchema('form')
                ?->getFlatComponents(withHidden: true)
        )->first(fn ($component): bool => $component instanceof BaseFileUpload && $component->getStatePath() === 'data.image');

        $this->assertInstanceOf(BaseFileUpload::class, $upload);
        $this->assertContains(
            FileUploadClearAction::NAME,
            collect($upload->getHintActions())->map->getName()->all(),
        );
    }

    public function test_remove_action_clears_a_stored_image_that_will_not_preview(): void
    {
        $user = User::factory()->create();
        $company = $user->defaultCompany();
        app(CompanyContext::class)->set($company);
        $this->actingAs($user);

        $path = $company->storageRoot().'/public/categories/stuck.webp';
        Storage::disk('public')->put($path, 'fake-image-bytes');

        $category = Category::query()->create([
            'name' => 'Blenders', 'slug' => 'blenders', 'is_active' => true, 'image' => $path,
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertFormSet(['image' => $path])
            ->assertFormComponentActionVisible('image', FileUploadClearAction::NAME)
            ->callFormComponentAction('image', FileUploadClearAction::NAME)
            ->assertHasNoFormComponentActionErrors()
            ->assertFormSet(['image' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($category->fresh()->image);
        // The stored object is deliberately left in place — a mis-click stays
        // recoverable from the Media Hub.
        Storage::disk('public')->assertExists($path);
    }

    public function test_remove_action_is_not_offered_while_the_field_is_empty(): void
    {
        $user = User::factory()->create();
        app(CompanyContext::class)->set($user->defaultCompany());
        $this->actingAs($user);

        $category = Category::query()->create([
            'name' => 'Toasters', 'slug' => 'toasters', 'is_active' => true,
        ]);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertFormComponentActionDoesNotExist('image', FileUploadClearAction::NAME);
    }
}
