<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Taufani Split') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles

        <style>
            body {
                font-family: 'Inter', sans-serif;
            }
            h1, h2, h3, h4, .font-display {
                font-family: 'Outfit', sans-serif;
            }
            .glass {
                background: rgba(255, 255, 255, 0.7);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            }
            .pb-safe {
                padding-bottom: env(safe-area-inset-bottom);
            }
        </style>
    </head>
    <body class="antialiased text-slate-900 bg-slate-50 selection:bg-indigo-100">
        <!-- Animated Background -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute -left-[10%] -top-[10%] h-[40%] w-[40%] rounded-full bg-indigo-100/50 blur-[120px] animate-pulse"></div>
            <div class="absolute -right-[10%] bottom-[10%] h-[40%] w-[40%] rounded-full bg-rose-50/50 blur-[120px] animate-pulse" style="animation-delay: 2s"></div>
        </div>

        <div class="min-h-screen flex flex-col">
            <!-- Header -->
            <header class="sticky top-0 z-40 glass">
                <div class="mx-auto flex h-16 max-w-lg items-center justify-between px-6">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        </div>
                        <span class="font-display text-xl font-black tracking-tight text-slate-900">
                            TAU<span class="text-indigo-600">FANI</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="group flex h-10 w-10 items-center justify-center rounded-2xl text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <main class="mx-auto w-full max-w-lg flex-1 px-6 pt-6 pb-32">
                {{ $slot }}
            </main>

            <!-- Bottom nav -->
            <nav class="fixed bottom-6 left-1/2 z-40 -translate-x-1/2 w-[90%] max-w-md">
                <div class="glass flex items-center justify-around py-3 px-2 rounded-[2.5rem] shadow-2xl shadow-indigo-100 border border-white/50">
                    <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 px-4 py-2 transition-all {{ request()->routeIs('dashboard') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ request()->routeIs('dashboard') ? '3' : '2.5' }}" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        <span class="text-[9px] font-black uppercase tracking-widest">Dash</span>
                    </a>
                    
                    <a href="/add-expense" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-200 transition-all hover:scale-110 active:scale-90">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    </a>

                    <a href="/groups" class="flex flex-col items-center gap-1 px-4 py-2 transition-all {{ request()->is('groups*') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ request()->is('groups*') ? '3' : '2.5' }}" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span class="text-[9px] font-black uppercase tracking-widest">Groups</span>
                    </a>

                    <a href="/settle" class="flex flex-col items-center gap-1 px-4 py-2 transition-all {{ request()->is('settle*') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ request()->is('settle*') ? '3' : '2.5' }}" stroke-linecap="round" stroke-linejoin="round"><path d="m11 17 2 2 6-6"/><path d="m18 14 2.5 2.5a3.37 3.37 0 0 1-4.77 4.77L11 15"/><path d="m2 18 2.5-2.5a3.37 3.37 0 0 0 4.77-4.77L14 16"/><path d="m13 7 2 2-6 6"/><path d="m6 10-2.5 2.5a3.37 3.37 0 0 0 4.77 4.77L13 12"/></svg>
                        <span class="text-[9px] font-black uppercase tracking-widest">Settle</span>
                    </a>
                </div>
            </nav>
        </div>
        @livewireScripts
    </body>
</html>
