<?php

namespace App\Filament\Resources\ExpenseScans\Schemas;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\ExpenseScan;
use App\Models\ExpenseScanItem;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ExpenseScanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Scan')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('status')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => ExpenseScan::STATUSES[$state] ?? $state)
                        ->color(fn (string $state): string => ExpenseScan::statusColor($state)),
                    TextEntry::make('user.name')->label('Uploaded by')->placeholder('—'),
                    TextEntry::make('created_at')->label('Uploaded')->dateTime(),
                    TextEntry::make('publisher.name')->label('Published by')->placeholder('—'),
                    TextEntry::make('published_at')->label('Published')->dateTime()->placeholder('—'),
                    TextEntry::make('model')->label('AI model')->placeholder('—'),
                    TextEntry::make('error_message')->label('Message')->columnSpanFull()
                        ->visible(fn (ExpenseScan $record): bool => filled($record->error_message)),
                ]),
            Section::make(__('Source (photo / pasted text)'))
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    View::make('filament.expense-scans.images')
                        ->viewData(fn (ExpenseScan $record): array => ['scan' => $record]),
                ]),
            Section::make('Expense lines')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('items')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('expense_date')->label('Date')->date()->placeholder('—'),
                            TextEntry::make('amount')->moneyWithoutTrailingZeroes('BDT')->placeholder('—'),
                            TextEntry::make('category.name')->label('Category')
                                ->placeholder(fn (ExpenseScanItem $record): string => filled($record->new_category_name) ? "New: {$record->new_category_name}" : '—'),
                            TextEntry::make('account.name')->label('Pay from')->placeholder('—'),
                            TextEntry::make('description')->columnSpan(3)->placeholder('—'),
                            TextEntry::make('expense.expense_number')->label('Expense')
                                ->placeholder('Not published')
                                ->url(fn (ExpenseScanItem $record): ?string => $record->expense_id
                                    ? ExpenseResource::getUrl('view', ['record' => $record->expense_id])
                                    : null),
                        ]),
                ]),
        ]);
    }
}
