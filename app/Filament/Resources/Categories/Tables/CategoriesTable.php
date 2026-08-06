<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Models\Category;
use App\Support\CompanyMedia;
use App\Support\StorefrontCategoryIcons;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->state(fn (Category $record): ?string => CompanyMedia::filamentPublicUrl($record->image, $record))
                    ->height(44)
                    ->square(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('icon')
                    ->label('Icon fallback')
                    ->icon(fn (?string $state): string => StorefrontCategoryIcons::normalize($state) ?? 'heroicon-o-tag')
                    ->tooltip(fn (?string $state): string => StorefrontCategoryIcons::label($state) ?? 'Category initial')
                    ->color(fn (?string $state): string => StorefrontCategoryIcons::normalize($state) ? 'primary' : 'gray')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
