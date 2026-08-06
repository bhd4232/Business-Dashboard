@php
    $categoryIcon = \App\Support\StorefrontCategoryIcons::normalize($icon ?? null);
    $iconClass = $iconClass ?? 'h-7 w-7';
@endphp

{!! \Filament\Support\generate_icon_html(
    $categoryIcon ?? \Filament\Support\Icons\Heroicon::OutlinedTag,
    attributes: (new \Illuminate\View\ComponentAttributeBag([
        'data-category-icon' => $categoryIcon,
    ]))->class([$iconClass]),
    size: \Filament\Support\Enums\IconSize::Large,
)?->toHtml() !!}
