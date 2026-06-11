<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Models\Account;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('type')->options(Account::TYPES)->default('cash')->required(),
                    Toggle::make('is_active')->label('Active')->default(true),
                ])->columns(2),
            Section::make('Balance')
                ->schema([
                    TextInput::make('opening_balance')->numeric()->prefix('BDT')->default(0)->required(),
                    TextInput::make('current_balance')->numeric()->prefix('BDT')->disabled()->dehydrated(false),
                ])->columns(2),
        ]);
    }
}
