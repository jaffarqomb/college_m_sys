@php
    $title ??= null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    <meta name="description" content="{{ $description ?? config('site.tagline') }}">
</head>

<body class="min-h-screen flex flex-col bg-white font-sans text-zinc-800 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="sticky top-0 z-40 border-b border-zinc-200/70 bg-white/85 backdrop-blur-md dark:border-zinc-800/70 dark:bg-zinc-950/85">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-600/30">
                    <flux:icon.academic-cap class="size-5" />
                </span>
                <span class="hidden flex-col leading-tight sm:flex">
                    <span class="text-sm font-bold tracking-tight text-zinc-900 dark:text-white">Automated College</span>
                    <span class="text-[10px] font-medium uppercase tracking-widest text-indigo-600 dark:text-indigo-400">System</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 md:flex">
                <flux:navbar>
                    <flux:navbar.item href="{{ route('home') }}">Home</flux:navbar.item>
                    <flux:navbar.item href="{{ route('about') }}">About</flux:navbar.item>
                    <flux:navbar.item href="{{ route('contact') }}">Contact</flux:navbar.item>
                </flux:navbar>
            </nav>

            <div class="flex items-center gap-2">
                @auth
                    <flux:button href="{{ route('dashboard') }}" size="sm" variant="primary">My Portal</flux:button>
                @endauth
                @guest
                    <flux:button href="{{ route('login') }}" size="sm" variant="ghost">Log in</flux:button>
                    <flux:button href="{{ route('register') }}" size="sm" variant="primary">Get started</flux:button>
                @endguest
            </div>
        </div>

        <div class="md:hidden">
            <nav class="flex items-center gap-1 overflow-x-auto px-4 py-2 sm:px-6">
                <a href="{{ route('home') }}" class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800">Home</a>
                <a href="{{ route('about') }}" class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800">About</a>
                <a href="{{ route('contact') }}" class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800">Contact</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-4">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2.5">
                    <span class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white">
                        <flux:icon.academic-cap class="size-5" />
                    </span>
                    <span class="text-base font-bold text-zinc-900 dark:text-white">Automated College System</span>
                </div>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    {{ config('site.tagline') }} — enroll in courses, follow your AI-generated timetable, track grades and attendance, and manage tuition in one automated platform.
                </p>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Explore</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-zinc-600 dark:text-zinc-400">
                    <li><a href="{{ route('home') }}" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">Home</a></li>
                    <li><a href="{{ route('about') }}" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">About</a></li>
                    <li><a href="{{ route('contact') }}" class="transition hover:text-indigo-600 dark:hover:text-indigo-400">Contact</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Contact</h3>
                <ul class="mt-4 space-y-2.5 text-sm text-zinc-600 dark:text-zinc-400">
                    <li>{{ config('site.contact.email') }}</li>
                    <li>{{ config('site.contact.phone') }}</li>
                    <li>{{ config('site.contact.address') }}</li>
                    <li>{{ config('site.contact.hours') }}</li>
                </ul>
            </div>
        </div>

        <div class="border-t border-zinc-200 py-6 dark:border-zinc-800">
            <p class="mx-auto max-w-6xl px-4 text-center text-xs text-zinc-500 sm:px-6 dark:text-zinc-500">
                &copy; {{ now()->year }} Automated College System. All rights reserved.
            </p>
        </div>
    </footer>

    <livewire:site-chatbot />
</body>
</html>
