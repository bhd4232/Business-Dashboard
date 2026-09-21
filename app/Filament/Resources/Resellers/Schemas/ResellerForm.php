<?php

namespace App\Filament\Resources\Resellers\Schemas;

use App\Models\Customer;
use App\Services\CompanyContext;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class ResellerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Applicant')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->required()
                            ->maxLength(40),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Reseller')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('reseller_status')
                            ->label('Status')
                            ->options(Customer::RESELLER_STATUSES)
                            ->required(),
                        TextInput::make('business_name')
                            ->label('Business / shop name')
                            ->maxLength(255),
                        TextInput::make('reseller_slug')
                            ->label('Store URL slug')
                            ->helperText('The public store is reachable at {your-domain}/store/{slug}. Unique per company. Leave blank to auto-generate on approval.')
                            ->maxLength(255)
                            ->rule('alpha_dash')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule, ?Customer $record): Unique {
                                    $companyId = $record?->company_id ?? app(CompanyContext::class)->company()?->id;

                                    return $rule->where('company_id', $companyId);
                                },
                            ),
                        Textarea::make('reseller_note')
                            ->label('Application / rejection note')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Commission Payout')
                    ->description('Where this reseller\'s delivery commission is sent. Kept separate from wholesale rate, which staff set per product in the Store Products tab.')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('reseller_payout_method')
                            ->label('Payout Method')
                            ->options([
                                'bank' => 'Bank Account (BEFTN)',
                                'mfs_bkash' => 'bKash',
                                'mfs_nagad' => 'Nagad',
                                'mfs_rocket' => 'Rocket',
                            ])
                            ->native(false)
                            ->live(),
                        TextInput::make('reseller_payout_details.bank_name')
                            ->label('Bank Name')
                            ->visible(fn (Get $get): bool => $get('reseller_payout_method') === 'bank'),
                        TextInput::make('reseller_payout_details.branch')
                            ->label('Branch')
                            ->visible(fn (Get $get): bool => $get('reseller_payout_method') === 'bank'),
                        TextInput::make('reseller_payout_details.routing_number')
                            ->label('Routing Number')
                            ->visible(fn (Get $get): bool => $get('reseller_payout_method') === 'bank'),
                        TextInput::make('reseller_payout_details.account_number')
                            ->label('Account Number')
                            ->visible(fn (Get $get): bool => $get('reseller_payout_method') === 'bank'),
                        TextInput::make('reseller_payout_details.account_name')
                            ->label('Account Name')
                            ->visible(fn (Get $get): bool => $get('reseller_payout_method') === 'bank'),
                        TextInput::make('reseller_payout_details.msisdn')
                            ->label('Mobile Number')
                            ->visible(fn (Get $get): bool => in_array($get('reseller_payout_method'), ['mfs_bkash', 'mfs_nagad', 'mfs_rocket'], true)),
                    ])
                    ->columns(2),
            ]);
    }
}
