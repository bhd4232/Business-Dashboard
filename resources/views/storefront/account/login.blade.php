@extends('storefront.layout')

@php
    $loginStoreUrl = route('storefront.account.login.store');
    $registerStoreUrl = route('storefront.account.register.store');
    $forgotUrl = route('storefront.account.forgot-password');
@endphp

@section('content')
    <section class="mx-auto w-full max-w-md px-4 py-12 sm:px-6 lg:px-8">
        <div
            x-data="{ tab: '{{ $activeTab }}' }"
            x-init="$nextTick(() => (tab === 'login' ? $refs.loginFirst : $refs.registerFirst)?.focus())"
        >
            <div class="grid grid-cols-2 gap-2 rounded-xl border border-gray-200 bg-gray-50 p-1 dark:border-white/10 dark:bg-white/5" role="group" aria-label="Log in or create an account">
                <button
                    type="button"
                    :aria-pressed="(tab === 'login').toString()"
                    @click="tab = 'login'; $nextTick(() => $refs.loginFirst?.focus())"
                    class="rounded-lg py-2.5 text-sm font-medium transition"
                    :class="tab === 'login' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                >
                    Log in
                </button>
                <button
                    type="button"
                    :aria-pressed="(tab === 'register').toString()"
                    @click="tab = 'register'; $nextTick(() => $refs.registerFirst?.focus())"
                    class="rounded-lg py-2.5 text-sm font-medium transition"
                    :class="tab === 'register' ? 'bg-white text-gray-950 shadow-sm dark:bg-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                >
                    Create account
                </button>
            </div>

            <div x-show="tab === 'login'" x-cloak class="mt-6">
                <h1 class="text-2xl font-semibold tracking-tight">Welcome back</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Log in with your phone number or email.</p>

                <form class="mt-6 space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ $loginStoreUrl }}">
                    @csrf
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="identifier">Phone number or email</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="identifier" name="identifier" type="text" autocomplete="username" value="{{ old('identifier') }}" required x-ref="loginFirst">
                        @error('identifier') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-medium text-gray-500" for="password">Password</label>
                            <a class="text-xs font-medium text-[var(--storefront-brand)]" href="{{ $forgotUrl }}">Forgot password?</a>
                        </div>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input class="rounded border-gray-300 text-[var(--storefront-brand)] focus:ring-[var(--storefront-brand)]" type="checkbox" name="remember" value="1">
                        Keep me logged in
                    </label>
                    <button class="w-full rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90 [touch-action:manipulation]" type="submit">
                        Log in
                    </button>
                </form>
            </div>

            <div x-show="tab === 'register'" x-cloak class="mt-6">
                <h1 class="text-2xl font-semibold tracking-tight">Create your account</h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">See your order history and check out faster next time.</p>

                <form class="mt-6 space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" method="POST" action="{{ $registerStoreUrl }}">
                    @csrf
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="name">Your name</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" required x-ref="registerFirst">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="phone">Phone number</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" value="{{ old('phone') }}" required>
                        @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="email">Email (optional)</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="email" name="email" type="email" autocomplete="email" spellcheck="false" value="{{ old('email') }}">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="address">Delivery address (optional)</label>
                        <textarea class="mt-2 w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="address" name="address" rows="2" autocomplete="street-address">{{ old('address') }}</textarea>
                        @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="reg_password">Password</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="reg_password" name="password" type="password" autocomplete="new-password" minlength="8" required aria-describedby="reg_password_hint">
                        <p class="mt-1 text-xs text-gray-400" id="reg_password_hint">At least 8 characters.</p>
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500" for="password_confirmation">Confirm password</label>
                        <input class="mt-2 min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 text-sm outline-none transition focus:border-[var(--storefront-brand)] focus:ring-1 focus:ring-[var(--storefront-brand)] dark:border-white/10 dark:bg-gray-950" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                    </div>
                    <button class="w-full rounded-lg bg-[var(--storefront-brand)] px-6 py-3 text-sm font-medium text-white transition hover:opacity-90 [touch-action:manipulation]" type="submit">
                        Create account
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
