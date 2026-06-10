@if($resource !== 'site_settings')
    @include('tyro-dashboard::resources.edit-original')
@else
@extends('tyro-dashboard::layouts.app')

@section('title', 'Website Settings & SEO')

@section('breadcrumb')
<a href="{{ route($dashboardRoute::name('index')) }}">Dashboard</a>
<span class="breadcrumb-separator">/</span>
<a href="{{ route($dashboardRoute::name('resources.index'), $resource) }}">{{ $config['title'] }}</a>
<span class="breadcrumb-separator">/</span>
<span>Edit</span>
@endsection

@push('styles')
    @include('settings.partials._styles')
    <style>
        .website-settings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .website-settings-grid .span-2 { grid-column: span 2; }
        .website-field {
            display: grid;
            gap: 0.45rem;
        }
        .website-field label {
            color: var(--foreground);
            font-size: 0.875rem;
            font-weight: 700;
        }
        .website-field .form-input,
        .website-field .form-select,
        .website-field textarea {
            width: 100%;
        }
        .website-field textarea {
            min-height: 108px;
            resize: vertical;
        }
        .website-media-preview {
            width: min(220px, 100%);
            min-height: 96px;
            display: grid;
            place-items: center;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            background: var(--card);
        }
        .website-media-preview img {
            max-width: 100%;
            max-height: 120px;
            object-fit: contain;
        }
        .website-file-current {
            color: var(--muted-foreground);
            font-size: 0.8125rem;
            line-height: 1.45;
        }
        .website-file-current a {
            color: var(--primary);
            overflow-wrap: anywhere;
        }
        .website-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 0.875rem;
            background: var(--card);
        }
        .website-switch-copy {
            min-width: 0;
        }
        .website-switch-copy strong {
            display: block;
            color: var(--foreground);
            font-size: 0.925rem;
        }
        .website-switch-copy span {
            display: block;
            margin-top: 0.25rem;
            color: var(--muted-foreground);
            font-size: 0.825rem;
            line-height: 1.45;
        }
        .website-toggle {
            width: 52px;
            height: 28px;
            appearance: none;
            border-radius: 999px;
            background: var(--border);
            position: relative;
            cursor: pointer;
            flex: 0 0 auto;
            transition: background 0.2s ease;
        }
        .website-toggle::after {
            content: "";
            position: absolute;
            width: 22px;
            height: 22px;
            top: 3px;
            left: 3px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 1px 4px rgba(15, 23, 42, 0.22);
            transition: transform 0.2s ease;
        }
        .website-toggle:checked {
            background: var(--primary);
        }
        .website-toggle:checked::after {
            transform: translateX(24px);
        }
        @media (max-width: 900px) {
            .website-settings-grid { grid-template-columns: 1fr; }
            .website-settings-grid .span-2 { grid-column: auto; }
        }
    </style>
@endpush

@php
    $tabs = [
        'brand' => [
            'label' => 'Brand',
            'title' => 'Brand identity',
            'description' => 'Manage the public site name, tagline, logo, and favicon.',
            'badge' => 'Brand',
            'fields' => ['site_name', 'tagline', 'logo', 'favicon'],
        ],
        'header' => [
            'label' => 'Header',
            'title' => 'Header logo display',
            'description' => 'Control whether title/tagline appears beside the logo and tune logo dimensions.',
            'badge' => 'Header',
            'fields' => ['header_show_site_name', 'header_show_tagline', 'header_logo_width', 'header_logo_height'],
        ],
        'contact' => [
            'label' => 'Contact',
            'title' => 'Contact and footer',
            'description' => 'Update phone, email, address, social links, and footer copy.',
            'badge' => 'Contact',
            'fields' => ['phone', 'email', 'address', 'footer_text', 'facebook_url', 'whatsapp_url'],
        ],
        'seo' => [
            'label' => 'SEO',
            'title' => 'SEO and social sharing',
            'description' => 'Set default meta content and the global Open Graph sharing image.',
            'badge' => 'SEO',
            'fields' => ['seo_title', 'seo_description', 'og_image'],
        ],
        'publish' => [
            'label' => 'Publish',
            'title' => 'Publishing state',
            'description' => 'Enable or disable this website settings record.',
            'badge' => 'Status',
            'fields' => ['is_active'],
        ],
    ];

    $icons = [
        'brand' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 7h16M4 12h10M4 17h7"/><path d="M17 14l2 2 4-4"/></svg>',
        'header' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18"/></svg>',
        'contact' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.8 19.8 0 0 1 3.08 5.18 2 2 0 0 1 5 3h3a2 2 0 0 1 2 1.72c.12.9.33 1.77.63 2.6a2 2 0 0 1-.45 2.11L9 10.6a16 16 0 0 0 4.4 4.4l1.17-1.17a2 2 0 0 1 2.11-.45c.83.3 1.7.51 2.6.63A2 2 0 0 1 22 16.92z"/></svg>',
        'seo' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>',
        'publish' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 2l7 4v6c0 5-3.5 9.5-7 10-3.5-.5-7-5-7-10V6l7-4z"/><path d="M9 12l2 2 4-4"/></svg>',
    ];

    $wideFields = ['address', 'footer_text', 'seo_description'];
