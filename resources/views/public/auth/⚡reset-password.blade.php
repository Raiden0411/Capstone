{{-- resources/views/public/auth/⚡reset-password.blade.php --}}
<?php

use App\Models\SiteSetting;
use App\Models\Tenant;
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
            @keyframes authCapsIn {
                from { opacity: 0; transform: translateY(-4px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .auth-caps { animation: authCapsIn .18s ease-out; }
            @media (prefers-reduced-motion: reduce) {
                .auth-chip, .auth-caps { animation: none; }
            }
        </style>
    @endonce
@endpush

<div class="min-h-screen flex flex-col md:flex-row bg-white dark:bg-gray-900">

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
                    Choose a new password
                </h2>

                <p class="max-w-xl mt-4 sm:mt-5 text-sm sm:text-base leading-relaxed text-white/75">
                    Pick something strong and unique. Minimum 8 characters — a mix of letters, numbers, and symbols is best.
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
                                   wire:key="reset-feat-{{ $ft['slug'] }}"
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

    <div class="auth-form-panel flex items-start md:items-center justify-center w-full min-w-0
                px-5 sm:px-8 md:px-10 lg:px-16 py-10 md:py-12
                bg-white dark:bg-gray-900 md:w-1/2">
        <div class="w-full max-w-md min-w-0">

            <a href="{{ route('login') }}" wire:navigate
               class="relative inline-flex items-center gap-1.5 text-sm font-medium
                      text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400
                      transition-colors mb-8 -mx-1 px-1 py-1 rounded
                      before:absolute before:content-[''] before:-inset-2 before:rounded
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
                         class="w-10 h-10 rounded-full object-contain ring-1 ring-black/5 shadow-sm dark:ring-white/10 shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full bg-primary-600 flex items-center justify-center text-white shrink-0 font-bold text-lg ring-1 ring-black/5 shadow-sm dark:ring-white/10"
                         aria-hidden="true">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $this->siteName }}</span>
            </div>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2 break-words leading-tight tracking-tight">
                Set a new password
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                Enter your new password below to complete the reset.
            </p>

            @if($errorMessage)
                <div role="alert" aria-live="polite"
                     class="mb-5 flex items-start gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 rounded-xl p-3.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm text-rose-700 dark:text-rose-300 min-w-0">
                        <p class="break-words">{{ $errorMessage }}</p>
                        <a href="{{ route('password.request') }}" wire:navigate
                           class="relative mt-1 inline-block font-semibold underline underline-offset-2 hover:text-rose-800 dark:hover:text-rose-200 transition-all duration-200 active:scale-95
                                  before:absolute before:content-[''] before:-inset-1 before:rounded
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded">
                            Request a new reset link →
                        </a>
                    </div>
                </div>
            @endif

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

            <form wire:submit="resetPassword" class="space-y-5"
                  x-data="{
                      showPassword: false,
                      showConfirmPassword: false,
                      pw: '',
                      pwConfirm: '',
                      capsLockPassword: false,
                      capsLockConfirm: false,

                      get strengthScore() {
                          const p = this.pw;
                          if (!p) return 0;
                          let score = 0;
                          if (p.length >= 8)  score++;
                          if (p.length >= 12) score++;
                          if (/[a-z]/.test(p)) score++;
                          if (/[A-Z]/.test(p)) score++;
                          if (/[0-9]/.test(p)) score++;
                          if (/[^a-zA-Z0-9]/.test(p)) score++;
                          if (score <= 2) return 1;
                          if (score <= 4) return 2;
                          if (score <= 5) return 3;
                          return 4;
                      },
                      get strengthLabel() {
                          return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.strengthScore];
                      },
                      get strengthTextColor() {
                          return [
                              'text-gray-400',
                              'text-rose-600 dark:text-rose-400',
                              'text-amber-600 dark:text-amber-400',
                              'text-blue-600 dark:text-blue-400',
                              'text-emerald-600 dark:text-emerald-400',
                          ][this.strengthScore];
                      },
                      get strengthBarColor() {
                          return [
                              'bg-gray-200 dark:bg-gray-700',
                              'bg-rose-500',
                              'bg-amber-500',
                              'bg-blue-500',
                              'bg-emerald-500',
                          ][this.strengthScore];
                      },
                      get pwMatches() {
                          return this.pwConfirm.length > 0 && this.pw === this.pwConfirm;
                      },
                      get pwMismatch() {
                          return this.pwConfirm.length > 0 && this.pw !== this.pwConfirm;
                      },
                  }">

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
                               autocomplete="email" placeholder="example@email.com"
                               class="input pl-10 @error('email') border-rose-400/60 @enderror">
                    </div>
                    @error('email') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        New Password <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                        </svg>
                        <input :type="showPassword ? 'text' : 'password'"
                               id="password" wire:model="password"
                               autocomplete="new-password" minlength="8"
                               placeholder="At least 8 characters"
                               @input="pw = $event.target.value"
                               @keyup="capsLockPassword = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @keydown="capsLockPassword = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @blur="capsLockPassword = false"
                               class="input pl-10 pr-11 @error('password') border-rose-400/60 @enderror">

                        <button type="button" @click="showPassword = !showPassword"
                                class="absolute right-1 top-1/2 -translate-y-1/2
                                       inline-flex items-center justify-center w-8 h-8 rounded-lg
                                       text-gray-400 hover:text-gray-700 dark:hover:text-gray-200
                                       hover:bg-gray-100 dark:hover:bg-gray-700/60
                                       transition-colors
                                       before:absolute before:content-[''] before:-inset-1.5 before:rounded-lg
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword ? 'true' : 'false'">
                            <svg xmlns="http://www.w3.org/2000/svg" x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" x-show="showPassword" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <div x-cloak x-show="pw.length > 0" class="mt-2">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                Password strength
                            </span>
                            <span class="text-[11px] font-semibold tabular-nums" :class="strengthTextColor" x-text="strengthLabel"></span>
                        </div>
                        <div class="flex gap-1" aria-hidden="true">
                            <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                 :class="strengthScore >= 1 ? strengthBarColor : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                 :class="strengthScore >= 2 ? strengthBarColor : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                 :class="strengthScore >= 3 ? strengthBarColor : 'bg-gray-200 dark:bg-gray-700'"></div>
                            <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                                 :class="strengthScore >= 4 ? strengthBarColor : 'bg-gray-200 dark:bg-gray-700'"></div>
                        </div>
                    </div>

                    <p x-cloak
                       x-show="capsLockPassword"
                       class="auth-caps mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                       role="status"
                       aria-live="polite">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Caps Lock is on
                    </p>

                    @error('password') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Confirm New Password <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751A11.959 11.959 0 0112 2.714z"/>
                        </svg>
                        <input :type="showConfirmPassword ? 'text' : 'password'"
                               id="password_confirmation" wire:model="password_confirmation"
                               autocomplete="new-password" minlength="8"
                               placeholder="Re-enter your new password"
                               @input="pwConfirm = $event.target.value"
                               @keyup="capsLockConfirm = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @keydown="capsLockConfirm = $event.getModifierState && $event.getModifierState('CapsLock')"
                               @blur="capsLockConfirm = false"
                               class="input pl-10 pr-11 @error('password_confirmation') border-rose-400/60 @enderror">

                        <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute right-1 top-1/2 -translate-y-1/2
                                       inline-flex items-center justify-center w-8 h-8 rounded-lg
                                       text-gray-400 hover:text-gray-700 dark:hover:text-gray-200
                                       hover:bg-gray-100 dark:hover:bg-gray-700/60
                                       transition-colors
                                       before:absolute before:content-[''] before:-inset-1.5 before:rounded-lg
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                :aria-label="showConfirmPassword ? 'Hide confirmation password' : 'Show confirmation password'"
                                :aria-pressed="showConfirmPassword ? 'true' : 'false'">
                            <svg xmlns="http://www.w3.org/2000/svg" x-show="!showConfirmPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg xmlns="http://www.w3.org/2000/svg" x-show="showConfirmPassword" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>

                    <p x-cloak
                       x-show="pwConfirm.length > 0"
                       class="mt-1.5 inline-flex items-center gap-1.5 text-xs"
                       :class="pwMatches ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                       role="status"
                       aria-live="polite">
                        <template x-if="pwMatches">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/>
                                <circle cx="12" cy="12" r="9"/>
                            </svg>
                        </template>
                        <template x-if="pwMismatch">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-6 6m0-6l6 6"/>
                                <circle cx="12" cy="12" r="9"/>
                            </svg>
                        </template>
                        <span x-text="pwMatches ? 'Passwords match' : 'Passwords don\'t match'"></span>
                    </p>

                    <p x-cloak
                       x-show="capsLockConfirm"
                       class="auth-caps mt-1.5 inline-flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400"
                       role="status"
                       aria-live="polite">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Caps Lock is on
                    </p>

                    @error('password_confirmation') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="resetPassword"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm shadow-primary-600/20
                               disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
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

            <p class="mt-8 text-sm text-center text-gray-600 dark:text-gray-400">
                Remembered your password?
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