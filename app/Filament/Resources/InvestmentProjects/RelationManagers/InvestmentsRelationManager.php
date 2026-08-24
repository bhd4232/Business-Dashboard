<?php

namespace App\Filament\Resources\InvestmentProjects\RelationManagers;

use App\Filament\Resources\InvestmentRecords\InvestmentRecordResource;
use App\Models\Investment;
use App\Models\Investor;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvestmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'investments';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status === 'settled' || parent::isReadOnly();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('investor_id')->relationship('investor', 'name')->searchable(['name', 'phone', 'nid_number'])->preload()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                    TextInput::make('phone')->required(),
                    TextInput::make('nid_number')->label('NID Number'),
                ])->createOptionUsing(fn (array $data): int => Investor::query()->create($data)->getKey())->required(),
            TextInput::make('amount')->numeric()->prefix('৳')->minValue(0.01)->required(),
            Select::make('payment_method')->options(Investment::PAYMENT_METHODS)->required(),
            TextInput::make('payment_reference'),
            DatePicker::make('invested_at')->default(now())->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->withCount('securityInstruments'))->columns([
            TextColumn::make('investor.name')->searchable(),
            TextColumn::make('investor.channelPartner.name')->label('Channel Partner')->placeholder('Direct'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT')->summarize(Sum::make()->moneyWithoutTrailingZeroes('BDT')),
            TextColumn::make('payment_method')->badge(),
            TextColumn::make('invested_at')->date(),
            TextColumn::make('security_instruments_count')->label('Security')->badge(),
        ])->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'company_id' => $this->getOwnerRecord()->company_id, 'received_by' => auth()->id()])])
            ->recordActions([
                ViewAction::make()->url(fn (Investment $record): string => InvestmentRecordResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
