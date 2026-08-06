<?php

namespace App\Filament\Forms\Components;

use App\Support\StorefrontCategoryIcons;
use Filament\Forms\Components\Field;

class CategoryIconPicker extends Field
{
    protected string $view = 'filament.forms.components.category-icon-picker';

    public function getIcons(): array
    {
        return StorefrontCategoryIcons::catalog();
    }
}
