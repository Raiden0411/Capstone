{{-- resources/views/public/auth/⚡register.blade.php --}}
<?php

use App\Mail\WelcomeNewUser;
use App\Models\BusinessApplication;
use App\Models\SiteSetting;
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

    /**
     * Avatar upload.
     *
     * `$avatar` is the Livewire TemporaryUploadedFile on selection. We
     * store it to disk immediately in updatedAvatar() so the preview uses
     * a real asset('storage/...') URL — temporaryUrl() breaks behind the
     * EnvKit proxy. `$avatar_path` is what we persist on the User row.
     *
     * Requires both the WithFileUploads trait (Livewire needs it to accept
     * wire:model on a file input at all) and HandlesImageUploads (which
     * routes the store through ImageCompressionService).
     */
    public $avatar = null;
    public ?string $avatar_path = null;

    /**
     * GDPR: explicit consent to the privacy policy.
     * Not persisted on this SFC — used only to gate the register() action.
     * The acceptance timestamp + policy version ARE persisted on the User
     * row so we can demonstrate consent per Article 7(1).
     */
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
            'phone'             => ['nullable', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'avatar'            => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'privacy_accepted'  => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'phone.regex'                => 'Use a valid PH number: 09xxxxxxxxx or +639xxxxxxxxx.',
            'account_type.required'      => 'Please choose how you want to use the platform.',
            'account_type.in'            => 'Please choose a valid account type.',
            'avatar.image'               => 'Your photo must be a JPG, PNG, or WEBP image.',
            'avatar.mimes'               => 'Your photo must be a JPG, PNG, or WEBP image.',
            'avatar.max'                 => 'Your photo must be 5 MB or smaller.',
            'privacy_accepted.accepted'  => 'You must agree to the Privacy Policy to create an account.',
        ];
    }

    /**
     * Store the avatar immediately on select. The HandlesImageUploads
     * trait routes the store through ImageCompressionService — the
     * 'avatars' context caps it at 512 KB / 800×800 and applies
     * auto-orientation + EXIF stripping.
     */
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

            // If a previous unsaved upload exists, delete it now so we
            // don't leave orphans on disk.
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
            // Clear the temporary file — the DB write uses $avatar_path.
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
                    'phone'                  => $this->phone !== '' ? $this->phone : null,
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
            // DB write failed → clean up the avatar we already stored.
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

        // Welcome email — AFTER commit, wrapped so a broken SMTP never
        // rolls back or blocks the redirect.
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

    /**
     * Wrap a mail dispatch so a broken SMTP server never bubbles up
     * and rolls back an already-committed state change.
     */
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
    <div class="relative w-full md:w-3/5 h-[220px] md:h-screen md:sticky md:top-0 order-1 md:order-1">
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
                Your journey to the heart of the<br />wilderness starts here
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Discover the unmapped ecotrails, pristine waterfalls, and rich history of Victorias City.
                Let us show you a side of the world you've never seen.
            </p>
        </div>
    </div>

    {{-- Right Side: Registration Form --}}
    <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
        <div class="w-full max-w-md">

            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-all duration-200 active:scale-95 mb-6 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Home
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
                Create your account
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Takes less than a minute. No credit card required.
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

            <form wire:submit="register" class="space-y-5"
                  x-data="{ showPassword: false, showConfirmPassword: false }">

                {{-- Account type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        I'm signing up as… <span class="text-rose-500">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="relative cursor-pointer group">
                            <input type="radio" wire:model.live="account_type" value="tourist" name="account_type" class="peer sr-only">
                            <div class="rounded-xl border-2 border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/60 p-4 transition-all duration-200 hover:border-gray-300 dark:hover:border-gray-600 peer-checked:border-primary-500 peer-checked:bg-primary-50 peer-checked:shadow-sm dark:peer-checked:bg-primary-500/10 dark:peer-checked:border-primary-500 peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/50">
                                <div class="flex items-start gap-3">
                                    <div class="shrink-0 w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
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

                        <label class="relative cursor-pointer group">
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

                {{-- Full Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <div class="mt-1.5">
                        <input id="name" type="text" wire:model="name" autofocus autocomplete="name"
                               placeholder="Juan Dela Cruz"
                               class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('name') border-rose-400/60 @enderror">
                    </div>
                    @error('name') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- Profile Photo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Profile Photo
                        <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>

                    <div class="mt-2 flex items-center gap-4">
                        {{-- Preview --}}
                        <div class="shrink-0 relative">
                            @if($avatar_path)
                                <img src="{{ asset('storage/' . $avatar_path) }}"
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

                            {{-- Upload-in-progress overlay --}}
                            <div wire:loading wire:target="avatar"
                                 class="absolute inset-0 rounded-full bg-white/85 dark:bg-gray-900/85 backdrop-blur-sm flex items-center justify-center">
                                <svg class="animate-spin w-5 h-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- File input + remove --}}
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
                                          focus:outline-none">

                            <p class="mt-1.5 text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed">
                                JPG, PNG, or WEBP · max 5 MB · auto-compressed
                            </p>

                            @if($avatar_path)
                                <button type="button"
                                        wire:click="removeAvatar"
                                        class="mt-1.5 inline-flex items-center gap-1 text-[11px] font-medium text-rose-600 dark:text-rose-400 hover:underline transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 rounded">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Remove photo
                                </button>
                            @endif
                        </div>
                    </div>

                    @error('avatar') <p class="mt-2 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <div class="mt-1.5">
                        <input id="email" type="email" wire:model.blur="email" autocomplete="email"
                               placeholder="example@email.com"
                               class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('email') border-rose-400/60 @enderror">
                    </div>
                    @error('email') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Phone <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>
                    <div class="mt-1.5">
                        <input id="phone" type="text" wire:model="phone" autocomplete="tel"
                               placeholder="09123456789"
                               class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('phone') border-rose-400/60 @enderror">
                    </div>
                    @error('phone') <p class="mt-1 text-xs text-rose-500">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Password <span class="text-rose-500">*</span>
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

                {{-- Confirm Password --}}
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Confirm Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative mt-1.5">
                        <input :type="showConfirmPassword ? 'text' : 'password'"
                               id="password_confirmation" wire:model="password_confirmation"
                               autocomplete="new-password" minlength="8"
                               placeholder="Re-enter your password"
                               class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500 @error('password_confirmation') border-rose-400/60 @enderror">
                        {{-- Rule 69: :class toggling. --}}
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-full"
                                tabindex="-1" aria-label="Toggle confirm password visibility">
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

                {{-- ═══════════════════════════════════════════════════════
                     GDPR CONSENT — required. Blocks submit when unchecked.
                     ═══════════════════════════════════════════════════════ --}}
                <div class="rounded-xl border-2 {{ $errors->has('privacy_accepted') ? 'border-rose-300 dark:border-rose-500/40 bg-rose-50/50 dark:bg-rose-500/[0.04]' : 'border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40' }} p-4 transition-colors">
                    <label for="privacy_accepted" class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox"
                               id="privacy_accepted"
                               wire:model.live="privacy_accepted"
                               class="mt-0.5 shrink-0 w-4 h-4 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-gray-700 transition">
                        <span class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                            I have read and agree to the
                            <a href="{{ route('privacy.policy') }}"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="font-semibold text-primary-600 dark:text-primary-400 underline underline-offset-2 hover:text-primary-700 dark:hover:text-primary-300">
                                Privacy Policy
                            </a>
                            and consent to the processing of my personal data as described in it.
                            <span class="text-rose-500 font-bold">*</span>
                        </span>
                    </label>

                    @error('privacy_accepted')
                        <p class="mt-2 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="register"
                        @disabled(!$privacy_accepted)
                        title="{{ $privacy_accepted ? '' : 'You must agree to the Privacy Policy to continue' }}"
                        class="w-full inline-flex items-center justify-center gap-2 h-11 mt-2 text-sm font-semibold text-white transition-all duration-200 bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm
                               disabled:opacity-50 disabled:cursor-not-allowed active:scale-95
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

                <p class="text-[11px] text-center text-gray-400 dark:text-gray-500 leading-relaxed">
                    By creating an account you also agree to our
                    <a href="{{ route('privacy.policy') }}" target="_blank" rel="noopener noreferrer"
                       class="underline underline-offset-2 hover:text-gray-600 dark:hover:text-gray-300">
                        Privacy Policy
                    </a>.
                </p>
            </form>

            <p class="mt-8 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                Already Have An Account?
                <a href="{{ route('login', $redirectTo ? ['redirect' => $redirectTo] : []) }}" wire:navigate
                   class="text-primary-600 hover:underline ml-1 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Sign In
                </a>
            </p>

            <p class="mt-2 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                Already have an account and want to list a spot?
                <a href="{{ route('register_business') }}" wire:navigate
                   class="text-primary-600 hover:underline ml-1 transition-all duration-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    Register your business
                </a>
            </p>

        </div>
    </div>

</div>