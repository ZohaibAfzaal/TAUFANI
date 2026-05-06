<x-app-layout>
    <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-20">

        <!-- Header -->
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="flex h-9 w-9 items-center justify-center rounded-2xl bg-white border border-slate-100 shadow-sm text-slate-500 hover:text-slate-900 transition-colors">
                <x-icon name="chevron-left" class="w-5 h-5" stroke-width="2.5" />
            </a>
            <div>
                <h2 class="text-xl font-extrabold text-slate-900">Profile</h2>
                <p class="text-xs text-slate-400">Manage your account settings</p>
            </div>
        </div>

        <!-- Avatar + Basic Info Card -->
        <div class="rounded-[2rem] bg-white border border-slate-100 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('patch')

                <!-- Avatar Section -->
                <div class="flex flex-col items-center pt-8 pb-6 px-6 bg-gradient-to-b from-indigo-50/50 to-white border-b border-slate-100">
                    <div class="relative group mb-4">
                        <div class="h-24 w-24 rounded-[2rem] overflow-hidden bg-indigo-100 border-4 border-white shadow-xl shadow-indigo-100">
                            @if($user->profile_photo_path)
                                <img src="{{ Storage::url($user->profile_photo_path) }}" alt="{{ $user->name }}" class="h-full w-full object-cover" id="avatar-preview">
                            @else
                                <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-indigo-500 to-violet-600" id="avatar-placeholder">
                                    <span class="text-3xl font-black text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                </div>
                                <img src="" alt="" class="h-full w-full object-cover hidden" id="avatar-preview">
                            @endif
                        </div>
                        <label for="photo" class="absolute -bottom-2 -right-2 flex h-8 w-8 cursor-pointer items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-colors">
                            <x-icon name="camera" class="w-3.5 h-3.5" stroke-width="2.5" />
                        </label>
                        <input type="file" id="photo" name="photo" class="sr-only" accept="image/*"
                            onchange="
                                const file = this.files[0];
                                if (!file) return;
                                const url = URL.createObjectURL(file);
                                const preview = document.getElementById('avatar-preview');
                                const placeholder = document.getElementById('avatar-placeholder');
                                preview.src = url;
                                preview.classList.remove('hidden');
                                if (placeholder) placeholder.classList.add('hidden');
                            ">
                    </div>
                    <p class="text-[11px] font-medium text-slate-400">Tap the camera to change photo</p>
                    <x-input-error :messages="$errors->get('photo')" class="mt-1" />
                </div>

                <!-- Fields -->
                <div class="p-6 space-y-5">
                    <div>
                        <x-input-label for="name" :value="__('Full Name')" />
                        <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email Address')" />
                        <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" />

                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <div class="mt-2 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3">
                                <p class="text-xs font-medium text-amber-700">
                                    Your email is unverified.
                                    <button form="send-verification" class="underline font-semibold hover:text-amber-900">Resend verification</button>
                                </p>
                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-1 text-xs font-semibold text-emerald-600">Verification link sent!</p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

                    @if (session('status') === 'profile-updated')
                        <div class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3"
                             x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)">
                            <x-icon name="circle-check" class="w-4 h-4 text-emerald-500 shrink-0" stroke-width="2.5" />
                            <p class="text-xs font-semibold text-emerald-700">Profile saved successfully</p>
                        </div>
                    @endif

                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Change Password Card -->
        <div class="rounded-[2rem] bg-white border border-slate-100 shadow-sm">
            <form method="post" action="{{ route('password.update') }}">
                @csrf
                @method('put')

                <div class="p-6 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Change Password</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Use a strong, unique password</p>
                </div>

                <div class="p-6 space-y-5">
                    <div>
                        <x-input-label for="current_password" :value="__('Current Password')" />
                        <x-text-input id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="••••••••" />
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" />
                    </div>

                    <div>
                        <x-input-label for="new_password" :value="__('New Password')" />
                        <x-text-input id="new_password" name="password" type="password" autocomplete="new-password" placeholder="Min. 8 characters" />
                        <x-input-error :messages="$errors->updatePassword->get('password')" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm New Password')" />
                        <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat new password" />
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
                    </div>

                    @if (session('status') === 'password-updated')
                        <div class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3"
                             x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 3000)">
                            <x-icon name="circle-check" class="w-4 h-4 text-emerald-500 shrink-0" stroke-width="2.5" />
                            <p class="text-xs font-semibold text-emerald-700">Password updated</p>
                        </div>
                    @endif

                    <x-primary-button>Update Password</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Danger Zone -->
        <div class="rounded-[2rem] bg-white border border-rose-100 shadow-sm">
            <div class="p-6 border-b border-rose-50">
                <h3 class="text-sm font-bold text-rose-600">Danger Zone</h3>
                <p class="text-xs text-slate-400 mt-0.5">Permanently delete your account and all data</p>
            </div>
            <div class="p-6">
                <form method="post" action="{{ route('profile.destroy') }}"
                      onsubmit="return confirm('Are you sure? This cannot be undone.')">
                    @csrf
                    @method('delete')

                    <div class="mb-5">
                        <x-input-label for="delete_password" :value="__('Confirm with your password')" />
                        <x-text-input id="delete_password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" />
                        <x-input-error :messages="$errors->userDeletion->get('password')" />
                    </div>

                    <button type="submit"
                        class="w-full rounded-2xl border-2 border-rose-200 bg-rose-50 py-3.5 text-sm font-bold text-rose-600 transition-all hover:bg-rose-500 hover:text-white hover:border-rose-500 active:scale-[0.98]">
                        Delete My Account
                    </button>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
