<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quotation_number')->label('Quotation #')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'rejected', 'expired' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')->money('BDT'),
                TextColumn::make('valid_until')->date()->placeholder('-'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('New quotation')
                    ->url(fn (): string => QuotationResource::getUrl('create', ['lead' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
