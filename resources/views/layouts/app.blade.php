<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-[var(--background)] text-[var(--text)]">
        <div class="min-h-screen bg-[var(--background)]">
            <aside class="fixed left-0 top-0 h-screen w-[250px] border-r border-[var(--border)] bg-[var(--card)] px-4 py-6 shadow-sm">
                <div class="mb-8 flex items-center gap-3 px-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--primary-light)] text-[var(--primary)] font-semibold">H</div>
                    <div>
                        <p class="text-sm font-semibold text-[var(--text)]">HIMS</p>
                        <p class="text-xs text-[var(--muted)]">Supply Chain</p>
                    </div>
                </div>

                <nav class="space-y-1">
                    <a href="{{ route('dashboard') }}" class="menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-[var(--muted)] transition hover:bg-[var(--primary)] hover:text-white">
                        <span>◉</span>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('inventory') }}" class="menu-link {{ request()->routeIs('inventory*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-[var(--muted)] transition hover:bg-[var(--primary)] hover:text-white">
                        <span>◉</span>
                        <span>Inventory</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="menu-link {{ request()->routeIs('profile*') ? 'active' : '' }} flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-medium text-[var(--muted)] transition hover:bg-[var(--primary)] hover:text-white">
                        <span>◉</span>
                        <span>Profile</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-3 text-left text-sm font-medium text-[var(--muted)] transition hover:bg-[var(--primary)] hover:text-white">
                            <span>◉</span>
                            <span>Logout</span>
                        </button>
                    </form>
                </nav>
            </aside>

            <div class="ml-[250px] min-h-screen bg-[var(--background)] p-6 lg:p-8">
                <header class="mb-6 flex flex-col gap-4 rounded-2xl border border-[var(--border)] bg-[var(--card)] px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase tracking-[0.3em] text-[var(--primary)]">Operations</p>
                        <h2 class="text-xl font-semibold text-[var(--text)]">Supply Chain & Inventory Hub</h2>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="text" placeholder="Search" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-4 py-2 text-sm outline-none sm:w-72" />
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--primary-light)] font-semibold text-[var(--primary)]">
                            {{ Str::upper(Str::substr(Auth::user()->name, 0, 1)) }}
                        </div>
                    </div>
                </header>

                <!-- Page Heading -->
                @isset($header)
                    <header class="mb-6 rounded-2xl border border-[var(--border)] bg-[var(--card)] px-5 py-4 shadow-sm">
                        <div class="max-w-7xl">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
