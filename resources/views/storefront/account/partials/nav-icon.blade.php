@switch($icon)
    @case('home')
        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3 10.5 9-7.5 9 7.5M5.25 9v10.5A1.5 1.5 0 0 0 6.75 21h10.5a1.5 1.5 0 0 0 1.5-1.5V9M9 21v-6.75A2.25 2.25 0 0 1 11.25 12h1.5A2.25 2.25 0 0 1 15 14.25V21"/></svg>
        @break
    @case('user')
        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.1a7.5 7.5 0 0 1 15 0A17.9 17.9 0 0 1 12 21.75a17.9 17.9 0 0 1-7.5-1.65Z"/></svg>
        @break
    @case('bag')
        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5h10.5l.75 13.5H6L6.75 7.5ZM9 10.5V6a3 3 0 0 1 6 0v4.5"/></svg>
        @break
    @case('store')
        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.75 4.5 4.5h15l.75 5.25M3.75 9.75a2.25 2.25 0 0 0 4.5 0 2.25 2.25 0 0 0 4.5 0 2.25 2.25 0 0 0 4.5 0 2.25 2.25 0 0 0 4.5 0M4.5 9.75V19.5a1.5 1.5 0 0 0 1.5 1.5h4.5v-6h3v6H18a1.5 1.5 0 0 0 1.5-1.5V9.75"/></svg>
        @break
    @default
        <svg class="h-5 w-5 shrink-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h3l2.25-6 4.5 12 2.25-6h6"/></svg>
@endswitch
