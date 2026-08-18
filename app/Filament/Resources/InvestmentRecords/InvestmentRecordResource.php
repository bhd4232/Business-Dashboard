<?php

namespace App\Filament\Resources\InvestmentRecords;

use App\Filament\Clusters\Investments;
use App\Filament\Resources\InvestmentProjects\InvestmentProjectResource;
use App\Filament\Resources\InvestmentRecords\Pages\ViewInvestmentRecord;
use App\Filament\Resources\InvestmentRecords\RelationManagers\SecurityInstrumentsRelationManager;
use App\Models\Investment;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class InvestmentRecordResource extends Resource
{
    protected static ?string $model = Investment::class;

    protected static ?string $cluster = Investments::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Investment')->schema([
                TextEntry::make('project.project_code')->label('Project'),
                TextEntry::make('investor.name'),
                TextEntry::make('amount')->moneyWithoutTrailingZeroes('BDT'),
                TextEntry::make('payment_method')->badge(),
                TextEntry::make('payment_reference')->placeholder('-'),
                TextEntry::make('invested_at')->date(),
            ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [SecurityInstrumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['view' => ViewInvestmentRecord::route('/{record}')];
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return InvestmentProjectResource::getUrl(
            'index',
            $parameters,
            $isAbsolute,
            $panel,
            $tenant,
            $shouldGuessMissingParameters,
        );
    }
}