@endphp

@section('content')
<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1 class="page-title">Website Settings & SEO</h1>
            <p class="page-description">Manage public website branding, header behavior, contact details, and search/social metadata.</p>
        </div>
        <div>
            <button type="submit" form="websiteSettingsForm" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                Save Settings
            </button>
        </div>
    </div>
</div>

<form id="websiteSettingsForm" action="{{ route($dashboardRoute::name('resources.update'), [$resource, $item->id]) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="vtabs-layout">
        <nav class="vtabs-sidebar">
            @foreach($tabs as $tabKey => $tab)
                <button class="vtabs-item {{ $loop->first ? 'active' : '' }}" data-vtab="{{ $tabKey }}" type="button">
                    {!! $icons[$tabKey] !!}
                    {{ $tab['label'] }}
                </button>
            @endforeach
            <div class="vtabs-save-bar">
                <button type="submit" form="websiteSettingsForm" class="btn btn-primary btn-sm" style="width:100%;">
                    Save Settings
                </button>
            </div>
        </nav>

        <div class="vtabs-content">
            @foreach($tabs as $tabKey => $tab)
                <section class="vtab-panel {{ $loop->first ? 'active' : '' }}" data-vtab-panel="{{ $tabKey }}">
                    <div class="sys-settings-section-intro">
                        <div class="sys-settings-section-copy">
                            <h2 class="sys-settings-section-heading">{{ $tab['title'] }}</h2>
                            <p class="sys-settings-section-description">{{ $tab['description'] }}</p>
                        </div>
                        <span class="sys-settings-section-badge">{{ $tab['badge'] }}</span>
                    </div>

                    <div class="sys-settings-surface">
                        <div class="website-settings-grid">
                            @foreach($tab['fields'] as $key)
                                @php($field = $config['fields'][$key] ?? null)

                                @if(! $field || ($field['hide_in_form'] ?? false) || ($field['hide_in_edit'] ?? false))
                                    @continue
                                @endif

                                @if($field['type'] === 'hidden')
                                    <input type="hidden" name="{{ $key }}" value="{{ old($key, $item->$key) }}">
                                    @continue
                                @endif

                                <div class="website-field {{ in_array($key, $wideFields, true) ? 'span-2' : '' }}">
                                    @if($field['type'] === 'boolean')
                                        <div class="website-switch">
                                            <div class="website-switch-copy">
                                                <strong>{{ $field['label'] }}</strong>
                                                @if(isset($field['help_text']))
                                                    <span>{{ $field['help_text'] }}</span>
                                                @endif
                                            </div>
                                            <input type="hidden" name="{{ $key }}" value="0">
                                            <input class="website-toggle" type="checkbox" name="{{ $key }}" id="{{ $key }}" value="1" {{ old($key, $item->$key) ? 'checked' : '' }}>
                                        </div>
                                    @else
                                        <label for="{{ $key }}">{{ $field['label'] }}</label>

                                        @if($field['type'] === 'textarea')
                                            <textarea name="{{ $key }}" id="{{ $key }}" class="form-input @error($key) is-invalid @enderror" rows="5" placeholder="{{ $field['placeholder'] ?? '' }}">{{ old($key, $item->$key) }}</textarea>
                                        @elseif($field['type'] === 'file')
                                            @if(!empty($item->$key) && preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $item->$key))
                                                <div class="website-media-preview">
                                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($item->$key) }}" alt="{{ $field['label'] }}">
                                                </div>
                                            @endif
                                            <input type="file" name="{{ $key }}" id="{{ $key }}" class="form-input @error($key) is-invalid @enderror">
                                            @if(!empty($item->$key))
                                                <div class="website-file-current">
                                                    Current file:
                                                    <a href="{{ \Illuminate\Support\Facades\Storage::url($item->$key) }}" target="_blank">{{ basename($item->$key) }}</a>
                                                </div>
                                            @endif
                                        @else
                                            <input type="{{ $field['type'] }}" name="{{ $key }}" id="{{ $key }}" class="form-input @error($key) is-invalid @enderror" value="{{ old($key, $item->$key) }}" placeholder="{{ $field['placeholder'] ?? '' }}">
                                        @endif

                                        @if(isset($field['help_text']))
                                            <div class="form-help-text" style="color: var(--muted-foreground); font-size: 0.875rem; margin-top: 0.1rem;">{{ $field['help_text'] }}</div>
                                        @endif
                                    @endif

                                    @error($key)
                                        @if(config('tyro-dashboard.resource_ui.show_field_errors', true))
                                            <div class="form-error" style="color: var(--danger); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</div>
                                        @endif
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    <div class="sys-settings-save-row">
        <button type="submit" class="btn btn-primary">
            Save Settings
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var buttons = document.querySelectorAll('.vtabs-item[data-vtab]');
        var panels = document.querySelectorAll('.vtab-panel[data-vtab-panel]');

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                var target = button.getAttribute('data-vtab');

                buttons.forEach(function (item) {
                    item.classList.toggle('active', item === button);
                });

                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.getAttribute('data-vtab-panel') === target);
                });
            });
        });
    });
</script>
@endpush
@endif
