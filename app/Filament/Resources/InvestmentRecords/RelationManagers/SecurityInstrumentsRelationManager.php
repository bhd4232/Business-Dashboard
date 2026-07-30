<?php

namespace App\Filament\Resources\InvestmentRecords\RelationManagers;

use App\Models\InvestorSecurityInstrument;
use App\Services\CompanyStorageService;
use App\Support\CompanyMedia;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SecurityInstrumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'securityInstruments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('cheque_number'),
            TextInput::make('cheque_bank_name'),
            TextInput::make('cheque_amount')->numeric()->prefix('BDT')->default(fn (): float => (float) $this->getOwnerRecord()->amount),
            Select::make('cheque_status')->options(InvestorSecurityInstrument::CHEQUE_STATUSES)->default('held_by_investor')->required(),
            TextInput::make('guarantor_name'),
            TextInput::make('guarantor_nid')->label('Guarantor NID'),
            TextInput::make('guarantor_phone')->tel(),
            FileUpload::make('contract_document_path')
                ->label('Signed Contract')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(10240)
                ->disk(fn (): string => app(CompanyStorageService::class)->privateDiskName())
                ->directory(fn (): string => app(CompanyStorageService::class)->privateDirectory(CompanyMedia::require($this->getOwnerRecord()), 'investor-contracts'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('cheque_number')->placeholder('-'),
            TextColumn::make('cheque_bank_name')->label('Bank')->placeholder('-'),
            TextColumn::make('cheque_amount')->money('BDT')->placeholder('-'),
            TextColumn::make('cheque_status')->badge(),
            TextColumn::make('guarantor_name')->placeholder('-'),
            TextColumn::make('contract_document_path')->label('Contract')->formatStateUsing(fn (?string $state): string => $state ? 'Uploaded' : 'Not uploaded')->badge(),
        ])->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'company_id' => $this->getOwnerRecord()->company_id])])
            ->recordActions([
                Action::make('downloadContract')
                    ->label('Download Contract')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (InvestorSecurityInstrument $record): string => route('investor-security-instruments.contract', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (InvestorSecurityInstrument $record): bool => filled($record->contract_document_path)),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
