{{-- resources/views/public/auth/⚡forgot-password.blade.php --}}
<?php

use App\Models\SiteSetting;
use App\Models\Tenant;
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

        $ipKey = 'password-reset-ip:' . $ip;

        if (RateLimiter::tooManyAttempts($ipKey, 5)) {
            $seconds = RateLimiter::availableIn($ipKey);
            $this->addError('email', "Too many requests from your network. Please try again in {$seconds} seconds.");
            return;
        }

        $emailKey = 'password-reset:' . $email . '|' . $ip;

        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            $seconds = RateLimiter::availableIn($emailKey);
            $this->addError('email', "Too many reset attempts for this email. Please try again in {$seconds} seconds.");
            return;
        }

        RateLimiter::hit($ipKey, 600);
        RateLimiter::hit($emailKey, 60);

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $this->addError('email', 'No account found with that email address. Please check and try again.');
            return;
        }

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

    /**
     * @return array<int, array{name: string, slug: string, type: string, logo: string, url: string}>
     */
    #[Computed]
    public function featuredTenants(): array
    {
        return Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('logo')
            ->whereNotNull('slug')
            ->with('typeOfTenant:id,type')
            ->orderByDesc('is_recommended')
            ->orderByDesc('verified_at')
            ->orderBy('name')
            ->limit(3)
            ->get(['id', 'name', 'slug', 'logo', 'type_of_tenant_id'])
            ->map(fn ($tenant) => [
                'name' => (string) $tenant->name,
                'slug' => (string) $tenant->slug,
                'type' => (string) ($tenant->typeOfTenant?->type ?? 'Destination'),
                'logo' => asset('storage/' . $tenant->logo),
                'url'  => route('business.offerings', $tenant->slug),
            ])
            ->all();
    }
};
?>

