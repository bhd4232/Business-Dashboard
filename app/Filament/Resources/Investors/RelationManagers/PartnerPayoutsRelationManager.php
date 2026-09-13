<?php

namespace App\Filament\Resources\Investors\RelationManagers;

use App\Models\Investor;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only channel-partner earnings history (v3 P2.1) — only meaningful for
 * an investor flagged `is_channel_partner`. Payouts themselves are only ever
 * created/marked-paid from the settlement's own
 * ChannelPartnerPayoutsRelationManager.
 */
class PartnerPayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'channelPartnerPayouts';

    protected static ?string $title = 'Channel Partner Payout History';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Investor && $ownerRecord->is_channel_partner;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('settlement.project'))->columns([
            TextColumn::make('settlement.project.project_code')->label('Project'),
            TextColumn::make('amount')->moneyWithoutTrailingZeroes('BDT')->summarize(Sum::make()->moneyWithoutTrailingZeroes('BDT')),
            TextColumn::make('payment_status')->badge(),
            TextColumn::make('paid_at')->date()->placeholder('-'),
        ])->recordActions([
            Action::make('report')->label('Settlement')->icon('heroicon-o-document-text')
                ->url(fn ($record): string => route('investments.reports.project-register', $record->settlement->project_id))
                ->openUrlInNewTab(),
        ]);
    }
}
