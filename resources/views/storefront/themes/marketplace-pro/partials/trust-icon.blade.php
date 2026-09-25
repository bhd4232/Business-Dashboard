@switch($icon ?? 'shield')
    @case('truck')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 6h11v11H3zM14 10h4l3 3v4h-7zM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>
        @break
    @case('refresh')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20 7v5h-5M4 17v-5h5M6.1 9A7 7 0 0 1 18 6l2 2M17.9 15A7 7 0 0 1 6 18l-2-2"/></svg>
        @break
    @case('briefcase')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 7V5h6v2M4 7h16v12H4zM4 12h16M10 12v2h4v-2"/></svg>
        @break
    @case('lock')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 10h12v10H6zM8 10V7a4 4 0 0 1 8 0v3"/></svg>
        @break
    @case('phone')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/></svg>
        @break
    @default
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM9 12l2 2 4-4"/></svg>
@endswitch