@push('styles')
    @once
        <style>
            .auth-form-panel {
                background-image:
                    radial-gradient(ellipse 70% 50% at 50% 0%, rgba(245,158,11,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 60% at 100% 100%, rgba(59,130,246,.04) 0%, transparent 55%);
            }
            .dark .auth-form-panel {
                background-image:
                    radial-gradient(ellipse 70% 50% at 50% 0%, rgba(245,158,11,.09) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 60% at 100% 100%, rgba(59,130,246,.07) 0%, transparent 55%);
            }
            .auth-hero-vignette {
                background: radial-gradient(ellipse 90% 80% at 50% 50%, transparent 40%, rgba(0,0,0,.35) 100%);
            }
            @keyframes authChipIn {
                from { opacity: 0; transform: translateY(6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .auth-chip { animation: authChipIn .5s cubic-bezier(.16,1,.3,1) both; }
            .auth-chip:nth-child(1) { animation-delay: .25s; }
            .auth-chip:nth-child(2) { animation-delay: .35s; }
            .auth-chip:nth-child(3) { animation-delay: .45s; }
            @media (prefers-reduced-motion: reduce) {
                .auth-chip { animation: none; }
            }
        </style>
    @endonce
@endpush

<div class="min-h-screen flex flex-col md:flex-row bg-white dark:bg-gray-900">

    {{-- ═══════════════ HERO ═══════════════ --}}
    <div class="relative w-full md:w-1/2 min-h-[200px] sm:min-h-[260px] md:min-h-screen md:h-screen md:sticky md:top-0 overflow-hidden">

        <img src="{{ $this->heroUrl }}"
             alt="" aria-hidden="true"
             loading="eager" fetchpriority="high" decoding="async"
             width="1600" height="900"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-br from-black/30 via-black/40 to-black/85"></div>
        <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black/85 via-black/40 to-transparent"></div>
        <div class="absolute inset-0 auth-hero-vignette pointer-events-none" aria-hidden="true"></div>
        <div class="absolute inset-x-0 top-0 h-1/3 bg-gradient-to-b from-amber-500/12 via-amber-500/4 to-transparent pointer-events-none" aria-hidden="true"></div>

        <div class="relative h-full flex flex-col justify-between
                    p-5 sm:p-8 md:p-12 lg:p-16 text-white">

            <div>
                <span class="inline-flex max-w-full items-center gap-2 px-3 py-1.5
                             text-[11px] font-bold tracking-[0.22em] uppercase
                             text-white bg-black/40 rounded-full backdrop-blur-md
                             border border-white/15 shadow-sm">
                    <span class="w-1.5 h-1.5 bg-amber-400 rounded-full shrink-0" aria-hidden="true"></span>
                    <span class="truncate">{{ $this->siteName }}</span>
                </span>
            </div>

            <div class="mt-8 md:mt-auto">
                <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight
                           leading-[1.06] text-white max-w-3xl
                           [text-wrap:balance]">
                    Forgotten passwords are easily fixed
                </h2>

                <p class="max-w-xl mt-4 sm:mt-5 text-sm sm:text-base leading-relaxed text-white/75">
                    Enter the email address you used to create your account and we'll send you a secure link to choose a new password.
                </p>

                @if(!empty($this->featuredTenants))
                    <div class="hidden md:block mt-6 md:mt-7">
                        <p class="mb-2.5 inline-flex items-center gap-2
                                  text-[10px] font-bold uppercase tracking-[0.22em]
                                  text-amber-300/90">
                            <span class="h-px w-4 bg-amber-400/70" aria-hidden="true"></span>
                            Featured Spots
                        </p>
                        <div class="flex gap-2 flex-wrap">
                            @foreach($this->featuredTenants as $ft)
                                <a href="{{ $ft['url'] }}"
                                   wire:navigate
                                   wire:key="forgot-feat-{{ $ft['slug'] }}"
                                   class="auth-chip group shrink-0 inline-flex items-center gap-2
                                          pl-1.5 pr-3 py-1.5 rounded-full
                                          bg-white/[0.08] hover:bg-white/[0.16]
                                          ring-1 ring-inset ring-white/15 hover:ring-white/35
                                          text-white
                                          transition-all duration-200 active:scale-[0.97]
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-300/60 focus-visible:ring-offset-2 focus-visible:ring-offset-black/40">
                                    <img src="{{ $ft['logo'] }}" alt="" width="28" height="28"
                                         loading="lazy" decoding="async"
                                         class="w-7 h-7 rounded-full object-cover bg-white/10 shrink-0">
                                    <span class="text-xs sm:text-[13px] font-medium leading-none truncate max-w-[130px]">
                                        {{ $ft['name'] }}
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════ FORM ═══════════════ --}}
    <div class="auth-form-panel flex items-start md:items-center justify-center w-full min-w-0
                px-5 sm:px-8 md:px-10 lg:px-16 py-10 md:py-12
                bg-white dark:bg-gray-900 md:w-1/2">
        <div class="w-full max-w-md min-w-0">

            <a href="{{ route('login') }}" wire:navigate
               class="inline-flex items-center gap-1.5 text-sm font-medium
                      text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                      transition-colors mb-8 -mx-1 px-1 py-1 rounded
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Login
            </a>

            <div class="flex items-center gap-3 mb-8 min-w-0">
                @if($this->logoUrl)
                    <img src="{{ $this->logoUrl }}" alt="{{ $this->siteName }} logo"
                         width="40" height="40" decoding="async"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0 font-bold text-lg"
                         aria-hidden="true">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $this->siteName }}</span>
            </div>

            @if($submitted)

                {{-- Success state --}}
                <div class="flex items-center justify-center w-14 h-14 rounded-2xl
                            bg-emerald-100 dark:bg-emerald-500/15
                            text-emerald-600 dark:text-emerald-400 mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2 break-words leading-tight tracking-tight">
                    Check your inbox
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-6">
                    We've sent a password reset link to <strong class="text-gray-900 dark:text-white break-all">{{ $email }}</strong>. The link expires in 60 minutes.
                </p>

                <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3.5 py-3 text-xs text-blue-800 dark:text-blue-300 mb-6 flex items-start gap-2.5">
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
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    Use a different email
                </button>

            @else

                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2 break-words leading-tight tracking-tight">
                    Forgot your password?
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                    No problem — enter your email and we'll send you a reset link.
                </p>

                @if ($errors->any())
                    <div role="alert" aria-live="polite"
                         class="mb-5 flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 rounded-xl p-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <ul class="space-y-0.5 text-sm text-rose-700 dark:text-rose-300 min-w-0 break-words">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form wire:submit="sendResetLink" class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Email Address <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>
                        <div class="relative">
                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            <input id="email" type="email" wire:model="email"
                                   autofocus autocomplete="email"
                                   placeholder="example@email.com"
                                   class="input pl-10 @error('email') border-rose-400/60 @enderror">
                        </div>
                        @error('email')
                            <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="sendResetLink"
                            class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm shadow-primary-600/20
                                   disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
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

            <p class="mt-8 text-sm text-center text-gray-600 dark:text-gray-400">
                Remembered it after all?
                <a href="{{ route('login') }}" wire:navigate
                   class="relative text-primary-600 dark:text-primary-400 font-medium hover:underline ml-1
                          before:absolute before:content-[''] before:-inset-2 before:rounded
                          transition-all duration-200 active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-0.5">
                    Back to Login
                </a>
            </p>

            <div class="mt-10 pt-5 border-t border-gray-100 dark:border-gray-800
                        flex items-center justify-between gap-3
                        text-[11px] text-gray-400 dark:text-gray-600">
                <span class="truncate">© {{ date('Y') }} {{ $this->siteName }}</span>
                <a href="{{ route('home') }}" wire:navigate
                   class="relative inline-flex items-center gap-1 shrink-0
                          hover:text-primary-600 dark:hover:text-primary-400
                          transition-all duration-200 active:scale-95
                          before:absolute before:content-[''] before:-inset-2 before:rounded
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-0.5">
                    Continue browsing
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </a>
            </div>

        </div>
    </div>
</div>