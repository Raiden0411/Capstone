{{-- resources/views/public/auth/⚡register.blade.php --}}
<?php

use App\Mail\WelcomeNewUser;
use App\Models\BusinessApplication;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts.auth')]
#[Title('Create Your Account')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    public string $account_type = 'tourist';

    public string $name                  = '';
    public string $email                 = '';
    public string $password              = '';
    public string $password_confirmation = '';
    public string $phone                 = '';

    public $avatar = null;
    public ?string $avatar_path = null;

    public bool $privacy_accepted = false;

    public ?string $redirectTo = null;

    public function mount(): void
    {
        $this->redirectTo = $this->sanitizeRedirect(request()->query('redirect'));

        if (
            ($this->redirectTo && str_contains($this->redirectTo, 'register-business'))
            || request()->query('type') === 'business_owner'
        ) {
            $this->account_type = 'business_owner';
        }

        if (Auth::check()) {
            $this->redirect(route('home'), navigate: true);
            return;
        }
    }

    protected function rules(): array
    {
        return [
            'account_type'      => ['required', Rule::in(['tourist', 'business_owner'])],
            'name'              => ['required', 'string', 'min:3', 'max:255'],
            'email'             => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password'          => ['required', 'string', 'min:8', 'confirmed'],
            // Phone is REQUIRED — every booking sends this value to
            // PayMongo. See ⚡create-booking.blade.php.
            'phone'             => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'avatar'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'privacy_accepted'  => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone.required'             => 'A mobile number is required.',
            'phone.regex'                => 'Use a valid PH number: 09xxxxxxxxx or +639xxxxxxxxx.',
            'account_type.required'      => 'Please choose how you want to use the platform.',
            'account_type.in'            => 'Please choose a valid account type.',
            'avatar.image'               => 'Your photo must be a JPG, PNG, or WEBP image.',
            'avatar.mimes'               => 'Your photo must be a JPG, PNG, or WEBP image.',
            'avatar.max'                 => 'Your photo must be 5 MB or smaller.',
            'privacy_accepted.accepted'  => 'You must agree to the Privacy Policy to create an account.',
        ];
    }

    public function updatedAvatar(): void
    {
        if (!$this->avatar) {
            return;
        }

        $this->validateOnly('avatar');

        try {
            $storedPath = $this->storeImage($this->avatar, 'avatars', 'public', 'avatars');

            if (!$storedPath) {
                throw new \RuntimeException('Storage returned no path.');
            }

            if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
                Storage::disk('public')->delete($this->avatar_path);
            }

            $this->avatar_path = $storedPath;
        } catch (\Throwable $e) {
            Log::warning('Avatar upload failed', [
                'error' => $e->getMessage(),
                'type'  => get_class($e),
            ]);
            $this->addError('avatar', 'Could not upload your photo. Please try again.');
        } finally {
            $this->avatar = null;
        }
    }

    public function removeAvatar(): void
    {
        if ($this->avatar_path && Storage::disk('public')->exists($this->avatar_path)) {
            Storage::disk('public')->delete($this->avatar_path);
        }

        $this->avatar      = null;
        $this->avatar_path = null;
    }

    public function register()
    {
        $this->validate();

        $email = strtolower(trim($this->email));
        if (User::query()->where('email', $email)->exists()) {
            $this->addError('email', 'This email is already registered. Please sign in instead.');
            return null;
        }

        $storedAvatarPath = $this->avatar_path;

        try {
            $user = DB::transaction(function () use ($email, $storedAvatarPath) {
                $user = User::create([
                    'name'                   => trim($this->name),
                    'email'                  => $email,
                    'password'               => Hash::make($this->password),
                    'phone'                  => trim($this->phone),
                    'avatar'                 => $storedAvatarPath,
                    'tenant_id'              => null,
                    'is_active'              => true,
                    'privacy_accepted_at'    => now(),
                    'privacy_policy_version' => config('legal.privacy_policy_version'),
                ]);

                $user->syncRoles(['tourist']);

                if ($this->account_type === 'business_owner') {
                    BusinessApplication::create([
                        'user_id' => $user->id,
                        'status'  => BusinessApplication::STATUS_DRAFT,
                    ]);
                }

                return $user;
            });
        } catch (\Throwable $e) {
            if ($storedAvatarPath && Storage::disk('public')->exists($storedAvatarPath)) {
                Storage::disk('public')->delete($storedAvatarPath);
            }

            Log::error('Registration transaction failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            $this->addError('email', 'Something went wrong creating your account. Please try again.');
            return null;
        }

        auth()->login($user);
        session()->regenerate();

        $this->safeMail(function () use ($user) {
            Mail::to($user->email)->send(
                new WelcomeNewUser(
                    userName:        $user->name,
                    userEmail:       $user->email,
                    isBusinessOwner: $this->account_type === 'business_owner',
                )
            );
        }, 'welcome-new-user', $user->id);

        if ($this->account_type === 'business_owner') {
            $application = $user->businessApplications()
                ->whereIn('status', [
                    BusinessApplication::STATUS_DRAFT,
                    BusinessApplication::STATUS_NEEDS_REVISION,
                ])
                ->latest()
                ->first();

            if ($application) {
                return redirect()
                    ->route('register_business.edit', ['application' => $application->id])
                    ->with('message', 'Account created! Let\'s set up your business profile.');
            }

            return redirect()
                ->route('register_business')
                ->with('message', 'Account created! Start your business registration.');
        }

        $message = 'Account created successfully! Welcome to Victorias Tourism.';

        if ($this->redirectTo) {
            return redirect()->to($this->redirectTo)->with('message', $message);
        }

        return redirect()->route('home')->with('message', $message);
    }

    protected function safeMail(callable $callback, string $context, int $userId): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            Log::warning('Mail dispatch failed', [
                'context' => $context,
                'user_id' => $userId,
                'error'   => $e->getMessage(),
                'type'    => get_class($e),
            ]);
        }
    }

    protected function sanitizeRedirect(?string $url): ?string
    {
        if (!$url || !str_starts_with($url, '/')) {
            return null;
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '/\\')) {
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
                'logo' => '/storage/' . ltrim((string) $tenant->logo, '/'),
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

                <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight
                           leading-[1.06] text-white max-w-3xl
                           [text-wrap:balance]">
                    Begin your<br class="hidden sm:block">
                    Victorias City story
                </h2>

                <p class="max-w-xl mt-4 sm:mt-5 text-sm sm:text-base leading-relaxed text-white/75">
                    Create a free account to book stays, save your favorite spots, and unlock local events across the city.
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
                                   wire:key="register-feat-{{ $ft['slug'] }}"
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

    <div class="auth-form-panel flex items-start justify-center w-full min-w-0
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
                Get started
            </p>

            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2 break-words leading-tight">
                Create your account
            </h1>

            <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
                Takes less than a minute. No credit card required.
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

            <form wire:submit="register" class="space-y-5"
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
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        I'm signing up as <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative cursor-pointer group
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                            <input type="radio" wire:model.live="account_type" value="tourist" name="account_type" class="peer sr-only">
                            <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 p-4 transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:shadow-sm dark:peer-checked:bg-primary-500/10 dark:peer-checked:border-primary-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0 w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Tourist</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                            Book stays, join events, and explore Victorias City.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>

                        <label class="relative cursor-pointer group
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                            <input type="radio" wire:model.live="account_type" value="business_owner" name="account_type" class="peer sr-only">
                            <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 p-4 transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:shadow-sm dark:peer-checked:bg-primary-500/10 dark:peer-checked:border-primary-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0 w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-500/15 text-blue-700 dark:text-blue-400 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Business Owner</p>
                                        <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 leading-snug">
                                            List your property, service, or tourist spot on the platform.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    @error('account_type')
                        <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
                    @enderror

                    @if($account_type === 'business_owner')
                        <div class="mt-3 flex items-start gap-2.5 rounded-xl border border-blue-200/70 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/[0.06] px-3 py-2.5 text-xs text-blue-800 dark:text-blue-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="leading-relaxed">
                                After you create your account, we'll take you straight to the business registration form. You'll need your DTI/SEC registration, TIN, and Mayor's Permit on hand.
                            </span>
                        </div>
                    @endif
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Full Name <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                        </svg>
                        <input id="name" type="text" wire:model="name" autofocus autocomplete="name"
                               placeholder="Juan Dela Cruz"
                               class="input pl-10 @error('name') border-rose-400/60 @enderror">
                    </div>
                    @error('name') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Profile Photo
                        <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>

                    <div class="mt-2 flex items-center gap-4">
                        <div class="shrink-0 relative">
                            @if($avatar_path)
                                <img src="{{ '/storage/' . ltrim($avatar_path, '/') }}"
                                     alt="Profile preview"
                                     width="64" height="64" decoding="async"
                                     class="w-16 h-16 rounded-full object-cover border-2 border-primary-200 dark:border-primary-500/40 shadow-sm">
                            @else
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 border-2 border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 dark:text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                    </svg>
                                </div>
                            @endif

                            <div wire:loading wire:target="avatar"
                                 class="absolute inset-0 rounded-full bg-white/85 dark:bg-gray-900/85 backdrop-blur-sm flex items-center justify-center">
                                <svg class="animate-spin w-5 h-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <input type="file"
                                   id="avatar"
                                   wire:model="avatar"
                                   wire:loading.attr="disabled"
                                   wire:target="avatar"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="block w-full text-xs text-gray-500 dark:text-gray-400
                                          file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0
                                          file:text-xs file:font-semibold file:cursor-pointer file:transition-colors
                                          file:bg-primary-50 file:text-primary-700
                                          hover:file:bg-primary-100
                                          dark:file:bg-primary-500/10 dark:file:text-primary-300
                                          dark:hover:file:bg-primary-500/20
                                          disabled:opacity-50 disabled:cursor-not-allowed
                                          [touch-action:manipulation]
                                          focus:outline-none">

                            <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed">
                                JPG, PNG, or WEBP · max 5 MB · auto-compressed
                            </p>

                            @if($avatar_path)
                                <button type="button"
                                        wire:click="removeAvatar"
                                        class="relative mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 dark:text-rose-400 hover:underline transition-all duration-200 active:scale-95
                                               before:absolute before:content-[''] before:-inset-1.5 before:rounded
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded px-1 -mx-1 py-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Remove photo
                                </button>
                            @endif
                        </div>
                    </div>

                    @error('avatar') <p class="mt-2 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

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
                        <input id="email" type="email" wire:model.blur="email"
                               autocomplete="email" inputmode="email"
                               placeholder="you@example.com"
                               class="input pl-10 @error('email') border-rose-400/60 @enderror">
                    </div>
                    @error('email') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Mobile Number <span class="text-rose-500" aria-hidden="true">*</span>
                    </label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/>
                        </svg>
                        <input id="phone"
                               type="tel"
                               inputmode="numeric"
                               wire:model="phone"
                               autocomplete="tel"
                               placeholder="09xxxxxxxxx"
                               maxlength="13"
                               x-on:input="
                                   const cleaned = $event.target.value.replace(/[^0-9+]/g, '');
                                   if (cleaned !== $event.target.value) {
                                       $event.target.value = cleaned;
                                       $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                                   }
                               "
                               class="input pl-10 @error('phone') border-rose-400/60 @enderror">
                    </div>
                    <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed">
                        Required — sent to PayMongo with every booking.
                    </p>
                    @error('phone') <p class="mt-1.5 text-xs text-rose-500 break-words">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Password <span class="text-rose-500" aria-hidden="true">*</span>
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
                        Confirm Password <span class="text-rose-500" aria-hidden="true">*</span>
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
                               placeholder="Re-enter your password"
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

                <div class="rounded-xl border-2 {{ $errors->has('privacy_accepted') ? 'border-rose-300 dark:border-rose-500/40 bg-rose-50/50 dark:bg-rose-500/[0.04]' : 'border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40' }} p-4 transition-colors">
                    <label for="privacy_accepted" class="flex items-start gap-3 cursor-pointer
                                                        [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <input type="checkbox"
                               id="privacy_accepted"
                               wire:model.live="privacy_accepted"
                               class="mt-0.5 shrink-0 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 focus:ring-offset-0 bg-white dark:bg-gray-700 transition">
                        <span class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                            I have read and agree to the
                            <a href="{{ route('privacy.policy') }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="font-semibold text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                Privacy Policy
                            </a>
                            and consent to the processing of my personal data as described in it.
                            <span class="text-rose-500 font-bold" aria-hidden="true">*</span>
                        </span>
                    </label>

                    @error('privacy_accepted')
                        <p class="mt-2 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="register"
                        @disabled(!$privacy_accepted)
                        title="{{ $privacy_accepted ? '' : 'You must agree to the Privacy Policy to continue' }}"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2
                               text-sm font-semibold text-white
                               bg-primary-600 hover:bg-primary-700
                               rounded-xl shadow-sm shadow-primary-600/20
                               transition-all duration-200
                               disabled:opacity-40 disabled:cursor-not-allowed
                               active:scale-[0.98]
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span wire:loading.remove wire:target="register">
                        @if($account_type === 'business_owner')
                            Create Account &amp; Continue
                        @else
                            Create Account
                        @endif
                    </span>
                    <span wire:loading wire:target="register" class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Creating…
                    </span>
                </button>

                @if(!$privacy_accepted)
                    <p class="-mt-3 text-[11px] text-center text-gray-500 dark:text-gray-400">
                        Agree to the Privacy Policy above to enable this button.
                    </p>
                @endif
            </form>

            <p class="mt-8 text-sm text-center text-gray-600 dark:text-gray-400">
                Already have an account?
                <a href="{{ route('login', $redirectTo ? ['redirect' => $redirectTo] : []) }}" wire:navigate
                   class="relative text-primary-600 dark:text-primary-400 font-semibold hover:underline ml-1
                          before:absolute before:content-[''] before:-inset-2 before:rounded
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded px-1 -mx-1 py-0.5">
                    Sign in
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