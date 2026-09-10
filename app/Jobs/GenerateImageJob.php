<?php

namespace App\Jobs;

use App\Filament\Pages\ImageGeneration;
use App\Models\GeneratedImage;
use App\Models\Media;
use App\Services\BusinessNotificationService;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\ImageGeneration\ImageGenerationException;
use App\Services\ImageGeneration\ImageGenerationRequest;
use App\Services\ImageGeneration\ImageProviderResolver;
use App\Services\ImageGeneration\ImageProviderSettingsService;
use App\Services\ImageOptimizerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs one Image Generation request off the web request (a generation
 * commonly takes 10-60s). Resolves the chosen provider adapter, optimizes
 * every returned image to WebP, stores each through CompanyStorageService,
 * registers each in the company's Media Hub (so it is pickable from every
 * "Select From Media" field), updates the GeneratedImage row, and notifies
 * the requesting user on completion or failure.
 *
 * Sets/clears CompanyContext explicitly (CLAUDE.md rule for queued jobs) —
 * storage, Media Hub, and the row's own CompanyScope all depend on it.
 */
class GenerateImageJob implements ShouldQueue
{
    use Queueable;

    /** A failed generation is surfaced to the user with its reason, not retried blindly. */
    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $generatedImageId) {}

    public function handle(
        CompanyContext $context,
        ImageProviderSettingsService $providerSettings,
        ImageProviderResolver $resolver,
        ImageOptimizerService $optimizer,
        CompanyStorageService $storage,
        BusinessNotificationService $notifications,
    ): void {
        $record = GeneratedImage::withoutGlobalScopes()->with(['company', 'user'])->find($this->generatedImageId);

        if (! $record || ! $record->company) {
            return;
        }

        $context->set($record->company);

        try {
            $record->forceFill(['status' => GeneratedImage::STATUS_PROCESSING])->save();

            $profile = $providerSettings->find($record->company, $record->provider_profile_id);

            if ($profile === null) {
                throw new ImageGenerationException('The selected image provider profile no longer exists — reconfigure it on AI Tools → Image Providers.');
            }

            if (! $providerSettings->isConfigured($profile)) {
                throw new ImageGenerationException('The selected image provider profile is missing a model or API key.');
            }

            $result = $resolver->byFormat($profile['api_format'])->generate(new ImageGenerationRequest(
                prompt: $record->prompt,
                model: (string) $profile['model'],
                apiKey: $profile['api_key'] ?? null,
                baseUrl: $profile['base_url'] ?: null,
                size: $record->pixelSize(),
                aspectRatio: $record->aspect_ratio ?: GeneratedImage::DEFAULT_ASPECT_RATIO,
                count: max(1, min((int) $record->variations_requested, GeneratedImage::MAX_VARIATIONS)),
            ));

            $paths = [];

            foreach (array_values($result->images) as $index => $bytes) {
                $webp = $optimizer->optimizeBytes($bytes);
                $filename = sprintf('ai-%s-%d-%d.webp', $record->context, $record->getKey(), $index + 1);
                $path = $storage->putPublic($record->company, 'ai-generated-images', $filename, $webp);
                $paths[] = $path;

                rescue(
                    fn () => Media::recordUpload($storage->publicDiskName(), $path, companyId: $record->company_id),
                    report: false,
                );
            }

            $record->forceFill([
                'status' => GeneratedImage::STATUS_COMPLETED,
                'output_paths' => $paths,
                'provider_label' => $profile['label'],
                'api_format' => $profile['api_format'],
                'model' => $profile['model'],
                'estimated_cost' => round(((float) ($profile['cost_per_image'] ?? 0)) * count($paths), 4),
                'provider_response' => $result->meta ?: null,
                'error_message' => null,
                'generated_at' => now(),
            ])->save();

            $this->notify($notifications, $record, success: true);
        } catch (Throwable $exception) {
            report($exception);

            $record->forceFill([
                'status' => GeneratedImage::STATUS_FAILED,
                'error_message' => $exception instanceof ImageGenerationException
                    ? $exception->getMessage()
                    : 'The image could not be generated. Please try again.',
            ])->save();

            $this->notify($notifications, $record, success: false);
        } finally {
            $context->clear();
        }
    }

    protected function notify(BusinessNotificationService $notifications, GeneratedImage $record, bool $success): void
    {
        if (! $record->user) {
            return;
        }

        $url = rescue(fn (): string => ImageGeneration::getUrl(), rescue: null, report: false);

        $notifications->notifyUser(
            $record->user,
            'ai-image',
            $success ? 'Image ready' : 'Image generation failed',
            $success
                ? sprintf('Your "%s" image is ready in the Image Generation tool.', $record->contextLabel())
                : ($record->error_message ?: 'The image could not be generated.'),
            ['generated_image_id' => (string) $record->getKey()],
            $url,
            $url ? 'Open Image Generation' : null,
        );
    }
}
