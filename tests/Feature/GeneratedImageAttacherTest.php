<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\GeneratedImage;
use App\Models\Offer;
use App\Models\Product;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\GeneratedImageAttacher;
use App\Services\ImageGeneration\ImageAttachmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneratedImageAttacherTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'name' => 'Attach Co',
            'slug' => 'attach-co-'.uniqid(),
            'invoice_prefix' => 'ATT'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->user = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->user->companies()->syncWithoutDetaching([
            $this->company->getKey() => ['role' => 'manager', 'is_default' => true],
        ]);
    }

    private function completedGeneration(array $overrides = []): GeneratedImage
    {
        return GeneratedImage::query()->create(array_merge([
            'user_id' => $this->user->getKey(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => 'product_photo',
            'prompt' => 'a matte black bottle on a jute mat',
            'provider_profile_id' => 'p1',
            'provider_label' => 'OpenAI',
            'api_format' => 'openai',
            'aspect_ratio' => '1:1',
            'variations_requested' => 2,
            'output_paths' => [
                'companies/'.$this->company->storage_key.'/public/ai-generated-images/ai-a.webp',
                'companies/'.$this->company->storage_key.'/public/ai-generated-images/ai-b.webp',
            ],
            'status' => GeneratedImage::STATUS_COMPLETED,
            'generated_at' => now(),
        ], $overrides));
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => 'Steel Bottle',
            'sku' => 'SB-'.random_int(100, 999),
            'price' => 900,
            'sale_price' => 900,
            'stock' => 0,
        ]);
    }

    public function test_it_adds_an_output_to_the_product_gallery_and_links_the_generation(): void
    {
        $generation = $this->completedGeneration();
        $product = $this->product();

        $landedIn = app(GeneratedImageAttacher::class)->attachToProduct(
            $generation,
            1,
            $product,
            GeneratedImageAttacher::PRODUCT_SLOT_GALLERY,
        );

        $this->assertSame('gallery', $landedIn);
        $this->assertSame(
            ['companies/'.$this->company->storage_key.'/public/ai-generated-images/ai-b.webp'],
            $product->fresh()->gallery_images,
        );
        $this->assertNull($product->fresh()->image);

        $generation->refresh();
        $this->assertSame(Product::class, $generation->linked_type);
        $this->assertSame($product->getKey(), $generation->linked_id);
    }

    public function test_it_sets_the_output_as_the_products_featured_image(): void
    {
        $generation = $this->completedGeneration();
        $product = $this->product();

        $landedIn = app(GeneratedImageAttacher::class)->attachToProduct(
            $generation,
            0,
            $product,
            GeneratedImageAttacher::PRODUCT_SLOT_FEATURED,
        );

        $this->assertSame('featured image', $landedIn);
        $this->assertSame(
            'companies/'.$this->company->storage_key.'/public/ai-generated-images/ai-a.webp',
            $product->fresh()->image,
        );
    }

    public function test_attaching_the_same_image_to_the_gallery_twice_does_not_duplicate_it(): void
    {
        $generation = $this->completedGeneration();
        $product = $this->product();

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);

        $this->assertCount(1, $product->fresh()->gallery_images);
    }

    public function test_it_rejects_a_full_product_gallery(): void
    {
        $generation = $this->completedGeneration();
        $product = $this->product();
        $product->forceFill(['gallery_images' => array_map(fn (int $i): string => "existing-{$i}.webp", range(1, 10))])->save();

        $this->expectException(ImageAttachmentException::class);

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
    }

    public function test_it_sets_an_offer_cover_banner_and_links_the_generation(): void
    {
        $generation = $this->completedGeneration(['context' => 'landing_banner']);
        $offer = Offer::query()->create(['type' => Offer::TYPE_SINGLE, 'title' => 'Winter Combo']);

        app(GeneratedImageAttacher::class)->attachToOffer($generation, 0, $offer);

        $this->assertSame(
            'companies/'.$this->company->storage_key.'/public/ai-generated-images/ai-a.webp',
            $offer->fresh()->cover_image,
        );

        $generation->refresh();
        $this->assertSame(Offer::class, $generation->linked_type);
        $this->assertSame($offer->getKey(), $generation->linked_id);
    }

    public function test_it_flags_a_generation_as_a_video_reference(): void
    {
        $generation = $this->completedGeneration();
        $this->assertFalse((bool) $generation->fresh()->is_video_reference);

        app(GeneratedImageAttacher::class)->markAsVideoReference($generation);

        $this->assertTrue($generation->fresh()->is_video_reference);
        $this->assertTrue(GeneratedImage::query()->videoReferences()->whereKey($generation->getKey())->exists());
    }

    public function test_it_rejects_an_unfinished_generation(): void
    {
        $generation = $this->completedGeneration(['status' => GeneratedImage::STATUS_PROCESSING, 'output_paths' => null]);
        $product = $this->product();

        $this->expectException(ImageAttachmentException::class);

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
    }

    public function test_it_rejects_an_out_of_range_output_index(): void
    {
        $generation = $this->completedGeneration();
        $product = $this->product();

        $this->expectException(ImageAttachmentException::class);

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 9, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
    }

    public function test_it_rejects_an_image_awaiting_review(): void
    {
        $generation = $this->completedGeneration(['review_status' => GeneratedImage::REVIEW_PENDING]);
        $product = $this->product();

        $this->expectException(ImageAttachmentException::class);
        $this->expectExceptionMessage('waiting for a reviewer');

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
    }

    public function test_it_allows_an_approved_image(): void
    {
        $generation = $this->completedGeneration(['review_status' => GeneratedImage::REVIEW_APPROVED]);
        $product = $this->product();

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $product, GeneratedImageAttacher::PRODUCT_SLOT_FEATURED);

        $this->assertNotNull($product->fresh()->image);
    }

    public function test_it_rejects_a_target_from_another_company(): void
    {
        $generation = $this->completedGeneration();

        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'slug' => 'other-co-'.uniqid(),
            'invoice_prefix' => 'OTH'.random_int(100, 999),
            'currency' => 'BDT',
            'timezone' => 'Asia/Dhaka',
            'is_active' => true,
        ]);
        app(CompanyContext::class)->set($otherCompany);
        $foreignProduct = Product::query()->create([
            'name' => 'Foreign', 'sku' => 'F-1', 'price' => 1, 'sale_price' => 1, 'stock' => 0,
        ]);
        app(CompanyContext::class)->set($this->company);

        $this->expectException(ImageAttachmentException::class);

        app(GeneratedImageAttacher::class)->attachToProduct($generation, 0, $foreignProduct, GeneratedImageAttacher::PRODUCT_SLOT_GALLERY);
    }
}
