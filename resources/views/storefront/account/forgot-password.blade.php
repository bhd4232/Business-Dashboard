@extends('storefront.layout')

@php
    $forgotStoreUrl = route('storefront.account.forgot-password.store');
    $loginUrl = route('storefront.account.login');
    $contactUrl = route('storefront.contact');
@endphp

@section('content')
    <section class="mx-auto w-full max-w-md px-4 py-12 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Account recovery</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Forgot your password?</h1>
        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
            Enter the phone number on your account. If it matches, we'll text you a 6-digit reset code.
        </p>

        @unless ($smsAvailable)
            <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-200">
                Password reset by SMS isn't set up for this store yet. Please <a class="font-medium underline" href="{{ $contactUrl }}">contact support</a> to regain access to your account.
            </div>
        @endunless

        <form class="mt-6 space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ $forgotStoreUrl }}">
            @csrf
            <div>
                <label class="text-xs font-medium text-gray-500" for="phone">Phone number</label>
                <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required>
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <button class="w-full rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90 [touch-action:manipulation]" type="submit">
                Send reset code
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Remembered it? <a class="font-medium text-[var(--storefront-brand)]" href="{{ $loginUrl }}">Log in</a>
        </p>
    </section>
@endsection
