<?php

namespace App\Filament\Resources\UserRoles\Schemas;

use App\Models\User;
use App\Models\UserRole;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class UserRoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, ?string $state, callable $set) => $operation === 'create'
                                ? $set('slug', Str::slug($state ?? '', '_'))
                                : null),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(UserRole::class, 'slug', ignoreRecord: true),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Permissions')
                    ->schema([
                        CheckboxList::make('permissions')
                            ->options(User::CUSTOM_PERMISSION_OPTIONS)
                            ->default(['dashboard.view'])
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
            ]);
    }
}
