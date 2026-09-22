{{-- resources/views/public/auth/⚡forgot-password.blade.php --}}
<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Forgot Password')]
class extends Component
{
    public string $email     = '';
    public bool   $submitted = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address.',
        ];
    }

    public function sendResetLink(): void
    {
        $this->validate();

        $email = strtolower(trim($this->email));
        $ip    = request()->ip();

        // ═══════════════════════════════════════════════════════════
        // 1. IP-ONLY LIMITER — broad abuse prevention
        // ═══════════════════════════════════════════════════════════
        // Prevents one IP from probing multiple different emails.
        // 5 attempts / 10 minutes.
        $ipKey = 'password-reset-ip:' . $ip;

        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            $seconds = RateLimiter::availableIn($ipKey);
            $this->addError('email', "Too many requests from your network. Please try again in {$seconds} seconds.");
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 2. EMAIL + IP LIMITER — targeted per-email protection
        // ═══════════════════════════════════════════════════════════
        // Prevents someone from repeatedly bombing a single user's
        // inbox with reset emails.
        $emailKey = 'password-reset:' . $email . '|' . $ip;

        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $seconds = RateLimiter::availableIn($emailKey);
            $this->addError('email', "Too many reset attempts for this email. Please try again in {$seconds} seconds.");
            return;
        }

        // Hit both counters BEFORE any DB work — failed attempts
        // still count toward the limit.
        RateLimiter::hit($ipKey, 600);    // 10 minutes
        RateLimiter::hit($emailKey, 60);  // 60 seconds

        // ═══════════════════════════════════════════════════════════
        // 3. EXPLICIT EMAIL CHECK
        // ═══════════════════════════════════════════════════════════
        // Per the user's request: tell them plainly when no account
        // exists instead of a generic "check your inbox" message.
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->addError('email', 'No account found with that email address. Please check and try again.');
            return;
        }

        // ═══════════════════════════════════════════════════════════
        // 4. DISPATCH THE RESET EMAIL
        // ═══════════════════════════════════════════════════════════
        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (\Throwable $e) {
            Log::warning('Password reset mail dispatch failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'type'  => get_class($e),
            ]);

            $this->addError('email', 'Could not send the reset email right now. Please try again in a moment.');
            return;
        }

        if ($status !== Password::RESET_LINK_SENT) {
            // Laravel's broker can reject with its own throttle status
            // (Password::RESET_THROTTLED) — surface it gracefully.
            $this->addError('email', match ($status) {
                Password::RESET_THROTTLED => 'A reset link was requested recently. Please wait a minute and check your inbox.',
                default                   => 'Could not send the reset email. Please try again in a moment.',
            });
            return;
        }

        $this->submitted = true;
    }

    public function resetForm(): void
    {
        $this->submitted = false;
        $this->email     = '';
        $this->resetValidation();
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
                Forgotten passwords<br />are easily fixed
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Enter the email address you used to create your account and we'll send you a secure link to choose a new password.
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

            @if($submitted)

                {{-- ── Success state ─────────────────────────────── --}}
                <div class="flex items-center justify-center w-14 h-14 rounded-2xl
                            bg-emerald-100 dark:bg-emerald-500/15
                            text-emerald-600 dark:text-emerald-400 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>

                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Check your inbox
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-6">
                    We've sent a password reset link to <strong class="text-gray-900 dark:text-white">{{ $email }}</strong>. The link expires in 60 minutes.
                </p>

                <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3 py-2.5 text-xs text-blue-800 dark:text-blue-300 mb-6 flex items-start gap-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="leading-relaxed">
                        Can't find it? Check your spam folder, or wait a minute and try again.
                    </span>
                </div>

                <button type="button"
                        wire:click="resetForm"
                        class="w-full inline-flex items-center justify-center h-11 text-sm font-semibold text-gray-700 dark:text-gray-200
                               border border-gray-300 dark:border-gray-700
                               hover:bg-gray-50 dark:hover:bg-gray-800/60
                               rounded-xl transition-all duration-200 active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Use a different email
                </button>

            @else

                {{-- ── Form state ────────────────────────────────── --}}
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                    Forgot your password?
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    No problem — enter your email and we'll send you a reset link.
                </p>

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

                <form wire:submit="sendResetLink" class="space-y-5">

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <div class="mt-1.5">
                            <input id="email"
                                   type="email"
                                   wire:model="email"
                                   autofocus
                                   autocomplete="email"
                                   placeholder="example@email.com"
                                   class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('email') border-rose-400/60 @enderror">
                        </div>
                        @error('email')
                            <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendResetLink"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm
                                   disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span wire:loading.remove wire:target="sendResetLink">Send reset link</span>
                        <span wire:loading wire:target="sendResetLink" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Sending…
                        </span>
                    </button>
                </form>

                <p class="mt-6 text-xs text-center text-gray-400 dark:text-gray-500 leading-relaxed">
                    We'll only send a link if the email is registered.
                </p>

            @endif

            <p class="mt-8 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                Remembered it after all?
                <a href="{{ route('login') }}" wire:navigate
                   class="text-primary-600 hover:underline ml-1 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Back to Login
                </a>
            </p>

        </div>
    </div>

</div>