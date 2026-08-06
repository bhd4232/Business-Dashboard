<?php

namespace App\Filament\Resources\StorefrontComplaints;

use App\Filament\Clusters\Storefront;
use App\Filament\Resources\StorefrontComplaints\Pages\EditStorefrontComplaint;
use App\Filament\Resources\StorefrontComplaints\Pages\ListStorefrontComplaints;
use App\Models\StorefrontComplaint;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class StorefrontComplaintResource extends Resource
{
    protected static ?string $model = StorefrontComplaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $cluster = Storefront::class;

    protected static ?string $navigationLabel = 'Complaints';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'complaint_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Customer complaint')
                ->schema([
                    TextInput::make('complaint_number')->disabled()->dehydrated(false),
                    TextInput::make('order_reference')->label('Submitted order / invoice / phone')->disabled()->dehydrated(false),
                    TextInput::make('order.order_number')->label('Matched order')->disabled()->dehydrated(false),
                    TextInput::make('customer_name')->disabled()->dehydrated(false),
                    TextInput::make('phone')->disabled()->dehydrated(false),
                    Select::make('issue_type')->options(StorefrontComplaint::ISSUE_TYPES)->disabled()->dehydrated(false),
                    Textarea::make('details')->rows(6)->disabled()->dehydrated(false)->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Resolution')
                ->schema([
                    Select::make('status')->options(StorefrontComplaint::STATUSES)->required(),
                    TextInput::make('telegram_status')->label('Telegram delivery')->disabled()->dehydrated(false),
                    Textarea::make('internal_note')->rows(4)->maxLength(3000)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('complaint_number')->label('Complaint')->searchable()->sortable(),
                TextColumn::make('order.order_number')->label('Order')->placeholder('Not matched')->searchable(),
                TextColumn::make('customer_name')->label('Customer')->searchable()->placeholder('Not provided'),
                TextColumn::make('phone')->searchable()->placeholder('Not provided'),
                TextColumn::make('issue_type')->badge()->formatStateUsing(fn (string $state): string => StorefrontComplaint::ISSUE_TYPES[$state] ?? $state),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    StorefrontComplaint::STATUS_RESOLVED => 'success',
                    StorefrontComplaint::STATUS_CLOSED => 'gray',
                    StorefrontComplaint::STATUS_REVIEWING => 'warning',
                    default => 'danger',
                }),
                TextColumn::make('telegram_status')->label('Telegram')->badge()->color(fn (string $state): string => match ($state) {
                    'sent' => 'success',
                    'failed' => 'danger',
                    'disabled' => 'gray',
                    default => 'warning',
                }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(StorefrontComplaint::STATUSES),
                SelectFilter::make('issue_type')->options(StorefrontComplaint::ISSUE_TYPES),
                SelectFilter::make('telegram_status')->options([
                    'pending' => 'Pending',
                    'sent' => 'Sent',
                    'failed' => 'Failed',
                    'disabled' => 'Disabled',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function canViewAny(): bool
    {
        return SchemaFacade::hasTable('storefront_complaints') && (Auth::user()?->canManageSettings() ?? false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStorefrontComplaints::route('/'),
            'edit' => EditStorefrontComplaint::route('/{record}/edit'),
        ];
    }
}
