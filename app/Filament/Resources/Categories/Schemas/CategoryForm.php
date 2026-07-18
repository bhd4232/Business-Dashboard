<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Filament\Concerns\OptimizesUploadedImages;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    use OptimizesUploadedImages;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('Category image')
                    ->helperText('Shown on the storefront category card. Recommended: square, at least 400x400px. Automatically compressed to WebP on upload.')
                    ->image()
                    ->maxSize(1024)
                    ->disk('public')
                    ->directory('categories')
                    ->imageEditor()
                    ->saveUploadedFileUsing(static::optimizeCompactImageUpload())
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
