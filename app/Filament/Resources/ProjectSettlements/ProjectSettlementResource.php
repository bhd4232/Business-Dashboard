<?php

namespace App\Filament\Resources\ProjectSettlements;

use App\Filament\Clusters\Investments;
use App\Filament\Resources\ProjectSettlements\Pages\ListProjectSettlements;
use App\Filament\Resources\ProjectSettlements\Pages\ViewProjectSettlement;
use App\Filament\Resources\ProjectSettlements\RelationManagers\ChannelPartnerPayoutsRelationManager;
use App\Filament\Resources\ProjectSettlements\RelationManagers\PayoutsRelationManager;
use App\Models\ProjectSettlement;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectSettlementResource extends Resource
{
    protected static ?string $model = ProjectSettlement::class;

    protected static ?string $cluster = Investments::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 3;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Settlement Summary')->schema([
                TextEntry::make('project.project_code')->label('Project Code'),
                TextEntry::make('project.name')->label('Project'),
                TextEntry::make('total_revenue')->money('BDT'),
                TextEntry::make('landed_cost')
                    ->label('Total Landed Cost')
                    ->state(fn (ProjectSettlement $record): float => $record->project->totalLandedCost())
                    ->money('BDT'),
                TextEntry::make('local_expense')
                    ->label('Total Local Expense')
                    ->state(fn (ProjectSettlement $record): float => $record->project->totalLocalExpense())
                    ->money('BDT'),
                TextEntry::make('total_cost')->label('Total Direct Cost')->money('BDT'),
                TextEntry::make('net_profit')->money('BDT')->badge()->color('success'),
                TextEntry::make('investor_pool_amount')->money('BDT')->label(fn (ProjectSettlement $record): string => "Investor Pool ({$record->project->investor_share_percent}%)"),
                TextEntry::make('channel_partner_amount')->money('BDT')->label(fn (ProjectSettlement $record): string => "Channel Partner ({$record->project->channel_partner_share_percent}% eligible share)"),
                TextEntry::make('company_net_amount')->money('BDT')->label('Company Net (includes unallocated channel share)'),
                TextEntry::make('annualized_return_percent')->suffix('%')->badge()->placeholder('Not available'),
                TextEntry::make('status')->badge(),
                TextEntry::make('settledBy.name')->label('Settled By'),
                TextEntry::make('settled_at')->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('project.project_code')->label('Project')->searchable(),
            TextColumn::make('project.name')->searchable(),
            TextColumn::make('net_profit')->money('BDT')->sortable(),
            TextColumn::make('investor_pool_amount')->money('BDT'),
            TextColumn::make('company_net_amount')->money('BDT'),
            TextColumn::make('status')->badge(),
            TextColumn::make('settled_at')->dateTime()->sortable(),
        ])->recordActions([ViewAction::make()]);
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
        return false;
    }

    public static function getRelations(): array
    {
        return [PayoutsRelationManager::class, ChannelPartnerPayoutsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListProjectSettlements::route('/'), 'view' => ViewProjectSettlement::route('/{record}')];
    }
}
