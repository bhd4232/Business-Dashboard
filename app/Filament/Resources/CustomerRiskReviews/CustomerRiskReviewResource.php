<?php

namespace App\Filament\Resources\CustomerRiskReviews;

use App\Filament\Clusters\CustomerSuccess;
use App\Filament\Resources\CustomerRiskReviews\Pages\ListCustomerRiskReviews;
use App\Models\CustomerRiskProfile;
use App\Models\CustomerRiskReview;
use App\Models\User;
use App\Services\CustomerRiskService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class CustomerRiskReviewResource extends Resource
{
    protected static ?string $model = CustomerRiskReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $cluster = CustomerSuccess::class;

    protected static ?string $navigationLabel = 'Risk Reviews';

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.order_number')->label('Order')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Customer')->searchable()->placeholder('-'),
                TextColumn::make('approval_type')->badge()->formatStateUsing(fn (string $state): string => CustomerRiskReview::TYPES[$state] ?? $state),
                TextColumn::make('risk_score')->label('Score')->sortable(),
                TextColumn::make('risk_level')->badge()->formatStateUsing(fn (string $state): string => CustomerRiskProfile::LEVELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        CustomerRiskProfile::LEVEL_LOW => 'success',
                        CustomerRiskProfile::LEVEL_MEDIUM => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => CustomerRiskReview::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        CustomerRiskReview::STATUS_APPROVED => 'success',
                        CustomerRiskReview::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('reason')->limit(60)->placeholder('-'),
                TextColumn::make('reviewer.name')->label('Reviewed By')->placeholder('-'),
                TextColumn::make('reviewed_at')->dateTime()->sortable()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CustomerRiskReview::STATUSES),
                SelectFilter::make('approval_type')->options(CustomerRiskReview::TYPES),
                SelectFilter::make('risk_level')->options(CustomerRiskProfile::LEVELS),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (CustomerRiskReview $record): bool => $record->status === CustomerRiskReview::STATUS_PENDING && self::canReview($record))
                    ->schema([Textarea::make('review_note')->label('Approval note')->rows(3)])
                    ->action(fn (CustomerRiskReview $record, array $data): CustomerRiskReview => app(CustomerRiskService::class)->approveReview($record, $data['review_note'] ?? null)),
                Action::make('reject')
                    ->label('Reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (CustomerRiskReview $record): bool => $record->status === CustomerRiskReview::STATUS_PENDING && self::canReview($record))
                    ->requiresConfirmation()
                    ->schema([Textarea::make('review_note')->label('Reject reason')->required()->rows(3)])
                    ->action(fn (CustomerRiskReview $record, array $data): CustomerRiskReview => app(CustomerRiskService::class)->rejectReview($record, $data['review_note'] ?? null)),
            ]);
    }

    public static function canViewAny(): bool
    {
        return Schema::hasTable('customer_risk_reviews') && (Auth::user()?->hasPermission('sales.view') ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerRiskReviews::route('/')];
    }

    protected static function canReview(CustomerRiskReview $review): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($review->approval_type === CustomerRiskReview::TYPE_OWNER) {
            return $user->isSuperAdmin();
        }

        return $user->isSuperAdmin() || $user->effectiveRole() === 'manager';
    }
}
