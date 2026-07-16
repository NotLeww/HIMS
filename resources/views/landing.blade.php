<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>HIMS | Supply Chain & Inventory Management</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-100">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 z-0">
                <img src="{{ asset('img/landingpage.jpg') }}" alt="Hospital background" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(2,6,23,0.95)_0%,rgba(2,6,23,0.7)_45%,rgba(15,23,42,0.85)_100%)]"></div>
            </div>

            <div class="relative z-10 mx-auto flex min-h-screen max-w-7xl flex-col px-4 py-4 sm:px-6 lg:px-8">
                <header class="flex items-center justify-between rounded-full border border-white/10 bg-white/10 px-4 py-3 shadow-[0_10px_40px_rgba(0,0,0,0.25)] backdrop-blur-xl sm:px-6">
                    <a href="{{ url('/') }}" class="flex items-center gap-3 text-lg font-semibold tracking-wide text-white">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30">H</span>
                        <span>HIMS</span>
                    </a>
                    <nav class="flex items-center gap-2 text-sm text-slate-300 sm:gap-3">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="rounded-full border border-cyan-400/40 bg-cyan-500/10 px-4 py-2 font-medium text-cyan-300 transition hover:bg-cyan-500/20">
                                    Dashboard
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-full px-4 py-2 transition hover:bg-white/10 hover:text-white">
                                    Log in
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-full bg-white px-4 py-2 font-medium text-slate-900 transition hover:bg-slate-200">
                                        Request access
                                    </a>
                                @endif
                            @endauth
                        @endif
                    </nav>
                </header>

                <main class="flex-1 py-10 sm:py-14 lg:py-20">
                    <section class="grid items-center gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                        <div>
                            <div class="mb-6 inline-flex items-center rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-sm font-medium text-cyan-300">
                                Intelligent operations for modern teams
                            </div>
                            <h1 class="max-w-3xl text-4xl font-semibold leading-tight text-white sm:text-5xl lg:text-6xl">
                                Supply Chain & Inventory Management
                            </h1>
                            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                                Track suppliers, monitor stock movement, automate replenishment, and keep every department aligned with real-time visibility.
                            </p>
                            <div class="mt-8 flex flex-wrap gap-4">
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="rounded-full bg-cyan-500 px-6 py-3 font-semibold text-slate-950 transition hover:bg-cyan-400">
                                        Open the dashboard
                                    </a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-full border border-white/15 bg-white/10 px-6 py-3 font-semibold text-white transition hover:bg-white/20">
                                        Request access
                                    </a>
                                @endif
                            </div>

                            <div class="mt-10 grid gap-4 sm:grid-cols-3">
                                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 shadow-lg shadow-black/20 backdrop-blur">
                                    <p class="text-2xl font-semibold text-white">24/7</p>
                                    <p class="mt-1 text-sm text-slate-300">Live stock visibility</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 shadow-lg shadow-black/20 backdrop-blur">
                                    <p class="text-2xl font-semibold text-white">99.2%</p>
                                    <p class="mt-1 text-sm text-slate-300">Order accuracy</p>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-white/10 p-4 shadow-lg shadow-black/20 backdrop-blur">
                                    <p class="text-2xl font-semibold text-white">3x</p>
                                    <p class="mt-1 text-sm text-slate-300">Faster approvals</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[28px] border border-white/10 bg-slate-900/70 p-6 shadow-[0_25px_80px_rgba(0,0,0,0.45)] backdrop-blur-xl">
                            <div class="flex items-center justify-between rounded-2xl border border-white/10 bg-slate-950/70 px-4 py-3">
                                <div>
                                    <p class="text-sm text-slate-400">Warehouse health</p>
                                    <p class="text-xl font-semibold text-white">Stock aligned</p>
                                </div>
                                <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-sm font-medium text-emerald-300">Stable</span>
                            </div>

                            <div class="mt-6 space-y-4">
                                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-4">
                                    <div class="flex items-center justify-between">
                                        <p class="font-medium text-white">Procurement requests</p>
                                        <span class="text-sm text-cyan-300">12 pending</span>
                                    </div>
                                    <div class="mt-3 h-2 rounded-full bg-slate-800">
                                        <div class="h-2 w-3/4 rounded-full bg-cyan-500"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-4">
                                    <div class="flex items-center justify-between">
                                        <p class="font-medium text-white">Low stock alerts</p>
                                        <span class="text-sm text-amber-300">4 urgent</span>
                                    </div>
                                    <div class="mt-3 h-2 rounded-full bg-slate-800">
                                        <div class="h-2 w-2/3 rounded-full bg-amber-500"></div>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-white/10 bg-slate-900/80 p-4">
                                    <div class="flex items-center justify-between">
                                        <p class="font-medium text-white">Supplier performance</p>
                                        <span class="text-sm text-emerald-300">+14%</span>
                                    </div>
                                    <div class="mt-3 h-2 rounded-full bg-slate-800">
                                        <div class="h-2 w-4/5 rounded-full bg-emerald-500"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mt-16 grid gap-6 md:grid-cols-3">
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 shadow-lg shadow-black/20 backdrop-blur">
                            <h2 class="text-xl font-semibold text-white">Procurement control</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-300">Create purchase requests, review supplier quotes, and keep approvals moving without bottlenecks.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 shadow-lg shadow-black/20 backdrop-blur">
                            <h2 class="text-xl font-semibold text-white">Inventory visibility</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-300">Monitor stock across locations, identify low units, and manage movement with accurate updates.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/10 p-6 shadow-lg shadow-black/20 backdrop-blur">
                            <h2 class="text-xl font-semibold text-white">Operations reporting</h2>
                            <p class="mt-3 text-sm leading-7 text-slate-300">Turn warehouse activity into concise dashboards for planning, audits, and executive review.</p>
                        </div>
                    </section>
                </main>
            </div>
        </div>
    </body>
</html>
