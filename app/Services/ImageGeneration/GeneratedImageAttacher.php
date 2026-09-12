<?php

namespace App\Services\ImageGeneration;

use App\Models\GeneratedImage;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §10 — attaches one finished
 * output of a GeneratedImage to a real record:
 *
 * - Product featured image  → `products.image`
 * - Product gallery         → appended to `products.gallery_images`
 * - Offer / landing page    → `offers.cover_image` (the landing hero banner)
 * - Video reference         → flags `is_video_reference` for the future AI
 *   Video Generation tool (no target record — see videoReferences() scope)
 *
 * A stored output path is already in the company-scoped shape every image
 * FileUpload field in the app uses (CompanyStorageService::putPublic ==
 * CompanyMedia::publicDirectory), so an attach is a straight write of that
 * path into the target column — no file copy, no re-encode. Each record
 * attach also stamps the target on the generation's `linked` morph so the
 * library (Phase 4) can show where an image ended up.
 *
 * Meta ads: MetaAdsCreationService always pulls its creative image from the
 * advertised Product's own featured image — there is no separate ad-creative
 * field to target — so "use this for a Meta ad" is realised here as
 * PRODUCT_SLOT_FEATURED.
 */
class GeneratedImageAttacher
{
    public const PRODUCT_SLOT_FEATURED = 'featured';

    public const PRODUCT_SLOT_GALLERY = 'gallery';

    /** Matches the product gallery FileUpload's ->maxFiles(10). */
    public const PRODUCT_GALLERY_MAX = 10;

    /**
     * @param  self::PRODUCT_SLOT_*  $slot
     * @return string  a short human label for the slot the image landed in
     */
    public function attachToProduct(GeneratedImage $image, int $outputIndex, Product $product, string $slot): string
    {
        $path = $this->outputPath($image, $outputIndex);
        $this->assertSameCompany($image, $product);

        if ($slot === self::PRODUCT_SLOT_FEATURED) {
            $product->forceFill(['image' => $path])->save();
            $landedIn = 'featured image';
        } else {
            $gallery = collect($product->gallery_images ?? [])
                ->filter(fn ($existing): bool => filled($existing))
                ->reject(fn ($existing): bool => $existing === $path)
                ->push($path)
                ->values()
                ->all();

            if (count($gallery) > self::PRODUCT_GALLERY_MAX) {
                throw new ImageAttachmentException(sprintf(
                    'This product already has the maximum of %d gallery images. Remove one first.',
                    self::PRODUCT_GALLERY_MAX,
                ));
            }

            $product->forceFill(['gallery_images' => $gallery])->save();
            $landedIn = 'gallery';
        }

        $this->link($image, $product);

        return $landedIn;
    }

    public function attachToOffer(GeneratedImage $image, int $outputIndex, Offer $offer): void
    {
        $path = $this->outputPath($image, $outputIndex);
        $this->assertSameCompany($image, $offer);

        $offer->forceFill(['cover_image' => $path])->save();

        $this->link($image, $offer);
    }

    /**
     * Flag the whole generation as a reference image for the future AI Video
     * Generation tool. No target record and no `linked` morph — the flag plus
     * the videoReferences() scope is all that tool needs to find them.
     */
    public function markAsVideoReference(GeneratedImage $image): void
    {
        if (! $image->is_video_reference) {
            $image->forceFill(['is_video_reference' => true])->save();
        }
    }

    protected function outputPath(GeneratedImage $image, int $outputIndex): string
    {
        if ($image->status !== GeneratedImage::STATUS_COMPLETED) {
            throw new ImageAttachmentException('That generation has not finished yet.');
        }

        if (! $image->isAttachable()) {
            throw new ImageAttachmentException($image->review_status === GeneratedImage::REVIEW_REJECTED
                ? 'A reviewer rejected this image, so it cannot be attached.'
                : 'This image is waiting for a reviewer to approve it before it can be attached.');
        }

        $paths = array_values($image->output_paths ?? []);

        if (! array_key_exists($outputIndex, $paths) || blank($paths[$outputIndex])) {
            throw new ImageAttachmentException('That image is no longer available.');
        }

        return (string) $paths[$outputIndex];
    }

    protected function assertSameCompany(GeneratedImage $image, Model $target): void
    {
        if ((int) $image->company_id !== (int) $target->getAttribute('company_id')) {
            throw new ImageAttachmentException('That record belongs to a different company.');
        }
    }

    protected function link(GeneratedImage $image, Model $target): void
    {
        $image->linked()->associate($target);
        $image->save();
    }
}
