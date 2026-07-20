@extends('storefront.layout')

@php
    $resetStoreUrl = route('storefront.account.reset-password.store');
    $forgotUrl = route('storefront.account.forgot-password');
@endphp

@section('content')
    <section class="mx-auto w-full max-w-md px-4 py-12 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-wider text-[var(--storefront-brand)]">Account recovery</p>
        <h1 class="mt-3 text-2xl font-semibold tracking-tight sm:text-3xl">Enter your reset code</h1>
        <p class="mt-3 text-sm leading-6 text-gray-500 dark:text-gray-400">
            Enter the 6-digit code we texted you and choose a new password. The code expires in 15 minutes.
        </p>

        <form class="mt-6 space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ $resetStoreUrl }}">
            @csrf
            <div>
                <label class="text-xs font-medium text-gray-500" for="phone">Phone number</label>
                <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone', $phone) }}" required>
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500" for="code">Reset code</label>
                <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm tracking-widest outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" value="{{ old('code') }}" required>
                @error('code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500" for="password">New password</label>
                <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="password_hint">
                <p class="mt-1 text-xs text-gray-400" id="password_hint">At least 8 characters.</p>
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-medium text-gray-500" for="password_confirmation">Confirm new password</label>
                <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button class="w-full rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90 [touch-action:manipulation]" type="submit">
                Reset password
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Didn't get a code? <a class="font-medium text-[var(--storefront-brand)]" href="{{ $forgotUrl }}">Request another</a>
        </p>
    </section>
@endsection
