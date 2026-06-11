<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Expense')
                ->schema([
                    TextInput::make('expense_number')
                        ->label('Expense Number')
                        ->default(fn (): string => Expense::nextExpenseNumber())
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('expense_category_id')
                        ->label('Category')
                        ->relationship('category', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->rows(3)
                                ->columnSpanFull(),
                        ])
                        ->createOptionUsing(function (array $data): int {
                            $slug = Str::slug($data['name']);
                            $originalSlug = $slug;
                            $suffix = 2;

                            while (ExpenseCategory::query()->where('slug', $slug)->exists()) {
                                $slug = "{$originalSlug}-{$suffix}";
                                $suffix++;
                            }

                            return ExpenseCategory::query()->create([
                                'name' => $data['name'],
                                'slug' => $slug,
                                'description' => $data['description'] ?? null,
                                'is_active' => true,
                            ])->getKey();
                        })
                        ->required(),
                    Select::make('account_id')
                        ->label('Pay From Account')
                        ->relationship('account', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->required(),
                    DatePicker::make('expense_date')->default(now())->required(),
                    TextInput::make('amount')->numeric()->prefix('BDT')->minValue(0.01)->required(),
                    TextInput::make('reference')->maxLength(255),
                ])->columns(2),
            Section::make('Note')->schema([
                Textarea::make('note')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
