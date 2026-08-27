@php
    $platform = $platform ?? null;
    $iconClass = $iconClass ?? 'h-4 w-4';
@endphp

@switch($platform)
    @case('facebook')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.5h2.5l.5-3h-3V8.25c0-.87.24-1.46 1.49-1.46H16.5V4.14C16.24 4.1 15.36 4 14.33 4c-2.15 0-3.63 1.31-3.63 3.72V10.5H8.2v3h2.5V21h2.8Z"/></svg>
        @break
    @case('instagram')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="17" height="17" rx="4.5"/><circle cx="12" cy="12" r="3.7"/><circle cx="17" cy="7" r="0.9" fill="currentColor" stroke="none"/></svg>
        @break
    @case('youtube')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.7a2.7 2.7 0 0 0-1.9-1.9C18 5.3 12 5.3 12 5.3s-6 0-7.7.5a2.7 2.7 0 0 0-1.9 1.9C2 9.4 2 12 2 12s0 2.6.4 4.3a2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.5a2.7 2.7 0 0 0 1.9-1.9c.4-1.7.4-4.3.4-4.3s0-2.6-.4-4.3ZM10 15.2V8.8L15.6 12 10 15.2Z"/></svg>
        @break
    @case('tiktok')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M16.6 3h-2.9v12.2a2.6 2.6 0 1 1-1.9-2.5V9.7a5.6 5.6 0 1 0 4.8 5.5V9.3a7.3 7.3 0 0 0 4.4 1.5V7.9a4.4 4.4 0 0 1-4.4-4.4V3Z"/></svg>
        @break
    @case('x')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="m3 3 7.4 9.6L3.2 21h2.3l6.3-7.1L17 21h4l-7.7-10 6.8-8h-2.3l-5.8 6.5L7 3H3Z"/></svg>
        @break
    @case('linkedin')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6.94 8.5H4V20h2.94V8.5ZM5.47 4a1.7 1.7 0 1 0 0 3.4 1.7 1.7 0 0 0 0-3.4ZM20 20v-6.3c0-3.37-1.8-4.94-4.2-4.94a3.63 3.63 0 0 0-3.29 1.8h-.05V8.5H9.7c.04.9 0 11.5 0 11.5h2.76v-6.42c0-.34.02-.68.12-.92.28-.68.9-1.38 1.94-1.38 1.37 0 1.92 1.04 1.92 2.57V20H20Z"/></svg>
        @break
    @case('whatsapp')
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2Zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8a11.3 11.3 0 0 1-4.6-4.1c-.7-1-1.2-2.2-1.2-2.5s0-.6.5-1.1c.2-.2.5-.3.8-.3h.5c.2 0 .4 0 .6.4.2.5.7 1.7.7 1.8.1.1.1.3 0 .5-.2.3-.3.4-.5.6-.2.2-.4.4-.2.8.3.5 1 1.4 2 2.2 1.1.9 1.9 1.2 2.3 1.4.3.2.5.1.6-.1.2-.2.7-.8.9-1.1.2-.3.4-.2.6-.1l1.7.9c.2.1.4.2.4.4.1.2.1.7-.1 1.2Z"/></svg>
        @break
    @default
        <svg class="{{ $iconClass }}" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M3 12h18M12 3c2.4 2.6 3.6 5.7 3.6 9s-1.2 6.4-3.6 9c-2.4-2.6-3.6-5.7-3.6-9S9.6 5.6 12 3Z"/></svg>
@endswitch
