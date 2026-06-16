<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Models\UserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('role')
                            ->options(fn (): array => User::roleOptions())
                            ->default(fn (): ?string => User::defaultRole())
                            ->selectablePlaceholder(false)
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Role Name')
                                    ->required()
                                    ->maxLength(255),

                                CheckboxList::make('permissions')
                                    ->label('Permissions')
                                    ->options(User::CUSTOM_PERMISSION_OPTIONS)
                                    ->default(['dashboard.view'])
                                    ->columns(2)
                                    ->bulkToggleable(),
                            ])
                            ->createOptionAction(fn (Action $action): Action => $action
                                ->label('Create role')
                                ->icon('heroicon-o-plus')
                                ->modalHeading('Create user role'))
                            ->createOptionUsing(function (array $data): string {
                                $slug = Str::slug($data['name'], '_');
                                $originalSlug = $slug;
                                $suffix = 2;

                                while (
                                    array_key_exists($slug, User::ROLES) ||
                                    UserRole::query()->where('slug', $slug)->exists()
                                ) {
                                    $slug = "{$originalSlug}_{$suffix}";
                                    $suffix++;
                                }

                                UserRole::query()->create([
                                    'name' => $data['name'],
                                    'slug' => $slug,
                                    'permissions' => $data['permissions'] ?? [],
                                    'is_active' => true,
                                ]);

                                return $slug;
                            })
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Password')
                    ->schema([
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Leave blank while editing to keep the current password.'),
                    ]),
            ]);
    }
}
