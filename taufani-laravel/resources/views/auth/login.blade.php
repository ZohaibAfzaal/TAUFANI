<x-guest-layout>
    <div class="mb-8 text-center">
        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Welcome back</h1>
        <p class="mt-1 text-sm text-slate-500">Sign in to continue to Taufani</p>
    </div>

    <x-auth-session-status class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 border border-emerald-100" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <x-input-label for="password" :value="__('Password')" class="mb-0" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                        Forgot password?
                    </a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="flex items-center gap-2.5 cursor-pointer">
            <input id="remember_me" type="checkbox" name="remember"
                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0">
            <span class="text-sm text-slate-600">Keep me signed in</span>
        </label>

        <x-primary-button class="mt-2">Sign in</x-primary-button>

        @if (Route::has('register'))
            <p class="text-center text-sm text-slate-500">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">Create one</a>
            </p>
        @endif
    </form>
</x-guest-layout>
