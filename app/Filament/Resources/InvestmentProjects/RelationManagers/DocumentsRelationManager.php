<?php

namespace App\Filament\Resources\InvestmentProjects\RelationManagers;

use App\Models\Company;
use App\Models\InvestmentDocument;
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

/**
 * Polymorphic document store — registered on InvestmentProjectResource,
 * ProjectSettlementResource and InvestmentRecordResource (v3 P1.1). Every
 * host model exposes a `documents()` morphMany.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected function ownerCompany(): Company
    {
        return CompanyMedia::require($this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('category')->options(InvestmentDocument::CATEGORIES)->default('other')->required(),
            TextInput::make('label')->maxLength(120)->placeholder('e.g. Settlement sheet — cycle 1'),
            FileUpload::make('file_path')
                ->label('File')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(10240)
                ->disk(fn (): string => app(CompanyStorageService::class)->privateDiskName())
                ->directory(fn (): string => app(CompanyStorageService::class)->privateDirectory($this->ownerCompany(), 'investment-documents'))
                ->visibility('private')
                ->previewable(false)
                ->openable(false)
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('category')
                ->badge()
                ->formatStateUsing(fn (string $state): string => InvestmentDocument::CATEGORIES[$state] ?? $state),
            TextColumn::make('label')->limit(40)->placeholder('-'),
            TextColumn::make('uploadedBy.name')->label('Uploaded by')->placeholder('-'),
            TextColumn::make('created_at')->dateTime()->sortable(),
        ])->headerActions([
            CreateAction::make()->mutateDataUsing(fn (array $data): array => [
                ...$data,
                'company_id' => $this->getOwnerRecord()->company_id,
                'uploaded_by' => auth()->id(),
            ]),
        ])->recordActions([
            Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (InvestmentDocument $record): string => route('investment-documents.download', $record))
                ->openUrlInNewTab(),
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }
}
