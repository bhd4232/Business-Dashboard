<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Support\CompanyMedia;
use App\Support\StorefrontCategoryIcons;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('image')
                    ->label('Category image')
                    ->state(fn (Category $record): ?string => CompanyMedia::filamentPublicUrl($record->image, $record))
                    ->height(120)
                    ->square(),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                IconEntry::make('icon')
                    ->label('Icon fallback')
                    ->icon(fn (?string $state): string => StorefrontCategoryIcons::normalize($state) ?? 'heroicon-o-tag')
                    ->color(fn (?string $state): string => StorefrontCategoryIcons::normalize($state) ? 'primary' : 'gray')
                    ->tooltip(fn (?string $state): string => StorefrontCategoryIcons::label($state) ?? 'Category initial'),
                TextEntry::make('description')
                    ->placeholder('No description')
                    ->columnSpanFull(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
