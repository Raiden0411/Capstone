{{-- resources/views/public/auth/⚡reset-password.blade.php --}}
<?php

use App\Models\SiteSetting;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Reset Password')]
class extends Component
{
    public string $token                 = '';
    public string $email                 = '';
    public string $password              = '';
    public string $password_confirmation = '';

    public ?string $errorMessage = null;

    public function mount(string $token): void
    {
        if (Auth::check()) {
            $this->redirect(route('home'), navigate: true);
            return;
        }

        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    protected function rules(): array
    {
        return [
            'token'    => ['required', 'string'],
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resetPassword()
    {
        $this->validate();
        $this->errorMessage = null;

        try {
            $status = Password::reset(
                [
                    'email'                 => strtolower(trim($this->email)),
                    'password'              => $this->password,
                    'password_confirmation' => $this->password_confirmation,
                    'token'                 => $this->token,
                ],
                function ($user, $password): void {
                    DB::transaction(function () use ($user, $password): void {
                        $user->forceFill([
                            'password'       => Hash::make($password),
                            'remember_token' => Str::random(60),
                        ])->save();

                        // Force-logout every other session for this user.
                        // This is the standard "password changed → kill
                        // all sessions" security practice.
                        DB::table('sessions')
                            ->where('user_id', $user->id)
                            ->delete();

                        event(new PasswordReset($user));
                    });
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return redirect()
                    ->route('login')
                    ->with('message', 'Password reset successfully. Please sign in with your new password.');
            }

            $this->errorMessage = match ($status) {
                Password::INVALID_TOKEN => 'This reset link is invalid or has expired. Please request a new one.',
                Password::INVALID_USER  => 'We couldn\'t find an account with that email address.',
                default                 => 'Could not reset your password. Please try again.',
            };

            return null;
        } catch (\Throwable $e) {
            Log::error('Password reset failed', [
                'email' => $this->email,
                'error' => $e->getMessage(),
                'type'  => get_class($e),
            ]);

            $this->errorMessage = 'Something went wrong. Please try again, or request a new reset link.';
            return null;
        }
    }

    // ─────────────────────────────────────────────────────
    //  Branding — Rule 81: migrated from a pre-root @php
    //  block (unsupported/unreliable in SFCs) to
    //  #[Computed] methods.
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function siteName(): string
    {
        return (string) SiteSetting::getValue('site_name', config('app.name'));
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        $path = SiteSetting::getValue('site_logo');

        return $path ? asset('storage/' . $path) : null;
    }

    #[Computed]
    public function heroUrl(): string
    {
        $path = SiteSetting::getValue('hero_background_image');

        return $path
            ? asset('storage/' . $path)
            : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';
    }
};
?>

<div class="min-h-screen flex flex-col md:flex-row bg-white dark:bg-gray-900">

    {{-- Left Side: Hero Image & Text --}}
    <div class="relative w-full md:w-3/5 min-h-[220px] md:min-h-screen order-1 md:order-1">
        <img src="{{ $this->heroUrl }}" alt="{{ $this->siteName }}"
             loading="eager" fetchpriority="high" decoding="async"
             width="1600" height="900"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-24 text-white">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/10">
                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                {{ $this->siteName }}
            </span>

            <h1 class="mt-5 text-2xl sm:text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.1]">
                Choose a new<br />password
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Pick something strong and unique. Minimum 8 characters — a mix of letters, numbers, and symbols is best.
            </p>
        </div>
    </div>

    {{-- Right Side: Form --}}
    <div class="flex items-start justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
        <div class="w-full max-w-md">

            <a href="{{ route('login') }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95 mb-6 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Login
            </a>

            <div class="flex items-center gap-3 mb-6">
                @if($this->logoUrl)
                    <img src="{{ $this->logoUrl }}" alt="{{ $this->siteName }} logo"
                         width="40" height="40" decoding="async"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0 font-semibold">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $this->siteName }}</span>
            </div>

            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Set a new password
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Enter your new password below to complete the reset.
            </p>

            @if($errorMessage)
                <div role="alert" aria-live="polite"
                     class="mb-5 flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 rounded-md p-3.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-rose-700 dark:text-rose-300">
                        <p>{{ $errorMessage }}</p>
                        <a href="{{ route('password.request') }}" wire:navigate
                           class="mt-1 inline-block font-semibold underline underline-offset-2 hover:text-rose-800 dark:hover:text-rose-200 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded">
                            Request a new reset link →
                        </a>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div role="alert" aria-live="polite"
                     class="mb-5 flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 rounded-md p-3.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <ul class="space-y-0.5 text-sm text-rose-700 dark:text-rose-300">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form wire:submit="resetPassword" class="space-y-5"
                  x-data="{ showPassword: false, showConfirmPassword: false }">

                {{-- Email (prefilled from reset link) --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <div class="mt-1.5">
                        <input id="email"
                               type="email"
                               wire:model="email"
                               autocomplete="email"
                               placeholder="example@email.com"
                               class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('email') border-rose-400/60 @enderror">
                    </div>
                    @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- New Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        New Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative mt-1.5">
                        <input :type="showPassword ? 'text' : 'password'"
                               id="password" wire:model="password"
                               autocomplete="new-password" minlength="8"
                               placeholder="At least 8 characters"
                               class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('password') border-rose-400/60 @enderror">
                        {{-- Rule 69: :class toggling. --}}
                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-full"
                                tabindex="-1" aria-label="Toggle password visibility">
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="!showPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="showPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- Confirm --}}
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Confirm New Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative mt-1.5">
                        <input :type="showConfirmPassword ? 'text' : 'password'"
                               id="password_confirmation" wire:model="password_confirmation"
                               autocomplete="new-password" minlength="8"
                               placeholder="Re-enter your new password"
                               class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('password_confirmation') border-rose-400/60 @enderror">
                        {{-- Rule 69: :class toggling. --}}
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-full"
                                tabindex="-1" aria-label="Toggle password visibility">
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="!showConfirmPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" x-cloak :class="showConfirmPassword ? '' : 'hidden'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    @error('password_confirmation') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm
                               disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="resetPassword">Reset password</span>
                    <span wire:loading wire:target="resetPassword" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Resetting…
                    </span>
                </button>
            </form>

        </div>
    </div>

</div>