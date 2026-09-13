<?php

namespace App\Filament\Resources\InvestmentRecords\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WitnessesRelationManager extends RelationManager
{
    protected static string $relationship = 'witnesses';

    protected static ?string $title = 'Deed Witnesses';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('phone')->tel(),
            DatePicker::make('signed_date'),
            Textarea::make('address')->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
            TextColumn::make('phone')->placeholder('-'),
            TextColumn::make('signed_date')->date()->placeholder('-'),
        ])->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'company_id' => $this->getOwnerRecord()->company_id])])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
