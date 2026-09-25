<?php

namespace App\Filament\Resources\ExpenseScans\Schemas;

use App\Models\Account;
use App\Models\ExpenseCategory;
use App\Models\ExpenseScan;
use App\Models\ExpenseScanItem;
use App\Services\CompanyContext;
use App\Services\CompanyStorageService;
use App\Services\ExpenseScan\ExpenseScanPublisher;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ExpenseScanForm
{
    /** Review/correct form (edit page): the photo beside the AI's draft lines. */
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('Source (photo / pasted text)'))
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    View::make('filament.expense-scans.images')
                        ->viewData(fn (?ExpenseScan $record): array => ['scan' => $record]),
                ]),
            Section::make('Expense lines')
                ->description('Check every line against the photo. Fix anything the AI misread, fill in missing dates, then press Publish. Nothing is posted to your accounts until you publish.')
                ->columnSpanFull()
                ->schema([
                    Placeholder::make('scan_message')
                        ->hiddenLabel()
                        ->content(fn (?ExpenseScan $record): string => (string) $record?->error_message)
                        ->visible(fn (?ExpenseScan $record): bool => filled($record?->error_message)),
                    Repeater::make('items')
                        ->relationship()
                        ->orderColumn('sort_order')
                        ->hiddenLabel()
                        ->schema(static::lineFields())
                        ->columns(2)
                        ->itemLabel(fn (array $state): string => static::lineLabel($state))
                        ->collapsible()
                        ->defaultItems(0)
                        ->addActionLabel('Add a line'),
                ]),
        ]);
    }

    /** Upload form (create page). */
    public static function upload(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('Your expenses'))
                ->description(__('Upload a clear photo of a handwritten note, receipt, or any expense list, and/or paste expenses as text (Bangla or English). The AI reads them into draft lines for you to review. Nothing is posted until you publish.'))
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('image_paths')
                        ->label('Photos')
                        ->multiple()
                        ->maxFiles(ExpenseScan::MAX_IMAGES)
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->disk(fn (): string => app(CompanyStorageService::class)->privateDiskName())
                        ->visibility('private')
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => static::storeImage($file))
                        ->helperText('Up to '.ExpenseScan::MAX_IMAGES.' photos (JPG, PNG or WebP, 10 MB each). Pages of the same note can go in one scan.')
                        ->required(fn (Get $get): bool => blank($get('source_text')))
                        ->validationMessages(['required' => __('Upload a photo or paste your expenses as text.')]),
                    Textarea::make('source_text')
                        ->label(__('Or paste expenses as text'))
                        ->rows(8)
                        ->maxLength(ExpenseScan::MAX_TEXT_LENGTH)
                        ->placeholder(__("20/09/2026\nRickshaw fare 120\nTea and snacks 80 (bKash)\n21/09 Office rent 15000"))
                        ->helperText(__('Paste several expenses at once: one per line, in any format. Dates, amounts and payment methods are read the same way as from a photo.'))
                        ->required(fn (Get $get): bool => blank($get('image_paths')))
                        ->validationMessages(['required' => __('Upload a photo or paste your expenses as text.')]),
                    Select::make('default_account_id')
                        ->label('Pay from account (when the note does not say)')
                        ->options(fn (): array => static::accountOptions())
                        ->searchable()
                        ->helperText('Optional. Used for every line unless the note names a payment method (e.g. bKash, Cash). You can change it per line during review.'),
                ]),
        ]);
    }

    /** @return array<int, mixed> */
    protected static function lineFields(): array
    {
        return [
            DatePicker::make('expense_date')
                ->label('Date')
                ->helperText(fn (Get $get): ?string => blank($get('expense_date')) ? 'No date found on the note — please set one.' : null),
            TextInput::make('amount')
                ->label('Amount')
                ->numeric()
                ->prefix('৳')
                ->minValue(0.01)
                ->live(onBlur: true),
            TextInput::make('description')
                ->label('Description')
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('expense_category_id')
                ->label('Category')
                ->options(fn (): array => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->live(),
            TextInput::make('new_category_name')
                ->label('New category')
                ->maxLength(100)
                ->helperText('Suggested by the AI — created when you publish. Pick an existing category instead to skip it.')
                ->visible(fn (Get $get): bool => blank($get('expense_category_id'))),
            Select::make('account_id')
                ->label('Pay from account')
                ->options(fn (): array => static::accountOptions())
                ->searchable(),
            TextInput::make('reference')
                ->label('Reference')
                ->maxLength(255),
            Hidden::make('confidence')->dehydrated(false),
            Placeholder::make('review_warnings')
                ->hiddenLabel()
                ->columnSpanFull()
                ->content(fn (Get $get, ?ExpenseScanItem $record): HtmlString => static::warnings($get, $record?->expense_scan_id))
                ->visible(fn (Get $get, ?ExpenseScanItem $record): bool => static::warnings($get, $record?->expense_scan_id)->toHtml() !== ''),
        ];
    }

    /** @return array<int|string, string> */
    public static function accountOptions(): array
    {
        return Account::query()->manual()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @param  array<string, mixed>  $state */
    public static function lineLabel(array $state): string
    {
        $amount = is_numeric($state['amount'] ?? null) ? '৳'.number_format((float) $state['amount'], 2) : '৳ ?';
        $description = Str::limit((string) ($state['description'] ?? ''), 50);

        return trim($amount.' — '.($description !== '' ? $description : 'Expense line'));
    }

    protected static function warnings(Get $get, ?int $scanId): HtmlString
    {
        $messages = [];
        $confidence = $get('confidence');

        if (is_numeric($confidence) && (float) $confidence < 0.6) {
            $messages[] = 'The AI was not sure about this line — check the amount and date against the photo.';
        }

        $duplicate = app(ExpenseScanPublisher::class)->possibleDuplicate(
            $get('expense_date') ? (string) $get('expense_date') : null,
            $get('amount'),
            $scanId,
        );

        if ($duplicate) {
            $messages[] = 'Possible duplicate: '.$duplicate->expense_number.' is already recorded for the same date and amount.';
        }

        if ($messages === []) {
            return new HtmlString('');
        }

        return new HtmlString(
            '<div class="text-sm font-medium text-warning-600 dark:text-warning-400">'
            .collect($messages)->map(fn (string $message): string => '⚠ '.e($message))->implode('<br>')
            .'</div>'
        );
    }

    protected static function storeImage(TemporaryUploadedFile $file): string
    {
        $company = app(CompanyContext::class)->company();

        if (! $company) {
            throw ValidationException::withMessages([
                'image_paths' => 'Select a company before uploading an expense photo.',
            ]);
        }

        $extension = Str::of($file->guessExtension() ?: $file->getClientOriginalExtension())
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->limit(12, '')
            ->value();
        $stream = $file->readStream();

        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'image_paths' => 'The photo could not be read. Please upload it again.',
            ]);
        }

        try {
            return app(CompanyStorageService::class)->putPrivate(
                $company,
                'expense-scans',
                (string) Str::ulid().($extension !== '' ? ".{$extension}" : ''),
                $stream,
            );
        } finally {
            fclose($stream);
        }
    }
}
