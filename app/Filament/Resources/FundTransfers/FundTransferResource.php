<?php

namespace App\Filament\Resources\FundTransfers;

use App\Filament\Resources\FundTransfers\Pages\CreateFundTransfer;
use App\Filament\Resources\FundTransfers\Pages\ListFundTransfers;
use App\Models\FundTransfer;
use App\Services\FundTransferService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FundTransferResource extends Resource
{
    protected static ?string $model = FundTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Fund Transfers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fund Transfer')->schema([
                Select::make('from_account_id')->relationship('fromAccount', 'name')->label('From Account')->required(),
                Select::make('to_account_id')->relationship('toAccount', 'name')->label('To Account')->required(),
                TextInput::make('amount')->numeric()->prefix('BDT')->required(),
                Textarea::make('remarks')->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transfer_number')->label('Transfer #')->searchable()->sortable(),
                TextColumn::make('fromAccount.name')->label('From'),
                TextColumn::make('toAccount.name')->label('To'),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'rejected' => 'danger',
                    default => 'warning',
                }),
                TextColumn::make('requester.name')->label('Requested By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(FundTransfer::STATUSES)])
            ->recordActions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (FundTransfer $record): bool => $record->status === FundTransfer::STATUS_PENDING && (Auth::user()?->canApproveFundTransfer() ?? false))
                    ->requiresConfirmation()
                    ->action(function (FundTransfer $record): void {
                        app(FundTransferService::class)->approve($record, Auth::user());
                        Notification::make()->title('Fund transfer approved.')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (FundTransfer $record): bool => $record->status === FundTransfer::STATUS_PENDING && (Auth::user()?->canApproveFundTransfer() ?? false))
                    ->requiresConfirmation()
                    ->action(function (FundTransfer $record): void {
                        app(FundTransferService::class)->reject($record, Auth::user());
                        Notification::make()->title('Fund transfer rejected.')->warning()->send();
                    }),
            ]);
    }

    public static function canViewAny(): bool
    {
        return (Auth::user()?->canCreateFundTransfer() ?? false) || (Auth::user()?->canApproveFundTransfer() ?? false);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreateFundTransfer() ?? false;
    }

    public static function canEdit($record): bool
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
            'index' => ListFundTransfers::route('/'),
            'create' => CreateFundTransfer::route('/create'),
        ];
    }
}
