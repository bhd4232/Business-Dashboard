<?php

namespace App\Filament\Resources\Vouchers;

use App\Filament\Resources\Vouchers\Pages\CreateVoucher;
use App\Filament\Resources\Vouchers\Pages\EditVoucher;
use App\Filament\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Resources\Vouchers\Pages\ViewVoucher;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\VoucherService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;
use UnitEnum;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'voucher_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Voucher')->columnSpanFull()->schema([
                Select::make('type')->options(Voucher::TYPES)->required()->live()->default('credit'),
                Select::make('transaction_type')
                    ->label('Transaction Type')
                    ->options(fn () => collect(Voucher::TRANSACTION_TYPES)->except('fund_transfer')->all())
                    ->required()
                    ->live(),
                TextInput::make('amount')->numeric()->prefix('BDT')->required(),
                Select::make('confirmation_source')->options(Voucher::CONFIRMATION_SOURCES)->label('Confirmed Via'),
                TextInput::make('payment_method')->label('Payment Method')->maxLength(60),
                TextInput::make('transaction_id')->label('Transaction / Reference ID')->maxLength(120),
            ])->columns(2),

            Section::make('Parties & Fund')->columnSpanFull()->schema([
                Select::make('customer_id')->relationship('customer', 'name')->searchable()
                    ->visible(fn (Get $get) => $get('transaction_type') === 'customer_payment')
                    ->required(fn (Get $get) => $get('transaction_type') === 'customer_payment'),
                Select::make('supplier_id')->relationship('supplier', 'name')->searchable()
                    ->visible(fn (Get $get) => $get('transaction_type') === 'supplier_payment')
                    ->required(fn (Get $get) => $get('transaction_type') === 'supplier_payment'),
                Select::make('expense_category_id')->relationship('expenseCategory', 'name')->searchable()
                    ->visible(fn (Get $get) => $get('transaction_type') === 'business_expense')
                    ->required(fn (Get $get) => $get('transaction_type') === 'business_expense'),
                Select::make('purchase_id')->relationship('purchase', 'purchase_number')->searchable()
                    ->visible(fn (Get $get) => $get('transaction_type') === 'inventory_purchase')
                    ->required(fn (Get $get) => $get('transaction_type') === 'inventory_purchase'),
                Select::make('fund_source_id')->relationship('fundSource', 'name')->searchable()
                    ->visible(fn (Get $get) => $get('transaction_type') === 'inventory_purchase')
                    ->required(fn (Get $get) => $get('transaction_type') === 'inventory_purchase'),
                Select::make('account_id')->relationship('account', 'name')->searchable()
                    ->label('Account')
                    ->helperText('The account this voucher moves money through.')
                    ->visible(fn (Get $get) => $get('transaction_type') !== 'inventory_purchase')
                    ->required(fn (Get $get) => $get('transaction_type') !== 'inventory_purchase'),
            ])->columns(2),

            Section::make('Notes & Attachments')->columnSpanFull()->schema([
                Textarea::make('purpose')->rows(2),
                Textarea::make('remarks')->rows(2),
                Repeater::make('attachments')
                    ->relationship()
                    ->schema([
                        FileUpload::make('file_path')
                            ->label('File')
                            ->disk(fn (): string => app(CompanyStorageService::class)->privateDiskName())
                            ->directory(fn (): string => static::voucherAttachmentDirectory())
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                            ->maxSize(10240)
                            ->previewable(false)
                            ->openable(false)
                            ->downloadable()
                            ->disabled(fn (): bool => ! app(CompanyContext::class)->hasCompany())
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => static::storeVoucherAttachment($file))
                            ->getUploadedFileUsing(fn (string $file): ?array => static::voucherAttachmentMetadata($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => static::voucherAttachmentDownloadUrl($file))
                            ->required(),
                        TextInput::make('label')->label('Label')->maxLength(120)->placeholder('Payment Screenshot'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add attachment'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => Auth::user()?->canViewAllVouchers()
                ? $query
                : $query->where('submitted_by', Auth::id()))
            ->columns([
                TextColumn::make('voucher_number')->label('Voucher #')->searchable()->sortable(),
                TextColumn::make('type')->badge()->color(fn (string $state) => $state === 'credit' ? 'success' : 'danger'),
                TextColumn::make('transaction_type')->badge()->formatStateUsing(fn (string $state) => Voucher::TRANSACTION_TYPES[$state] ?? $state),
                TextColumn::make('amount')->money('BDT')->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'rejected', 'cancelled' => 'danger',
                    'verified' => 'info',
                    default => 'warning',
                }),
                TextColumn::make('submitter.name')->label('Submitted By'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(Voucher::TYPES),
                SelectFilter::make('status')->options(Voucher::STATUSES),
                SelectFilter::make('transaction_type')->options(Voucher::TRANSACTION_TYPES),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->color('info')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->visible(fn (Voucher $record): bool => $record->isPending() && (Auth::user()?->canVerifyVoucher() ?? false))
                    ->requiresConfirmation()
                    ->action(function (Voucher $record): void {
                        app(VoucherService::class)->verify($record, Auth::user());
                        Notification::make()->title('Voucher verified.')->success()->send();
                    }),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending', 'verified'], true) && (Auth::user()?->canApproveVoucher() ?? false))
                    ->requiresConfirmation()
                    ->action(function (Voucher $record): void {
                        try {
                            app(VoucherService::class)->approve($record, Auth::user());
                            Notification::make()->title('Voucher approved.')->success()->send();
                        } catch (ValidationException $exception) {
                            Notification::make()->title('Could not approve voucher')->body(collect($exception->errors())->flatten()->implode(' '))->danger()->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (Voucher $record): bool => in_array($record->status, ['pending', 'verified'], true) && (Auth::user()?->canRejectVoucher() ?? false))
                    ->requiresConfirmation()
                    ->schema([Textarea::make('reason')->label('Rejection reason')->required()])
                    ->action(function (Voucher $record, array $data): void {
                        app(VoucherService::class)->reject($record, $data['reason'], Auth::user());
                        Notification::make()->title('Voucher rejected.')->warning()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('gray')
                    ->visible(fn (Voucher $record): bool => $record->isPending() && (Auth::user()?->canCancelVoucher() ?? false))
                    ->requiresConfirmation()
                    ->action(function (Voucher $record): void {
                        app(VoucherService::class)->cancel($record);
                        Notification::make()->title('Voucher cancelled.')->send();
                    }),
                Action::make('receipt')
                    ->label('Print Receipt')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->visible(fn (Voucher $record): bool => $record->isCredit() && $record->status === Voucher::STATUS_APPROVED)
                    ->url(fn (Voucher $record): string => URL::signedRoute('vouchers.receipt', ['voucher' => $record->voucher_number]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreateVoucher() ?? false;
    }

    public static function canEdit($record): bool
    {
        return $record instanceof Voucher && $record->isPending()
            && ($record->submitted_by === Auth::id() || (Auth::user()?->canViewAllVouchers() ?? false));
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['submitter', 'fundSource']);
    }

    protected static function voucherAttachmentDirectory(): string
    {
        $company = app(CompanyContext::class)->company();

        return $company
            ? app(CompanyStorageService::class)->privateDirectory($company, 'voucher-attachments')
            : 'unavailable/voucher-attachments';
    }

    protected static function storeVoucherAttachment(TemporaryUploadedFile $file): string
    {
        $company = app(CompanyContext::class)->company();

        if (! $company) {
            throw ValidationException::withMessages([
                'attachments' => 'Select a company before uploading a voucher attachment.',
            ]);
        }

        $extension = Str::of($file->guessExtension() ?: $file->getClientOriginalExtension())
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->limit(12, '')
            ->value();
        $filename = (string) Str::ulid().($extension !== '' ? ".{$extension}" : '');
        $stream = $file->readStream();

        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'attachments' => 'The voucher attachment could not be read. Please upload it again.',
            ]);
        }

        try {
            return app(CompanyStorageService::class)->putPrivate(
                $company,
                'voucher-attachments',
                $filename,
                $stream,
            );
        } finally {
            fclose($stream);
        }
    }

    /**
     * @return array{name: string, size: int, type: ?string, url: string}|null
     */
    protected static function voucherAttachmentMetadata(string $file): ?array
    {
        $company = app(CompanyContext::class)->company();

        if (! $company) {
            return null;
        }

        try {
            $location = app(CompanyStorageService::class)->locatePrivate($file, $company);
            $attachment = static::voucherAttachmentForFile($file);

            if ($location === null || $attachment === null) {
                return null;
            }

            $disk = Storage::disk($location['disk']);

            return [
                'name' => $attachment->label ?: basename($location['path']),
                'size' => $disk->size($location['path']),
                'type' => $disk->mimeType($location['path']),
                'url' => route('voucher-attachments.download', ['attachment' => $attachment->getKey()]),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    protected static function voucherAttachmentDownloadUrl(string $file): ?string
    {
        $attachment = static::voucherAttachmentForFile($file);

        return $attachment
            ? route('voucher-attachments.download', ['attachment' => $attachment->getKey()])
            : null;
    }

    protected static function voucherAttachmentForFile(string $file): ?VoucherAttachment
    {
        $company = app(CompanyContext::class)->company();

        if (! $company) {
            return null;
        }

        return VoucherAttachment::query()
            ->where('company_id', $company->getKey())
            ->where('file_path', $file)
            ->first();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVouchers::route('/'),
            'create' => CreateVoucher::route('/create'),
            'view' => ViewVoucher::route('/{record}'),
            'edit' => EditVoucher::route('/{record}/edit'),
        ];
    }
}
