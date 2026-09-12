<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Lead Information')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->maxLength(30),

                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),

                        Select::make('source')
                            ->options(Lead::SOURCES)
                            ->default('other')
                            ->required()
                            ->native(false),

                        Select::make('status')
                            ->options(Lead::STATUSES)
                            ->default('new')
                            ->required()
                            ->native(false),

                        TextInput::make('estimated_value')
                            ->label('Estimated Value')
                            ->numeric()
                            ->prefix('৳')
                            ->minValue(0),
                    ])
                    ->columns(2),

                Section::make('Follow-up')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('assigned_to')
                            ->label('Assigned To')
                            ->relationship('assignedUser', 'name')
                            ->searchable()
                            ->preload(),

                        DateTimePicker::make('next_follow_up_at')
                            ->label('Next Follow-up At')
                            ->seconds(false),
                    ])
                    ->columns(2),

                Section::make('Details')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('interest')
                            ->label('Interested In')
                            ->rows(2),

                        Textarea::make('note')
                            ->rows(2),
                    ]),
                Section::make('Sales qualification')->schema([
                    Select::make('temperature')->options(['cold' => 'Cold', 'warm' => 'Warm', 'hot' => 'Hot'])->required()->default('cold'),
                    Toggle::make('temperature_locked')->label('Keep staff-selected temperature')->helperText('Prevents later AI qualification from changing this rating.'),
                    TextInput::make('qualification_score')->numeric()->disabled()->dehydrated(false),
                    Textarea::make('qualification_summary')->label('Confirmed preferences and summary')->disabled()->dehydrated(false)->rows(5),
                ]),
            ]);
    }
}
