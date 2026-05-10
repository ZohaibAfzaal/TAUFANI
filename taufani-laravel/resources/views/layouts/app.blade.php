<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Taufani') }}</title>

        <!-- Plus Jakarta Sans — variable font (200–800) -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @php use Illuminate\Support\Facades\Storage; @endphp

        <style>
            #lw-progress-bar {
                position: fixed;
                top: 0; left: 0;
                height: 2.5px;
                width: 0%;
                z-index: 9999;
                background: linear-gradient(90deg, #6366f1, #8b5cf6, #a78bfa);
                border-radius: 0 2px 2px 0;
                opacity: 0;
                transition: width 0.5s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease;
                pointer-events: none;
                box-shadow: 0 0 10px rgba(99,102,241,0.6);
            }
            #lw-spinner {
                position: fixed;
                top: 4.5rem;
                left: 50%;
                transform: translateX(-50%) translateY(-8px);
                z-index: 9998;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease, transform 0.2s ease;
            }
            #lw-spinner.visible {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            .lw-ring {
                width: 16px; height: 16px;
                border: 2px solid rgba(99,102,241,0.2);
                border-top-color: #6366f1;
                border-radius: 50%;
                animation: lw-spin 0.7s linear infinite;
            }
            @keyframes lw-spin {
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body class="antialiased text-slate-900 bg-slate-50 selection:bg-indigo-100">

        <div id="lw-progress-bar"></div>

        <div id="lw-spinner">
            <div class="flex items-center gap-2 rounded-full bg-white/90 backdrop-blur-lg border border-slate-100 shadow-lg shadow-slate-200/60 px-3.5 py-2">
                <div class="lw-ring"></div>
                <span class="text-[11px] font-semibold text-slate-500">Loading…</span>
            </div>
        </div>

        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute -left-[10%] -top-[10%] h-[40%] w-[40%] rounded-full bg-indigo-100/50 blur-[120px] animate-pulse"></div>
            <div class="absolute -right-[10%] bottom-[10%] h-[40%] w-[40%] rounded-full bg-rose-50/50 blur-[120px] animate-pulse" style="animation-delay:2s"></div>
        </div>

        <div class="min-h-screen flex flex-col">

            <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-slate-100/80 shadow-sm">
                <div class="mx-auto flex h-14 max-w-lg items-center gap-3 px-4">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-200">
                        <x-icon name="layers" class="w-3.5 h-3.5" stroke-width="2.5" />
                    </div>

                    <div class="flex-1 min-w-0 max-w-[200px]">
                        <livewire:group-switcher-nav />
                    </div>

                    <a href="{{ route('profile.edit') }}" wire:navigate
                       class="shrink-0 relative flex h-9 w-9 items-center justify-center rounded-2xl overflow-hidden border-2 {{ request()->routeIs('profile.edit') ? 'border-indigo-500' : 'border-slate-100' }} transition-all hover:border-indigo-300 shadow-sm">
                        @if(auth()->user()->profile_photo_path)
                            <img src="{{ Storage::url(auth()->user()->profile_photo_path) }}" alt="Avatar" class="h-full w-full object-cover">
                        @else
                            <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode(auth()->user()->name) }}"
                                 alt="{{ auth()->user()->name }}"
                                 class="h-full w-full object-cover bg-indigo-50">
                        @endif
                    </a>
                </div>
            </header>

            <main class="mx-auto w-full max-w-lg flex-1 px-6 pt-6 pb-32">
                {{ $slot }}
            </main>

            <nav class="fixed bottom-6 left-1/2 z-40 -translate-x-1/2 w-[90%] max-w-md">
                <div class="flex items-center justify-around py-3 px-2 rounded-[2.5rem] bg-white/80 backdrop-blur-xl shadow-2xl shadow-indigo-100/60 border border-white/60">

                    <a href="{{ route('dashboard') }}" wire:navigate
                       class="flex flex-col items-center gap-1 px-5 py-2 rounded-2xl transition-all {{ request()->routeIs('dashboard') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <x-icon name="layout-dashboard" class="w-[22px] h-[22px]" stroke-width="{{ request()->routeIs('dashboard') ? '3' : '2' }}" />
                        <span class="text-[9px] font-bold uppercase tracking-widest">Dash</span>
                    </a>

                    <a href="{{ route('expenses.create') }}" wire:navigate
                       class="flex h-13 w-13 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-200 transition-all hover:scale-110 hover:bg-indigo-700 active:scale-90"
                       style="width:3.25rem;height:3.25rem">
                        <x-icon name="plus" class="w-6 h-6" stroke-width="3" />
                    </a>

                    <a href="{{ route('expenses') }}" wire:navigate
                       class="flex flex-col items-center gap-1 px-4 py-2 rounded-2xl transition-all {{ request()->routeIs('expenses') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <x-icon name="receipt" class="w-[22px] h-[22px]" stroke-width="{{ request()->routeIs('expenses') ? '3' : '2' }}" />
                        <span class="text-[9px] font-bold uppercase tracking-widest">Expenses</span>
                    </a>

                    <a href="{{ route('balances') }}" wire:navigate
                       class="flex flex-col items-center gap-1 px-3 py-2 rounded-2xl transition-all {{ request()->routeIs('balances') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <x-icon name="scale" class="w-[22px] h-[22px]" stroke-width="{{ request()->routeIs('balances') ? '3' : '2' }}" />
                        <span class="text-[9px] font-bold uppercase tracking-widest">Balances</span>
                    </a>

                    <a href="{{ route('settle') }}" wire:navigate
                       class="flex flex-col items-center gap-1 px-3 py-2 rounded-2xl transition-all {{ request()->routeIs('settle') ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600' }}">
                        <x-icon name="arrow-right-left" class="w-[22px] h-[22px]" stroke-width="{{ request()->routeIs('settle') ? '3' : '2' }}" />
                        <span class="text-[9px] font-bold uppercase tracking-widest">Settle</span>
                    </a>

                </div>
            </nav>
        </div>

        @livewireScripts

        <script>
            document.addEventListener('livewire:initialized', () => {
                const bar     = document.getElementById('lw-progress-bar');
                const spinner = document.getElementById('lw-spinner');
                let spinnerTimer = null;
                let hideTimer    = null;

                function showLoading() {
                    clearTimeout(hideTimer);
                    bar.style.transition = 'none';
                    bar.style.width      = '0%';
                    bar.style.opacity    = '1';
                    requestAnimationFrame(() => {
                        bar.style.transition = 'width 0.6s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease';
                        bar.style.width      = '75%';
                    });
                    spinnerTimer = setTimeout(() => spinner.classList.add('visible'), 200);
                }

                function hideLoading() {
                    clearTimeout(spinnerTimer);
                    spinner.classList.remove('visible');
                    bar.style.transition = 'width 0.2s ease, opacity 0.4s ease';
                    bar.style.width      = '100%';
                    hideTimer = setTimeout(() => {
                        bar.style.opacity = '0';
                        setTimeout(() => { bar.style.width = '0%'; }, 400);
                    }, 200);
                }

                // Component update requests (wire:click, wire:model, etc.)
                Livewire.hook('request', ({ succeed, fail }) => {
                    showLoading();
                    succeed(() => hideLoading());
                    fail(() => hideLoading());
                });

                // Page navigation requests (wire:navigate links)
                document.addEventListener('livewire:navigate', () => showLoading());
                document.addEventListener('livewire:navigated', () => hideLoading());
            });
        </script>
    </body>
</html>
