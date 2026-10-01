{{-- resources/views/public/auth/⚡login-form.blade.php --}}
<?php

use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.auth')]
#[Title('Login')]
class extends Component
{
    public string $email    = '';
    public string $password = '';
    public bool   $remember = false;

    public ?string $redirectTo = null;

    public function mount(): void
    {
        $this->redirectTo = $this->sanitizeRedirect(request()->query('redirect'));

        if (Auth::check()) {
            $this->redirect(
                $this->redirectTo ?? $this->roleBasedFallback(),
                navigate: true,
            );
            return;
        }
    }

    protected function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function login()
    {
        $this->validate();

        $email       = strtolower(trim($this->email));
        $throttleKey = $email . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->addError('email',
                "Too many login attempts. Please try again in {$seconds} seconds."
            );
            return null;
        }

        $credentials = [
            'email'    => $email,
            'password' => $this->password,
        ];

        if (!Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'The provided credentials do not match our records.');
            return null;
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();

            session()->invalidate();
            session()->regenerateToken();

            RateLimiter::hit($throttleKey, 60);

            $this->addError('email', 'Your account is not yet active. Please wait for approval.');
            return null;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        if ($this->redirectTo) {
            return redirect()->to($this->redirectTo);
        }

        return redirect()->to($this->roleBasedFallback());
    }

    /**
     * Post-login landing page.
     *
     * The tenant-employee branch uses `roles()->exists()` — NOT
     * `getAllPermissions()->count() > 0`. Spatie's getAllPermissions()
     * reads getPermissionsTeamId(), which is still 0 in this request
     * (SetPermissionsTeamId ran before Auth::attempt() promoted the
     * visitor to a user). User::roles() is overridden in the User model
     * to pin the relation to $this->tenant_id at build time, so its
     * result does not depend on the ambient team context.
     */
    protected function roleBasedFallback(): string
    {
        $user = Auth::user();

        if (!$user) {
            return route('home');
        }

        if ($user->hasRole('super-admin')) {
            return route('superadmin.dashboard');
        }

        if ($user->hasRole('admin')) {
            return route('tenant.dashboard');
        }

        if ($user->tenant_id && $user->roles()->exists()) {
            return route('tenant.employee.dashboard');
        }

        return route('home');
    }

    protected function sanitizeRedirect(?string $url): ?string
    {
        if (!$url || !str_starts_with($url, '/')) {
            return null;
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '\\')) {
            return null;
        }

        return $url;
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

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    #[Computed]
    public function heroUrl(): string
    {
        $path = SiteSetting::getValue('hero_background_image');

        return $path
            ? '/storage/' . ltrim($path, '/')
            : 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1600&q=80';
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
                'logo' => '/storage/' . ltrim($tenant->logo, '/'),
                'url'  => route('business.offerings', $tenant->slug),
            ])
            ->all();
    }

    #[Computed]
    public function businessSignupUrl(): string
    {
        return route('register', [
            'redirect' => $this->redirectTo ?: route('register_business'),
        ]);
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
            .auth-chip {
                animation: authChipIn .5s cubic-bezier(.16,1,.3,1) both;
            }
            .auth-chip:nth-child(1) { animation-delay: .25s; }
            .auth-chip:nth-child(2) { animation-delay: .35s; }
            .auth-chip:nth-child(3) { animation-delay: .45s; }

            @keyframes authCapsIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .auth-caps { animation: authCapsIn .18s ease-out; }

            @keyframes authNoticeIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .auth-notice { animation: authNoticeIn .2s ease-out; }

            @media (prefers-reduced-motion: reduce) {
                .auth-chip, .auth-caps, .auth-notice { animation: none; }
            }
        </style>
    @endonce
@endpush

<div class="min-h-screen flex flex-col md:flex-row bg-white dark:bg-gray-900">

    <div class="relative w-full md:w-1/2 min-h-[200px] sm:min-h-[260px] md:min-h-screen md:h-screen md:sticky md:top-0 overflow-hidden">

        <img src="{{ $this->heroUrl }}"
             alt=""
             aria-hidden="true"
             loading="eager"
             fetchpriority="high"
             decoding="async"
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

                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight
                           leading-[1.06] text-white max-w-3xl
                           [text-wrap:balance]">
                    Welcome back to<br class="hidden sm:block">
                    Victorias City
                </h1>

                <p class="max-w-xl mt-4 sm:mt-5 text-sm sm:text-base leading-relaxed text-white/75">
                    Your bookings, saved destinations, and local finds — all in one place.
                </p>

                @if(!empty($this->featuredTenants))
                    <div class="hidden md:block mt-6 md:mt-7">
                        <p class="mb-2.5 inline-flex items-center gap-2
                                  text-xs font-semibold uppercase tracking-[0.18em]
                                  text-amber-400">
                            <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                            Featured Spots
                        </p>

                        <div class="flex gap-2 flex-wrap">
                            @foreach($this->featuredTenants as $ft)
                                <a href="{{ $ft['url'] }}"
                                   wire:navigate
                                   wire:key="login-feat-{{ $ft['slug'] }}"
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
                                    <svg xmlns="http://www.w3.org/2000/svg"
                                         class="w-3 h-3 shrink-0 opacity-0 -translate-x-1
                                                group-hover:opacity-70 group-hover:translate-x-0
                                                transition-all duration-200"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="auth-form-panel flex items-center justify-center w-full min-w-0
                px-5 sm:px-8 md:px-10 lg:px-16 py-10 md:py-12
                bg-white dark:bg-gray-900 md:w-1/2">
        <div class="w-full max-w-md min-w-0">

            <a href="{{ route('home') }}" wire:navigate
               class="relative inline-flex items-center gap-1.5 text-sm font-medium
                      text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                      transition-colors mb-8 -mx-1 px-1 py-1 rounded
                      before:absolute before:content-[''] before:-inset-2 before:rounded
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Home
            </a>

            <div class="flex items-center gap-3 mb-8 min-w-0">
                @if($this->logoUrl)
                    <img src="{{ $this->logoUrl }}"
                         alt="{{ $this->siteName }} logo"
                         width="40" height="40"
                         decoding="async"
                         class="w-10 h-10 rounded-full object-contain ring-1 ring-black/5 shadow-sm dark:ring-white/10 shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center text-white shrink-0 font-bold text-lg ring-1 ring-black/5 shadow-sm dark:ring-white/10"
                         aria-hidden="true">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-base font-semibold text-gray-900 dark:text-white truncate">
                    {{ $this->siteName }}
                </span>
            </div>

            <p class="mb-2 inline-flex items-center gap-2
                      text-xs font-semibold uppercase tracking-[0.18em]
                      text-primary-600 dark:text-primary-400">
                <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                Sign in
            </p>

            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2 break-words leading-tight">
                Welcome back
            </h2>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                Sign in to manage your bookings, saved spots, and account.
            </p>

            @if ($errors->any())
                <div role="alert" aria-live="polite"
                     class="mb-5 flex items-start gap-3
                            bg-rose-50 dark:bg-rose-500/10
                            border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500
                            rounded-xl p-3.5">
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

            <form wire:submit="login" class="space-y-5"
                  x-data="{ showPassword: false, capsLock: false }">

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Email address
                    </label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                        </svg>
                        <input id="email"
                               type="email"
                               wire:model="email"
                               autofocus
                               autocomplete="username"
                               inputmode="email"
                               placeholder="you@example.com"
                               class="input pl-10 @error('email') border-rose-400/60 @enderror">
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3 mb-1.5">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Password
                        </label>
                        <a href="{{ route('password.request') }}" wire:navigate
                           class="relative text-xs font-semibold
                                  text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300
                                  hover:underline transition-all duration-200 active:scale-95 shrink-0
                                  before:absolute before:content-[''] before:-inset-2.5 before:rounded
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-1">
                            Forgot password?
                        </a>
                    </div>

                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <input :type="showPassword ? 'text' : 'password'"
                               id="password"
                               wire:model="password"
                               autocomplete="current-password"
                               placeholder="Enter your password"
                               @keyup="capsLock = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @keydown="capsLock = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @blur="capsLock = false"
                               class="input pl-10 pr-11 @error('password') border-rose-400/60 @enderror">

                        <button type="button"
                                @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword ? 'true' : 'false'"
                                class="absolute right-1 top-1/2 -translate-y-1/2
                                       inline-flex items-center justify-center w-8 h-8 rounded-lg
                                       text-gray-400 hover:text-gray-700 dark:hover:text-gray-200
                                       hover:bg-gray-100 dark:hover:bg-gray-700/60
                                       transition-colors
                                       before:absolute before:content-[''] before:-inset-1.5 before:rounded-lg
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <p x-cloak
                       x-show="capsLock"
                       class="auth-caps mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                       role="status"
                       aria-live="polite">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Caps Lock is on
                    </p>

                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center pt-1">
                    <label class="flex items-center min-h-[44px] text-sm text-gray-600 dark:text-gray-300 cursor-pointer select-none
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <input type="checkbox"
                               id="remember"
                               wire:model="remember"
                               class="shrink-0 w-4 h-4 rounded
                                      border-gray-300 dark:border-gray-600
                                      text-primary-600
                                      focus:ring-primary-500 focus:ring-offset-0
                                      bg-white dark:bg-gray-700">
                        <span class="ml-2">Keep me signed in</span>
                    </label>
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="login"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2
                               text-sm font-semibold text-white
                               bg-primary-600 hover:bg-primary-700
                               rounded-xl shadow-sm shadow-primary-600/20
                               transition-all duration-200
                               disabled:opacity-50 disabled:cursor-not-allowed
                               active:scale-[0.98]
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="login">Sign in</span>
                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-4 h-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Signing in…
                    </span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-800 space-y-2.5"
                 x-data="{ showBusinessNotice: false }">

                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    New here?
                    <a href="{{ route('register', $this->redirectTo ? ['redirect' => $this->redirectTo] : []) }}" wire:navigate
                       class="relative text-primary-600 dark:text-primary-400 font-semibold hover:underline ml-1
                              before:absolute before:content-[''] before:-inset-2 before:rounded
                              transition-all duration-200 active:scale-95
                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-0.5">
                        Create an account
                    </a>
                </p>

                <p class="text-sm text-center text-gray-600 dark:text-gray-400">
                    Own a tourist spot?
                    <button type="button"
                            @click="showBusinessNotice = !showBusinessNotice"
                            :aria-expanded="showBusinessNotice.toString()"
                            aria-controls="business-notice-inline"
                            class="relative text-primary-600 dark:text-primary-400 font-semibold hover:underline ml-1
                                   before:absolute before:content-[''] before:-inset-2 before:rounded
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-0.5">
                        Register your business
                    </button>
                </p>

                <div x-cloak
                     x-show="showBusinessNotice"
                     x-collapse
                     id="business-notice-inline"
                     class="auth-notice mt-3">
                    <div class="rounded-xl border border-blue-200/70 dark:border-blue-500/30
                                bg-blue-50/70 dark:bg-blue-500/[0.08]
                                p-4 text-left">
                        <div class="flex items-start gap-3">
                            <div class="shrink-0 w-8 h-8 rounded-lg
                                        bg-blue-100 dark:bg-blue-500/20
                                        text-blue-700 dark:text-blue-300
                                        flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                                     stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 leading-snug">
                                    Create a tourist account first
                                </p>
                                <p class="mt-1 text-xs text-blue-800/85 dark:text-blue-300/85 leading-relaxed">
                                    You'll need one to register your business. It takes less than a minute.
                                </p>
                                <a href="{{ $this->businessSignupUrl }}"
                                   wire:navigate
                                   class="mt-3 inline-flex items-center gap-1.5 h-9 px-3.5 rounded-lg
                                          bg-primary-600 hover:bg-primary-700
                                          text-xs font-semibold text-white
                                          shadow-sm shadow-primary-600/20
                                          transition-all duration-200 active:scale-95
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-blue-950">
                                    Create a Tourist Account
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 shrink-0" fill="none"
                                         stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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