<?php

namespace App\Filament\Resources\Purchases\RelationManagers;

use App\Models\Container;
use App\Models\Purchase;
use App\Models\Shipment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class ShipmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'shipments';

    protected static ?string $title = 'Shipment & Container Tracking';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return SchemaFacade::hasTable('shipments')
            && $ownerRecord instanceof Purchase
            && ($ownerRecord->status !== 'cancelled' || $ownerRecord->shipments()->exists());
    }

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== 'draft' || parent::isReadOnly();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (): string => match ($this->getOwnerRecord()->status) {
                'received' => 'Received Purchase — Logistics History',
                'cancelled' => 'Cancelled Purchase — Logistics History',
                default => 'Draft Purchase — Shipping Plan',
            })
                ->description(fn (): string => match ($this->getOwnerRecord()->status) {
                    'received' => 'This purchase has been received. Shipment records are retained as read-only history.',
                    'cancelled' => 'This purchase was cancelled. Existing shipment records are retained for audit.',
                    default => 'Plan and update the shipment and container before marking the purchase as received.',
                })
                ->schema([
                    TextInput::make('tracking_number')
                        ->label('Shipment / Tracking Number')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('carrier')
                        ->maxLength(255),
                    Select::make('container_id')
                        ->label('Container')
                        ->relationship('container', 'container_number')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            TextInput::make('container_number')->required()->maxLength(100),
                            TextInput::make('shipping_line')->maxLength(255),
                            TextInput::make('origin')->maxLength(255),
                            TextInput::make('destination')->maxLength(255),
                            Select::make('status')->options(Container::STATUSES)->default('planned')->required(),
                            DatePicker::make('estimated_departure'),
                            DatePicker::make('estimated_arrival'),
                        ])
                        ->createOptionUsing(fn (array $data): int => Container::query()->create($data)->getKey()),
                    Select::make('transport_mode')
                        ->options(['sea' => 'Sea', 'air' => 'Air', 'road' => 'Road', 'rail' => 'Rail'])
                        ->default('sea')
                        ->required(),
                    Select::make('status')
                        ->options(Shipment::STATUSES)
                        ->default('planned')
                        ->required(),
                    DatePicker::make('shipped_at'),
                    DatePicker::make('estimated_arrival'),
                    DatePicker::make('received_at'),
                    Textarea::make('notes')->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->description(fn (): string => match ($this->getOwnerRecord()->status) {
                'received' => 'Received purchases show completed logistics history.',
                'cancelled' => 'Cancelled purchases show audit history only.',
                default => 'Add the shipment and container plan for this draft purchase.',
            })
            ->columns([
                TextColumn::make('tracking_number')->label('Tracking Number')->searchable(),
                TextColumn::make('container.container_number')->label('Container')->placeholder('Not assigned'),
                TextColumn::make('carrier')->placeholder('-'),
                TextColumn::make('transport_mode')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('estimated_arrival')->date()->placeholder('-'),
                TextColumn::make('received_at')->date()->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add shipment plan')
                    ->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
                DeleteAction::make()->visible(fn (): bool => $this->getOwnerRecord()->status === 'draft'),
            ]);
    }
}
