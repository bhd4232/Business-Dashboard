<?php

namespace App\Filament\Resources\InvestmentProjects\RelationManagers;

use App\Models\ProjectCostItem;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class CostItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'costItems';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status === 'settled' || parent::isReadOnly();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')->options(ProjectCostItem::CATEGORIES)->required(),
            TextInput::make('label')->required(),
            TextInput::make('amount')->numeric()->prefix('BDT')->minValue(0)->required(),
            Select::make('purchase_id')->relationship('purchase', 'purchase_number')->searchable()->preload(),
            Textarea::make('remarks')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('category')
                ->badge()
                ->formatStateUsing(fn (string $state): string => ProjectCostItem::CATEGORIES[$state])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('label')->searchable(),
            TextColumn::make('purchase.purchase_number')->label('Purchase')->placeholder('-'),
            TextColumn::make('amount')->money('BDT')->summarize(Sum::make()->money('BDT')),
            TextColumn::make('remarks')->limit(40)->placeholder('-'),
        ])->groups([
            Group::make('category')
                ->label('Cost Category')
                ->getTitleFromRecordUsing(fn (ProjectCostItem $record): string => ProjectCostItem::CATEGORIES[$record->category])
                ->collapsible(),
        ])->defaultGroup('category')
            ->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'company_id' => $this->getOwnerRecord()->company_id])])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
