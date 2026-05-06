<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Taufani') }}</title>

        <!-- Plus Jakarta Sans -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased text-slate-900 bg-slate-50 selection:bg-indigo-100">
        <!-- Ambient gradient -->
        <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
            <div class="absolute -left-[20%] -top-[20%] h-[60%] w-[60%] rounded-full bg-indigo-100/60 blur-[140px]"></div>
            <div class="absolute -right-[20%] bottom-0 h-[50%] w-[50%] rounded-full bg-rose-50/50 blur-[140px]"></div>
        </div>

        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-12">

            <!-- Brand mark -->
            <a href="/" class="flex items-center gap-3 mb-10">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xl shadow-indigo-200">
                    <x-icon name="layers" class="w-5 h-5" stroke-width="2.5" />
                </div>
                <span class="text-2xl font-extrabold tracking-tight text-slate-900">
                    Tau<span class="text-indigo-600">fani</span>
                </span>
            </a>

            <!-- Auth card -->
            <div class="w-full max-w-sm rounded-[2.5rem] bg-white/80 backdrop-blur-xl px-8 py-10 shadow-2xl shadow-slate-200/60 border border-white/60">
                {{ $slot }}
            </div>

        </div>
    </body>
</html>
