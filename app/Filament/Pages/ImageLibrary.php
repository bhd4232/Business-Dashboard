<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\AiTools;
use App\Models\GeneratedImage;
use App\Models\User;
use App\Services\CompanyContext;
use App\Services\ImageGeneration\GeneratedImageAttacher;
use App\Services\ImageGeneration\ImageGovernanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 11_AI_TOOL_MENU_IMAGE_GENERATION_PLAN.md §11 Phase 4 — the searchable
 * generation library. Every image this company has generated, filterable by
 * context / status / who made it / whether it is favourited, a video
 * reference, or already attached to a record. A starred generation doubles
 * as a reusable prompt template — "Reuse prompt" opens the Image Generation
 * tool prefilled from that row (see ImageGeneration::mount()).
 *
 * Same permission as the tool itself (`ai_tools.image_generation`) — anyone
 * who can generate can browse the shared library. Company isolation is the
 * model's own CompanyScope; nothing here widens it.
 */
class ImageLibrary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = AiTools::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Image Library';

    protected static ?string $title = 'Image Library';

    protected string $view = 'filament.pages.image-library';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasPermission('ai_tools.image_generation') ?? false;
    }

    public function hasSelectedCompany(): bool
    {
        $context = app(CompanyContext::class);

        return $context->hasCompany() && ! $context->isAllCompanies();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (GeneratedImage $record): ?string => $record->outputUrls()[0] ?? null)
                    ->square()
                    ->size(56),
                TextColumn::make('prompt')
                    ->label('Prompt')
                    ->wrap()
                    ->lineClamp(2)
                    ->limit(180)
                    ->searchable(['prompt', 'original_prompt'])
                    ->description(fn (GeneratedImage $record): ?string => $record->original_prompt
                        ? 'Enhanced from: '.\Illuminate\Support\Str::limit($record->original_prompt, 90)
                        : null),
                TextColumn::make('context')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => GeneratedImage::CONTEXTS[$state ?? ''] ?? 'General')
                    ->color('gray'),
                TextColumn::make('provider_label')
                    ->label('Provider')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('attached_to')
                    ->label('Attached to')
                    ->state(fn (GeneratedImage $record): ?string => $record->linkedLabel())
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_favorite')
                    ->label('Favourite')
                    ->boolean()
                    ->trueIcon(Heroicon::Star)
                    ->falseIcon(Heroicon::OutlinedStar)
                    ->sortable(),
                IconColumn::make('is_video_reference')
                    ->label('Video ref')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('review_status')
                    ->label('Review')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => GeneratedImage::REVIEW_STATUSES[$state ?? ''] ?? 'No review needed')
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedImage::REVIEW_PENDING => 'warning',
                        GeneratedImage::REVIEW_REJECTED => 'danger',
                        GeneratedImage::REVIEW_APPROVED => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('estimated_cost')
                    ->label('Est. cost')
                    ->numeric(decimalPlaces: 4)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => \Illuminate\Support\Str::headline((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        GeneratedImage::STATUS_COMPLETED => 'success',
                        GeneratedImage::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('context')
                    ->options(GeneratedImage::CONTEXTS),
                SelectFilter::make('status')
                    ->options([
                        GeneratedImage::STATUS_COMPLETED => 'Completed',
                        GeneratedImage::STATUS_PROCESSING => 'Processing',
                        GeneratedImage::STATUS_QUEUED => 'Queued',
                        GeneratedImage::STATUS_FAILED => 'Failed',
                    ]),
                SelectFilter::make('user_id')
                    ->label('Created by')
                    ->options(fn (): array => $this->creatorOptions()),
                SelectFilter::make('review_status')
                    ->label('Review')
                    ->options(GeneratedImage::REVIEW_STATUSES),
                TernaryFilter::make('is_favorite')
                    ->label('Favourites'),
                TernaryFilter::make('is_video_reference')
                    ->label('Video references'),
                TernaryFilter::make('attached')
                    ->label('Attached to a record')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('linked_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('linked_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                Action::make('reusePrompt')
                    ->label('Reuse prompt')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->url(fn (GeneratedImage $record): string => ImageGeneration::getUrl(['from' => $record->getKey()])),
                Action::make('toggleFavorite')
                    ->label(fn (GeneratedImage $record): string => $record->is_favorite ? 'Remove favourite' : 'Add to favourites')
                    ->icon(fn (GeneratedImage $record): string => $record->is_favorite ? 'heroicon-o-star' : 'heroicon-s-star')
                    ->action(function (GeneratedImage $record): void {
                        $record->forceFill(['is_favorite' => ! $record->is_favorite])->save();

                        Notification::make()
                            ->title($record->is_favorite ? 'Added to favourites' : 'Removed from favourites')
                            ->success()
                            ->send();
                    }),
                Action::make('markVideoReference')
                    ->label('Mark as video reference')
                    ->icon(Heroicon::OutlinedFilm)
                    ->visible(fn (GeneratedImage $record): bool => ! $record->is_video_reference && $record->status === GeneratedImage::STATUS_COMPLETED)
                    ->requiresConfirmation()
                    ->action(function (GeneratedImage $record): void {
                        app(GeneratedImageAttacher::class)->markAsVideoReference($record);

                        Notification::make()->title('Marked as a video reference')->success()->send();
                    }),
                Action::make('approveReview')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (GeneratedImage $record): bool => $this->canReview() && $record->review_status === GeneratedImage::REVIEW_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription('Approve this image so its creator can attach it to a product or offer.')
                    ->action(function (GeneratedImage $record): void {
                        app(ImageGovernanceService::class)->review($record, Auth::user(), approved: true);

                        Notification::make()->title('Image approved')->success()->send();
                    }),
                Action::make('rejectReview')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (GeneratedImage $record): bool => $this->canReview() && $record->review_status === GeneratedImage::REVIEW_PENDING)
                    ->schema([
                        Textarea::make('note')
                            ->label('Reason (shown to the creator)')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(function (GeneratedImage $record, array $data): void {
                        app(ImageGovernanceService::class)->review($record, Auth::user(), approved: false, note: $data['note'] ?? null);

                        Notification::make()->title('Image rejected')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No generations yet')
            ->emptyStateDescription('Images you create in the Image Generation tool are collected here.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto);
    }

    public function canReview(): bool
    {
        return Auth::user()?->hasPermission('ai_tools.image_generation.review') ?? false;
    }

    protected function baseQuery(): Builder
    {
        $query = GeneratedImage::query()
            ->where('tool', GeneratedImage::TOOL_IMAGE_GENERATION)
            ->with(['user', 'linked']);

        // The blade already hides the table unless one company is selected;
        // this keeps an all-companies super admin from ever reading across
        // companies even if the table renders.
        return $this->hasSelectedCompany() ? $query : $query->whereRaw('1 = 0');
    }

    /** @return array<int, string> */
    protected function creatorOptions(): array
    {
        if (! $this->hasSelectedCompany()) {
            return [];
        }

        $userIds = GeneratedImage::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
