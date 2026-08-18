<?php

namespace App\Filament\Resources\Investors\RelationManagers;

use App\Filament\Resources\InvestmentRecords\InvestmentRecordResource;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvestmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'investments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('project.project_code')->label('Project'),
            TextColumn::make('project.name')->label('Project Name'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT'),
            TextColumn::make('invested_at')->date(),
        ])->recordActions([ViewAction::make()->url(fn ($record): string => InvestmentRecordResource::getUrl('view', ['record' => $record]))]);
    }
}
