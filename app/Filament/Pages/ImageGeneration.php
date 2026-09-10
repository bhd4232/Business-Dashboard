<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\AiTools;
use App\Jobs\GenerateImageJob;
use App\Models\GeneratedImage;
use App\Models\Offer;
use App\Models\Product;
use App\Services\AuditLogService;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\GeneratedImageAttacher;
use App\Services\ImageGeneration\ImageAttachmentException;
use App\Services\ImageGeneration\ImageGovernanceService;
use App\Services\ImageGeneration\ImageProviderSettingsService;
use App\Services\PromptEnhancement\PromptEnhancementService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * The Image Generation tool. The prompt form + generation controls live
 * here; the chosen provider profile and its API key are configured on
 * AI Tools → Image Providers. Generation runs in
 * GenerateImageJob (queued) and the results gallery polls itself until every
 * pending row is finished.
 *
 * Prompt, context, aspect ratio, variation count, provider pick, queued
 * generation, polled gallery, per-image download, and the shared ✨ Enhance
 * prompt rewriter (before/after, always explicit). Each finished image can
 * then be attached (Phase 3) to a product's featured/gallery image or an
 * offer's landing-page cover banner, or the whole generation flagged as a
 * reference for the future AI Video Generation tool.
 */
class ImageGeneration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $cluster = AiTools::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Image Generation';

    protected static ?string $title = 'Image Generation';

    protected string $view = 'filament.pages.image-generation';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * `?from=<generated_image_id>` — the library's "Reuse prompt" link. Read
     * once in mount() to prefill the form, then irrelevant.
     */
    #[Url]
    public ?string $from = null;

    /** Per-request memo so one render does not query the same 12 rows three times. */
    private ?\Illuminate\Support\Collection $recentGenerationsCache = null;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('ai_tools.image_generation') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->initialFormState());
    }

    /**
     * Blank form by default; prefilled from an existing generation when the
     * Image Library links here with `?from=<id>` ("Reuse prompt").
     *
     * @return array<string, mixed>
     */
    protected function initialFormState(): array
    {
        $state = [
            'prompt' => '',
            'original_prompt' => null,
            'context' => 'general',
            'aspect_ratio' => GeneratedImage::DEFAULT_ASPECT_RATIO,
            'variations' => 1,
            'provider_profile_id' => $this->defaultProfileId(),
        ];

        $fromId = (int) $this->from;

        if ($fromId <= 0 || ! $this->hasSelectedCompany()) {
            return $state;
        }

        $source = GeneratedImage::query()
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->find($fromId);

        if ($source === null) {
            return $state;
        }

        return [
            ...$state,
            'prompt' => $source->prompt,
            'context' => array_key_exists($source->context, GeneratedImage::CONTEXTS) ? $source->context : 'general',
            'aspect_ratio' => array_key_exists((string) $source->aspect_ratio, GeneratedImage::ASPECT_RATIOS)
                ? $source->aspect_ratio
                : GeneratedImage::DEFAULT_ASPECT_RATIO,
            'variations' => max(1, min((int) $source->variations_requested, GeneratedImage::MAX_VARIATIONS)),
            'provider_profile_id' => app(ImageProviderSettingsService::class)->find(app(CompanyContext::class)->company(), $source->provider_profile_id)
                ? $source->provider_profile_id
                : $this->defaultProfileId(),
        ];
    }

    public function hasSelectedCompany(): bool
    {
        $context = app(CompanyContext::class);

        return $context->hasCompany() && ! $context->isAllCompanies();
    }

    /** @return array<int, array<string, mixed>> */
    public function providerProfiles(): array
    {
        if (! $this->hasSelectedCompany()) {
            return [];
        }

        return app(ImageProviderSettingsService::class)->list(app(CompanyContext::class)->company());
    }

    public function hasConfiguredProvider(): bool
    {
        return collect($this->providerProfiles())
            ->contains(fn (array $profile): bool => filled($profile['model']) && ($profile['has_api_key'] || $profile['api_format'] === 'custom'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(2)
            ->components([
                Hidden::make('original_prompt'),
                Textarea::make('prompt')
                    ->label('Prompt')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000)
                    ->live(onBlur: true)
                    ->placeholder('A studio product photo of a matte-black stainless steel water bottle on a warm neutral background, soft daylight, subtle shadow')
                    ->columnSpanFull(),
                Select::make('context')
                    ->label('What is this for?')
                    ->options(GeneratedImage::CONTEXTS)
                    ->default('general')
                    ->required()
                    ->native(false),
                Select::make('aspect_ratio')
                    ->label('Aspect ratio')
                    ->options(collect(GeneratedImage::ASPECT_RATIOS)->map(fn (array $r): string => $r['label'])->all())
                    ->default(GeneratedImage::DEFAULT_ASPECT_RATIO)
                    ->required()
                    ->native(false),
                Select::make('variations')
                    ->label('How many images?')
                    ->options(collect(range(1, GeneratedImage::MAX_VARIATIONS))
                        ->mapWithKeys(fn (int $n): array => [$n => $n === 1 ? '1 image' : "{$n} images"])
                        ->all())
                    ->default(1)
                    ->required()
                    ->native(false),
                Select::make('provider_profile_id')
                    ->label('Provider')
                    ->options(fn (): array => collect($this->providerProfiles())
                        ->mapWithKeys(fn (array $p): array => [$p['id'] => $p['label'].($p['is_default'] ? ' (default)' : '')])
                        ->all())
                    ->default(fn (): ?string => $this->defaultProfileId())
                    ->required()
                    ->native(false)
                    ->helperText(fn (): ?string => $this->hasConfiguredProvider()
                        ? null
                        : 'No image provider is configured yet. A super admin can add one on AI Tools → Image Providers.'),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->enhancePromptAction(),
            Action::make('generate')
                ->label('Generate')
                ->icon(Heroicon::OutlinedSparkles)
                ->keyBindings(['mod+enter'])
                ->disabled(fn (): bool => ! $this->hasSelectedCompany() || ! $this->hasConfiguredProvider())
                ->action('generate'),
        ];
    }

    /**
     * The shared ✨ Enhance rewriter. Only shown when a super admin has set
     * the enhancer up (AI Tools → Prompt Enhancer). Always explicit: the
     * modal shows the original next to the rewrite; the user edits/accepts or
     * cancels. Accepting records the pre-enhancement text as
     * `original_prompt` on the generation.
     */
    protected function enhancePromptAction(): Action
    {
        return Action::make('enhancePrompt')
            ->label('✨ Enhance')
            ->icon(Heroicon::OutlinedSparkles)
            ->color('gray')
            ->visible(fn (): bool => $this->hasSelectedCompany() && $this->promptEnhancerAvailable())
            ->disabled(fn (): bool => blank($this->data['prompt'] ?? null))
            ->modalHeading('Enhance prompt')
            ->modalDescription('The rewrite adapts to the selected provider and your brand style. Edit it if you like, then use it — or keep your original.')
            ->modalSubmitActionLabel('Use enhanced')
            ->modalCancelActionLabel('Keep original')
            ->fillForm(function (): array {
                $raw = trim((string) ($this->form->getState()['prompt'] ?? ''));

                try {
                    $enhanced = app(PromptEnhancementService::class)->enhance(
                        $raw,
                        $this->currentContextKey(),
                        $this->selectedProviderApiFormat(),
                        app(CompanyContext::class)->company(),
                    );
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->title('Could not enhance the prompt')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    $enhanced = $raw;
                }

                return ['original' => $raw, 'enhanced' => $enhanced];
            })
            ->schema([
                Textarea::make('original')
                    ->label('Your prompt')
                    ->rows(3)
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('enhanced')
                    ->label('Enhanced')
                    ->rows(6)
                    ->required()
                    ->maxLength(2000),
            ])
            ->action(function (array $data): void {
                $enhanced = trim((string) ($data['enhanced'] ?? ''));
                $original = trim((string) ($this->data['prompt'] ?? ''));

                if ($enhanced === '' || $enhanced === $original) {
                    return;
                }

                $this->data['original_prompt'] = $original;
                $this->data['prompt'] = $enhanced;
                $this->form->fill($this->data);

                Notification::make()->title('Prompt updated')->success()->send();
            });
    }

    public function promptEnhancerAvailable(): bool
    {
        return $this->hasSelectedCompany()
            && app(PromptEnhancementService::class)->isAvailable(app(CompanyContext::class)->company());
    }

    protected function currentContextKey(): string
    {
        $context = array_key_exists($this->data['context'] ?? '', GeneratedImage::CONTEXTS)
            ? $this->data['context']
            : 'general';

        return GeneratedImage::TOOL_IMAGE_GENERATION.'.'.$context;
    }

    protected function selectedProviderApiFormat(): ?string
    {
        $id = $this->data['provider_profile_id'] ?? null;

        return collect($this->providerProfiles())->firstWhere('id', $id)['api_format'] ?? null;
    }

    public function generate(): void
    {
        if (! $this->hasSelectedCompany()) {
            Notification::make()->title('Select a company first')->warning()->send();

            return;
        }

        $state = $this->form->getState();
        $company = app(CompanyContext::class)->company();
        $settings = app(ImageProviderSettingsService::class);

        $profile = $settings->find($company, $state['provider_profile_id'] ?? null)
            ?? $settings->default($company);

        if ($profile === null || ! $settings->isConfigured($profile)) {
            throw ValidationException::withMessages([
                'data.provider_profile_id' => 'Pick a provider that has a model and API key configured on AI Tools → Image Providers.',
            ]);
        }

        $aspectRatio = array_key_exists($state['aspect_ratio'] ?? '', GeneratedImage::ASPECT_RATIOS)
            ? $state['aspect_ratio']
            : GeneratedImage::DEFAULT_ASPECT_RATIO;

        $context = array_key_exists($state['context'] ?? '', GeneratedImage::CONTEXTS)
            ? $state['context']
            : 'general';

        $variations = max(1, min((int) ($state['variations'] ?? 1), GeneratedImage::MAX_VARIATIONS));

        $user = Auth::user();
        $governance = app(ImageGovernanceService::class);

        if ($user !== null && $governance->wouldExceedCap($company, $user, $variations)) {
            $remaining = $governance->remainingThisMonth($company, $user);

            throw ValidationException::withMessages([
                'data.variations' => $remaining === 0
                    ? 'You have used your monthly image allowance for this company. It resets on the 1st.'
                    : "That would go over your monthly image allowance — you have {$remaining} left this month.",
            ]);
        }

        $finalPrompt = trim((string) $state['prompt']);
        $originalPrompt = trim((string) ($state['original_prompt'] ?? ''));

        $reviewStatus = ($user !== null && $governance->roleRequiresReview($company, $user->effectiveRole()))
            ? GeneratedImage::REVIEW_PENDING
            : GeneratedImage::REVIEW_NOT_REQUIRED;

        $record = GeneratedImage::query()->create([
            'user_id' => Auth::id(),
            'tool' => GeneratedImage::TOOL_IMAGE_GENERATION,
            'context' => $context,
            'original_prompt' => ($originalPrompt !== '' && $originalPrompt !== $finalPrompt) ? $originalPrompt : null,
            'prompt' => $finalPrompt,
            'provider_profile_id' => $profile['id'],
            'provider_label' => $profile['label'],
            'api_format' => $profile['api_format'],
            'model' => $profile['model'],
            'aspect_ratio' => $aspectRatio,
            'variations_requested' => $variations,
            'is_video_reference' => $context === 'video_reference',
            'review_status' => $reviewStatus,
            'status' => GeneratedImage::STATUS_QUEUED,
        ]);

        app(AuditLogService::class)->record('ai_image.generated', $record, null, [
            'prompt' => $finalPrompt,
            'context' => $context,
            'provider' => $profile['label'],
            'api_format' => $profile['api_format'],
            'model' => $profile['model'],
            'variations' => $variations,
            'review_status' => $reviewStatus,
        ]);

        GenerateImageJob::dispatch($record->getKey());

        $this->recentGenerationsCache = null;

        $this->form->fill([
            'prompt' => '',
            'original_prompt' => null,
            'context' => $context,
            'aspect_ratio' => $aspectRatio,
            'variations' => $record->variations_requested,
            'provider_profile_id' => $profile['id'],
        ]);

        Notification::make()
            ->title('Generation started')
            ->body($reviewStatus === GeneratedImage::REVIEW_PENDING
                ? 'Your image is being generated. It will need a reviewer’s approval before it can be attached to a product or offer.'
                : 'Your image is being generated — it will appear below and you will be notified when it is ready.')
            ->success()
            ->send();
    }

    /**
     * How many more images the current user may generate this month, or null
     * when their role has no cap. Surfaced under the form.
     */
    public function remainingMonthlyAllowance(): ?int
    {
        $user = Auth::user();

        if ($user === null || ! $this->hasSelectedCompany()) {
            return null;
        }

        return app(ImageGovernanceService::class)->remainingThisMonth(app(CompanyContext::class)->company(), $user);
    }

    /** @return \Illuminate\Support\Collection<int, GeneratedImage> */
    public function recentGenerations(): \Illuminate\Support\Collection
    {
        if ($this->recentGenerationsCache !== null) {
            return $this->recentGenerationsCache;
        }

        if (! $this->hasSelectedCompany()) {
            return $this->recentGenerationsCache = collect();
        }

        return $this->recentGenerationsCache = GeneratedImage::query()
            ->where('user_id', Auth::id())
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->with('linked')
            ->latest()
            ->limit(12)
            ->get();
    }

    public function hasPendingGenerations(): bool
    {
        return $this->recentGenerations()->contains(fn (GeneratedImage $row): bool => ! $row->isTerminal());
    }

    protected function defaultProfileId(): ?string
    {
        if (! $this->hasSelectedCompany()) {
            return null;
        }

        return app(ImageProviderSettingsService::class)
            ->default(app(CompanyContext::class)->company())['id'] ?? null;
    }

    // --- Phase 3: attach a finished image to a record -----------------------

    /**
     * Add one finished output to a product — its featured image (also what
     * Meta ad creatives use) or its gallery. Mounted per image from the
     * gallery with `{ generation, image }` arguments.
     */
    public function attachToProductAction(): Action
    {
        return Action::make('attachToProduct')
            ->label('Add to a product')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->modalHeading('Add this image to a product')
            ->modalSubmitActionLabel('Attach')
            ->schema([
                Select::make('product_id')
                    ->label('Product')
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->options(fn (): array => $this->productOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $this->productOptions($search)),
                Select::make('slot')
                    ->label('Use as')
                    ->required()
                    ->native(false)
                    ->default(GeneratedImageAttacher::PRODUCT_SLOT_GALLERY)
                    ->options([
                        GeneratedImageAttacher::PRODUCT_SLOT_GALLERY => 'Add to the product gallery',
                        GeneratedImageAttacher::PRODUCT_SLOT_FEATURED => 'Set as the featured image',
                    ])
                    ->helperText('The featured image is also the photo Meta ad creatives use for this product.'),
            ])
            ->action(function (array $arguments, array $data): void {
                $generation = $this->resolveGeneration($arguments);
                $product = $generation !== null
                    ? Product::query()->find($data['product_id'] ?? null)
                    : null;

                if ($generation === null || $product === null) {
                    Notification::make()->title('Could not attach the image')->danger()->send();

                    return;
                }

                try {
                    $landedIn = app(GeneratedImageAttacher::class)->attachToProduct(
                        $generation,
                        (int) ($arguments['image'] ?? 0),
                        $product,
                        (string) ($data['slot'] ?? GeneratedImageAttacher::PRODUCT_SLOT_GALLERY),
                    );
                } catch (ImageAttachmentException $exception) {
                    Notification::make()
                        ->title('Could not attach the image')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                app(AuditLogService::class)->record('ai_image.attached', $generation, null, [
                    'target' => 'Product #'.$product->getKey().' — '.$product->name,
                    'slot' => $landedIn,
                    'image_index' => (int) ($arguments['image'] ?? 0),
                ]);

                $this->recentGenerationsCache = null;

                Notification::make()
                    ->title('Added to '.$product->name)
                    ->body('The image is now on the product '.$landedIn.'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Set one finished output as an offer / landing page's cover banner.
     * Mounted per image with `{ generation, image }` arguments.
     */
    public function attachToOfferAction(): Action
    {
        return Action::make('attachToOffer')
            ->label('Set as an offer banner')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->modalHeading('Use this image as an offer’s cover banner')
            ->modalDescription('Replaces the offer’s current cover image — the landing-page hero banner and social share image.')
            ->modalSubmitActionLabel('Set banner')
            ->schema([
                Select::make('offer_id')
                    ->label('Offer / landing page')
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->options(fn (): array => $this->offerOptions())
                    ->getSearchResultsUsing(fn (string $search): array => $this->offerOptions($search)),
            ])
            ->action(function (array $arguments, array $data): void {
                $generation = $this->resolveGeneration($arguments);
                $offer = $generation !== null
                    ? Offer::query()->find($data['offer_id'] ?? null)
                    : null;

                if ($generation === null || $offer === null) {
                    Notification::make()->title('Could not attach the image')->danger()->send();

                    return;
                }

                try {
                    app(GeneratedImageAttacher::class)->attachToOffer(
                        $generation,
                        (int) ($arguments['image'] ?? 0),
                        $offer,
                    );
                } catch (ImageAttachmentException $exception) {
                    Notification::make()
                        ->title('Could not attach the image')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                app(AuditLogService::class)->record('ai_image.attached', $generation, null, [
                    'target' => 'Offer #'.$offer->getKey().' — '.$offer->title,
                    'slot' => 'cover banner',
                    'image_index' => (int) ($arguments['image'] ?? 0),
                ]);

                $this->recentGenerationsCache = null;

                Notification::make()
                    ->title('Cover banner updated')
                    ->body('“'.$offer->title.'” now uses this image.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Flag the whole generation as a reference image for the future AI Video
     * Generation tool. Mounted per generation with a `{ generation }`
     * argument.
     */
    public function useAsVideoReferenceAction(): Action
    {
        return Action::make('useAsVideoReference')
            ->label('Mark as video reference')
            ->icon(Heroicon::OutlinedFilm)
            ->requiresConfirmation()
            ->modalHeading('Mark as a video reference')
            ->modalDescription('Flags this generation for the upcoming AI Video Generation tool. It stays in your library and keeps every other use.')
            ->modalSubmitActionLabel('Mark as reference')
            ->action(function (array $arguments): void {
                $generation = $this->resolveGeneration($arguments);

                if ($generation === null) {
                    Notification::make()->title('Could not update the generation')->danger()->send();

                    return;
                }

                app(GeneratedImageAttacher::class)->markAsVideoReference($generation);

                $this->recentGenerationsCache = null;

                Notification::make()->title('Marked as a video reference')->success()->send();
            });
    }

    /**
     * Star / unstar a generation for the Image Library. Mounted per
     * generation with a `{ generation }` argument.
     */
    public function toggleFavoriteAction(): Action
    {
        return Action::make('toggleFavorite')
            ->label('Favourite')
            ->icon(Heroicon::OutlinedStar)
            ->action(function (array $arguments): void {
                $generation = $this->resolveGeneration($arguments);

                if ($generation === null) {
                    return;
                }

                $generation->forceFill(['is_favorite' => ! $generation->is_favorite])->save();

                $this->recentGenerationsCache = null;

                Notification::make()
                    ->title($generation->is_favorite ? 'Added to favourites' : 'Removed from favourites')
                    ->success()
                    ->send();
            });
    }

    /**
     * Resolve a GeneratedImage from a mounted action's `generation` argument,
     * scoped to the current user's own image-generation rows (and, through
     * CompanyScope, the selected company).
     */
    protected function resolveGeneration(array $arguments): ?GeneratedImage
    {
        $id = (int) ($arguments['generation'] ?? 0);

        if ($id <= 0 || ! $this->hasSelectedCompany()) {
            return null;
        }

        return GeneratedImage::query()
            ->where('user_id', Auth::id())
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->find($id);
    }

    /** @return array<int, string> */
    protected function productOptions(string $search = ''): array
    {
        if (! $this->hasSelectedCompany()) {
            return [];
        }

        return Product::query()
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(30)
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    protected function offerOptions(string $search = ''): array
    {
        if (! $this->hasSelectedCompany()) {
            return [];
        }

        return Offer::query()
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->limit(30)
            ->pluck('title', 'id')
            ->all();
    }
}
