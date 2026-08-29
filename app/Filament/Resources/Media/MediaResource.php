<?php

namespace App\Filament\Resources\Media;

use App\Filament\Clusters\Settings;
use App\Filament\Concerns\OptimizesUploadedImages;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Services\CompanyContext;
use App\Support\CompanyMedia;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Media Hub: every image a company has uploaded, in one place — used
 * directly for add/delete, and browsed from any image field's "Select From
 * Media" action (see App\Filament\Concerns\SelectableFromMediaHub).
 *
 * Images-only for now, matching the owner's scoping decision. Deliberately
 * has no create/edit form — uploading is a header action (any number of
 * files at once) and deleting is a row/bulk action; there is nothing else
 * on a Media row worth a full edit page yet.
 */
class MediaResource extends Resource
{
    use OptimizesUploadedImages;

    protected static ?string $model = Media::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Media Hub';

    protected static ?string $modelLabel = 'image';

    protected static ?string $pluralModelLabel = 'Media Hub';

    protected static ?string $recordTitleAttribute = 'original_filename';

    public static function table(Table $table): Table
    {
        return $table
            ->contentGrid(['sm' => 2, 'md' => 3, 'lg' => 4, 'xl' => 6])
            ->columns([
                ImageColumn::make('path')
                    ->label('')
                    ->state(fn (Media $record): ?string => CompanyMedia::filamentPublicUrl($record->path, $record))
                    ->size(160)
                    ->extraImgAttributes(['loading' => 'lazy', 'class' => 'object-cover']),
                TextColumn::make('original_filename')
                    ->label('File')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn (Media $record): ?string => $record->original_filename),
                TextColumn::make('size')
                    ->label('Size')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 1).' KB'),
                TextColumn::make('company.name')
                    ->label('Company')
                    ->visible(fn (): bool => app(CompanyContext::class)->isAllCompanies()),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('This permanently deletes the image file. It will no longer display anywhere it is already in use.')
                    ->using(function (Media $record): void {
                        $record->deleteFile();
                        $record->delete();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription('This permanently deletes the image files. They will no longer display anywhere they are already in use.')
                        ->using(function (Collection $records): void {
                            $records->each(function (Media $record): void {
                                $record->deleteFile();
                                $record->delete();
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
        ];
    }

    public static function uploadAction(): Action
    {
        return Action::make('upload')
            ->label('Upload Images')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Upload Images')
            ->modalSubmitActionLabel('Upload')
            ->disabled(fn (): bool => ! app(CompanyContext::class)->hasCompany())
            ->schema([
                FileUpload::make('files')
                    ->label('Images')
                    ->image()
                    ->multiple()
                    ->reorderable(false)
                    ->maxFiles(20)
                    ->maxSize(2048)
                    ->tap(static::browserImagePrecompression())
                    ->disk(fn (): string => CompanyMedia::publicDiskName())
                    ->directory(fn (): string => CompanyMedia::publicDirectory('media-hub'))
                    ->saveUploadedFileUsing(static::optimizeImageUpload())
                    ->required()
                    ->helperText('Automatically compressed to WebP. Each of these becomes visible on any image field via "Select From Media".'),
            ])
            ->action(function (array $data): void {
                Notification::make()
                    ->title(count($data['files'] ?? []).' image(s) added to the Media Hub')
                    ->success()
                    ->send();
            });
    }
}
