{{-- resources/views/superadmin/pages/profile/⚡edit-profile.blade.php --}}
<?php

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ReverseGeocodeService;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('superadmin.layouts.app')]
#[Title('My Profile')]
class extends Component {

    use WithFileUploads;
    use HandlesImageUploads;

    public string $activeTab = 'account';

    public string $name  = '';
    public string $email = '';
    public $avatar = null;

    public string $current_password          = '';
    public string $new_password              = '';
    public string $new_password_confirmation = '';

    public $siteLogo;
    public string $siteName = '';

    public ?float $siteLatitude  = null;
    public ?float $siteLongitude = null;
    public string $siteAddress   = '';
    public string $siteBarangay  = '';
    public string $siteCity      = '';
    public string $siteProvince  = '';

    public const DEFAULT_LAT = 10.900736693923502;
    public const DEFAULT_LNG = 123.07391289720677;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $tab = (string) request()->query('tab', 'account');
        if (! in_array($tab, ['account', 'branding', 'location'], true)) {
            $tab = 'account';
        }
        $this->activeTab = $tab;

        $this->name  = (string) Auth::user()->name;
        $this->email = (string) Auth::user()->email;

        $this->siteName = (string) SiteSetting::getValue('site_name', 'Tourism Management');

        $siteLoc = SiteSetting::getValue('site_location', null);
        if (is_array($siteLoc) && isset($siteLoc['lat'], $siteLoc['lng'])) {
            $this->siteLatitude  = (float) $siteLoc['lat'];
            $this->siteLongitude = (float) $siteLoc['lng'];
            $this->siteAddress   = (string) ($siteLoc['address'] ?? '');
            $this->siteBarangay  = (string) ($siteLoc['barangay'] ?? '');
            $this->siteCity      = (string) ($siteLoc['city'] ?? '');
            $this->siteProvince  = (string) ($siteLoc['province'] ?? '');
        }
    }

    public function hydrate(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    /**
     * SPA-safe full-page refresh, tab-preserving. Component actions only
     * re-render the component's template, not the surrounding layout —
     * a save that touches data the layout reads (site_name, site_logo,
     * user name, avatar) needs a real navigation to refresh the header.
     */
    private function refreshPage(string $tab): void
    {
        $this->redirectRoute('superadmin.profile', ['tab' => $tab], navigate: true);
    }

    #[Computed]
    public function viewer(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }

    #[Computed]
    public function currentRoleLabel(): ?string
    {
        $role = $this->viewer?->roles->first();

        return $role
            ? ucwords(str_replace(['-', '_'], ' ', $role->name))
            : null;
    }

    // Rule J: relative /storage path, versioned by mtime.
    #[Computed]
    public function avatarUrl(): ?string
    {
        $path = $this->viewer?->avatar;

        if (! $path) {
            return null;
        }

        $full = public_path('storage/' . ltrim($path, '/'));
        if (! file_exists($full)) {
            return null;
        }

        return '/storage/' . ltrim($path, '/') . '?v=' . filemtime($full);
    }

    // Rule J: relative /storage path.
    #[Computed]
    public function currentSiteLogoUrl(): ?string
    {
        $path = SiteSetting::getValue('site_logo');

        return $path ? '/storage/' . ltrim($path, '/') : null;
    }

    #[Computed]
    public function hasSiteLocation(): bool
    {
        return $this->siteLatitude !== null && $this->siteLongitude !== null;
    }

    /** @return array{0: float, 1: float} */
    #[Computed]
    public function siteMapCenter(): array
    {
        if ($this->hasSiteLocation) {
            return [(float) $this->siteLongitude, (float) $this->siteLatitude];
        }

        return [self::DEFAULT_LNG, self::DEFAULT_LAT];
    }

    #[Computed]
    public function siteMapZoom(): int
    {
        return $this->hasSiteLocation ? 16 : 12;
    }

    protected function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(Auth::id()),
            ],
            'current_password' => ['required_with:new_password', 'string'],
            'new_password'     => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function messages(): array
    {
        return [
            'current_password.required_with' => 'Enter your current password to change it.',
            'new_password.min'               => 'The new password must be at least 8 characters.',
            'new_password.confirmed'         => 'The new password confirmation does not match.',
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['name', 'email', 'siteName'], true)) {
            $this->$property = trim((string) $this->$property);
        }
    }

    public function update()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->name  = trim($this->name);
        $this->email = trim($this->email);

        $this->validate();

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        if ($this->new_password !== '') {
            if (! Hash::check($this->current_password, Auth::user()->password)) {
                $this->addError('current_password', 'The current password is incorrect.');
                return null;
            }

            $data['password'] = Hash::make($this->new_password);
        }

        Auth::user()->update($data);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        unset($this->viewer, $this->currentRoleLabel);

        session()->flash('message', 'Profile updated successfully.');

        $this->refreshPage('account');
    }

    public function updatedAvatar(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        if (! $this->avatar) {
            return;
        }

        try {
            $this->validate([
                'avatar' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'avatar.max'   => 'Photo must be 5 MB or smaller.',
                'avatar.image' => 'Photo must be a JPG, PNG, or WEBP image.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->avatar = null;
            $first = collect($e->errors())->flatten()->first();
            session()->flash('error', $first ?: 'Invalid photo.');
            return;
        }

        $newPath = null;

        try {
            $newPath = $this->storeImage($this->avatar, 'avatars', 'public', 'avatars');

            if (! $newPath) {
                throw new \RuntimeException('Storage returned no path.');
            }

            $oldPath = Auth::user()->avatar;

            Auth::user()->update(['avatar' => $newPath]);

            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            unset($this->viewer, $this->avatarUrl);

            $this->avatar = null;

            session()->flash('message', 'Profile photo updated.');

            $this->refreshPage('account');
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            $this->avatar = null;

            Log::error('Superadmin avatar upload failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);

            session()->flash('error', 'Could not upload your photo. Please try again.');
        }
    }

    public function removeAvatar(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $oldPath = Auth::user()->avatar;

        if (! $oldPath) {
            session()->flash('error', 'There is no photo to remove.');
            return;
        }

        try {
            Auth::user()->update(['avatar' => null]);

            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            unset($this->viewer, $this->avatarUrl);

            session()->flash('message', 'Profile photo removed.');

            $this->refreshPage('account');
        } catch (\Throwable $e) {
            Log::error('Superadmin avatar removal failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);

            session()->flash('error', 'Could not remove your photo. Please try again.');
        }
    }

    public function saveBranding()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->siteName = trim($this->siteName);

        $this->validate([
            'siteName' => ['required', 'string', 'max:255'],
            'siteLogo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldPath = SiteSetting::getValue('site_logo');
        $newPath = null;

        try {
            if ($this->siteLogo) {
                $newPath = $this->storeImage($this->siteLogo, 'site', 'public', 'tenant-logo');

                if (! $newPath) {
                    throw new \RuntimeException('Failed to store the uploaded logo.');
                }
            }

            DB::transaction(function () use ($newPath): void {
                SiteSetting::setValue('site_name', $this->siteName);

                if ($newPath !== null) {
                    SiteSetting::setValue('site_logo', $newPath);
                }
            });
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            Log::error('Site branding update failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
            ]);

            session()->flash('error', 'Failed to update site branding. Please try again.');
            return null;
        }

        if ($newPath && $oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->siteLogo = null;

        unset($this->currentSiteLogoUrl);

        $this->dispatch('site-logo-cleared');

        session()->flash('message', 'Site branding updated successfully.');

        $this->refreshPage('branding');
    }

    public function removeLogo()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $oldPath = SiteSetting::getValue('site_logo');

        if (! $oldPath) {
            session()->flash('error', 'There is no logo to remove.');
            return;
        }

        try {
            SiteSetting::deleteKey('site_logo');
        } catch (\Throwable $e) {
            Log::error('Site logo removal failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to remove the logo. Please try again.');
            return;
        }

        if (Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        unset($this->currentSiteLogoUrl);

        $this->dispatch('site-logo-cleared');

        session()->flash('message', 'Site logo removed.');

        $this->refreshPage('branding');
    }

    public function setSiteLocation(float|string $lat, float|string $lng): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        $this->siteLatitude  = round($lat, 6);
        $this->siteLongitude = round($lng, 6);

        unset($this->hasSiteLocation, $this->siteMapCenter, $this->siteMapZoom);
    }

    public function resolveSiteAddress(float|string $lat, float|string $lng): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $lat = (float) $lat;
        $lng = (float) $lng;

        if (! is_finite($lat) || ! is_finite($lng)) {
            return;
        }
        if (abs($lat) > 90 || abs($lng) > 180) {
            return;
        }

        try {
            $result = app(ReverseGeocodeService::class)->reverse($lat, $lng);
        } catch (\Throwable $e) {
            Log::warning('Site location reverse geocode failed', [
                'actor_id' => Auth::id(),
                'lat'      => $lat,
                'lng'      => $lng,
                'error'    => $e->getMessage(),
            ]);
            return;
        }

        if ($result === null) {
            return;
        }

        if ($result['address'] !== '')  { $this->siteAddress  = $result['address'];  }
        if ($result['barangay'] !== '') { $this->siteBarangay = $result['barangay']; }
        if ($result['city'] !== '')     { $this->siteCity     = $result['city'];     }
        if ($result['province'] !== '') { $this->siteProvince = $result['province']; }
    }

    public function clearSiteLocation(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403);

        $this->siteLatitude  = null;
        $this->siteLongitude = null;
        $this->siteAddress   = '';
        $this->siteBarangay  = '';
        $this->siteCity      = '';
        $this->siteProvince  = '';

        $this->dispatch('map:pin-cleared');

        unset($this->hasSiteLocation, $this->siteMapCenter, $this->siteMapZoom);

        session()->flash('message', 'Location cleared. Remember to save.');
    }

    public function saveSiteLocation(): void
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->validate([
            'siteLatitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'siteLongitude' => ['nullable', 'numeric', 'between:-180,180'],
            'siteAddress'   => ['nullable', 'string', 'max:255'],
            'siteBarangay'  => ['nullable', 'string', 'max:255'],
            'siteCity'      => ['nullable', 'string', 'max:255'],
            'siteProvince'  => ['nullable', 'string', 'max:255'],
        ]);

        if (($this->siteLatitude === null) !== ($this->siteLongitude === null)) {
            $this->addError('siteLatitude', 'Both latitude and longitude must be set together, or neither.');
            return;
        }

        try {
            if ($this->siteLatitude === null) {
                SiteSetting::deleteKey('site_location');
            } else {
                SiteSetting::setValue('site_location', [
                    'lat'      => $this->siteLatitude,
                    'lng'      => $this->siteLongitude,
                    'address'  => $this->siteAddress,
                    'barangay' => $this->siteBarangay,
                    'city'     => $this->siteCity,
                    'province' => $this->siteProvince,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Site location save failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);
            session()->flash('error', 'Failed to save the site location. Please try again.');
            return;
        }

        unset($this->hasSiteLocation, $this->siteMapCenter, $this->siteMapZoom);

        session()->flash('message', 'Site location saved successfully.');

        $this->refreshPage('location');
    }
};
?>

@php
    $__initial = strtoupper(substr($this->name ?: 'SA', 0, 1));
@endphp

<div
    x-data="{ tab: '{{ $activeTab }}' }"
    class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6"
>

    @if (session()->has('message'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 transition active:scale-95 rounded" aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 transition active:scale-95 rounded" aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                My Profile
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Manage your account, site branding, and default location.
            </p>
        </div>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                  inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Dashboard
        </a>
    </div>

    <div class="card overflow-hidden">

        <div class="border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
            <nav class="flex min-w-max px-2 sm:px-4" role="tablist" aria-label="Profile sections">

                <button type="button"
                        id="tab-account"
                        role="tab"
                        aria-controls="panel-account"
                        :aria-selected="tab === 'account'"
                        @click="tab = 'account'"
                        :class="tab === 'account'
                            ? 'border-primary-600 text-primary-700 dark:text-primary-300'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'"
                        class="inline-flex items-center gap-2 px-3 sm:px-5 py-4 border-b-2 -mb-px font-medium text-sm whitespace-nowrap
                               transition-colors active:scale-[.98]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Account
                </button>

                <button type="button"
                        id="tab-branding"
                        role="tab"
                        aria-controls="panel-branding"
                        :aria-selected="tab === 'branding'"
                        @click="tab = 'branding'"
                        :class="tab === 'branding'
                            ? 'border-primary-600 text-primary-700 dark:text-primary-300'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'"
                        class="inline-flex items-center gap-2 px-3 sm:px-5 py-4 border-b-2 -mb-px font-medium text-sm whitespace-nowrap
                               transition-colors active:scale-[.98]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                    </svg>
                    Branding
                </button>

                <button type="button"
                        id="tab-location"
                        role="tab"
                        aria-controls="panel-location"
                        :aria-selected="tab === 'location'"
                        @click="tab = 'location'; $nextTick(() => window.Livewire && window.Livewire.dispatch('map:resize'))"
                        :class="tab === 'location'
                            ? 'border-primary-600 text-primary-700 dark:text-primary-300'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600'"
                        class="inline-flex items-center gap-2 px-3 sm:px-5 py-4 border-b-2 -mb-px font-medium text-sm whitespace-nowrap
                               transition-colors active:scale-[.98]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-inset">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Location
                </button>

            </nav>
        </div>

        <div
            id="panel-account"
            role="tabpanel"
            aria-labelledby="tab-account"
            :class="tab === 'account' ? 'block' : 'hidden'"
            class="p-5 sm:p-6"
        >
            <form wire:submit="update" class="space-y-8">

                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 pb-6 border-b border-gray-200 dark:border-gray-700">
                    <div class="relative shrink-0">
                        @if($this->avatarUrl)
                            <img src="{{ $this->avatarUrl }}"
                                 alt="{{ $this->name }}"
                                 width="80" height="80"
                                 loading="lazy" decoding="async"
                                 class="size-20 rounded-full object-cover ring-1 ring-black/5 shadow-sm dark:ring-white/10">
                        @else
                            <div class="grid size-20 place-items-center rounded-full bg-primary-600 text-white text-2xl font-bold ring-1 ring-black/5 shadow-sm dark:ring-white/10">
                                {{ $__initial }}
                            </div>
                        @endif

                        <div wire:loading.flex
                             wire:target="avatar"
                             class="absolute inset-0 grid place-items-center rounded-full bg-black/55 backdrop-blur-[2px] pointer-events-none"
                             aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-6 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white truncate">
                            {{ $name ?: 'Super Admin' }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                            {{ $email ?: '—' }}
                        </p>
                        @if($this->currentRoleLabel)
                            <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider
                                         bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300
                                         border border-purple-200 dark:border-purple-500/30">
                                {{ $this->currentRoleLabel }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="space-y-3"
                     x-data="imageCropper({
                         wireProperty: 'avatar',
                         aspect: 1,
                         title: 'Crop profile photo',
                         description: 'Square crop works best',
                     })"
                     x-init="init()">
                    <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Profile Photo</span>

                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="avatar-upload"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer active:scale-95
                                      focus-within:ring-2 focus-within:ring-primary-500/50 focus-within:ring-offset-2 dark:focus-within:ring-offset-gray-900
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            {{ $this->avatarUrl ? 'Replace photo' : 'Upload photo' }}
                            <input type="file" id="avatar-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>

                        @if($this->avatarUrl)
                            <button type="button"
                                    x-data="{
                                        armed: false,
                                        _t: null,
                                        arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                        unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                        destroy() { clearTimeout(this._t); }
                                    }"
                                    @click="armed ? (unarm(), $wire.removeAvatar()) : arm()"
                                    wire:loading.attr="disabled"
                                    wire:target="removeAvatar"
                                    :class="armed
                                        ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/40'
                                        : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'"
                                    class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <span x-show="!armed">Remove photo</span>
                                <span x-show="armed" x-cloak>Confirm</span>
                            </button>
                        @endif

                        <div wire:loading wire:target="avatar" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                            <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>
                    </div>

                    @error('avatar') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <div class="space-y-5">
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Personal Information</h2>
                        @if($this->currentRoleLabel)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30">
                                {{ $this->currentRoleLabel }}
                            </span>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
                            <input type="text" id="field-name" wire:model="name" autocomplete="name" class="input">
                            @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="field-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                            <input type="email" id="field-email" wire:model="email" autocomplete="email" class="input">
                            @error('email') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <div class="space-y-5">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Change Password</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave blank to keep your current password.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-2xl">
                        <div class="md:col-span-2">
                            <label for="field-current-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                            <input type="password" id="field-current-password" wire:model="current_password" autocomplete="current-password" class="input">
                            @error('current_password') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="field-new-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                            <input type="password" id="field-new-password" wire:model="new_password" autocomplete="new-password" minlength="8" class="input">
                            @error('new_password') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label for="field-confirm-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                            <input type="password" id="field-confirm-password" wire:model="new_password_confirmation" autocomplete="new-password" minlength="8" class="input">
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="update"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="update">Save Changes</span>
                        <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div
            id="panel-branding"
            role="tabpanel"
            aria-labelledby="tab-branding"
            :class="tab === 'branding' ? 'block' : 'hidden'"
            class="p-5 sm:p-6"
        >
            <form wire:submit="saveBranding" class="space-y-6">

                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Site Branding</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">The logo and title appear on the public website header.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[10rem_1fr] gap-6 items-start">

                    <div
                        x-data="avatarPreview()"
                        x-on:site-logo-preview.window="setUrl($event.detail.url)"
                        x-on:site-logo-cleared.window="clear()"
                        x-on:logo-removed.window="clear()"
                        class="relative w-32 h-32 sm:w-40 sm:h-40 shrink-0 mx-auto lg:mx-0"
                    >
                        <img
                            :src="previewUrl || '{{ $this->currentSiteLogoUrl ?? '' }}'"
                            :class="(previewUrl || {{ $this->currentSiteLogoUrl ? 'true' : 'false' }}) ? 'block' : 'hidden'"
                            alt="Site logo"
                            class="w-32 h-32 sm:w-40 sm:h-40 object-contain bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-3"
                            loading="lazy"
                            decoding="async"
                        >

                        <div
                            :class="(previewUrl || {{ $this->currentSiteLogoUrl ? 'true' : 'false' }}) ? 'hidden' : 'flex'"
                            class="w-32 h-32 sm:w-40 sm:h-40 rounded-xl bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 items-center justify-center text-gray-400 dark:text-gray-500 text-4xl font-bold"
                        >
                            {{ strtoupper(substr($this->siteName ?: 'T', 0, 1)) }}
                        </div>

                        <div
                            wire:loading.flex
                            wire:target="siteLogo"
                            class="absolute inset-0 rounded-xl bg-black/55 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                            aria-hidden="true"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-5 min-w-0">
                        <div>
                            <label for="site-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Header Title</label>
                            <input type="text" id="site-name" wire:model="siteName" class="input" placeholder="Tourism Management">
                            @error('siteName') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div
                            x-data="imageCropper({
                                wireProperty: 'siteLogo',
                                aspect: 1,
                                title: 'Crop site logo',
                                description: 'Square crop works best',
                                previewEvent: 'site-logo-preview',
                            })"
                            x-init="init()"
                        >
                            <span class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Logo</span>

                            <div class="flex items-center gap-2 flex-wrap">
                                <label for="site-logo"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer
                                              focus-within:ring-2 focus-within:ring-primary-500/50">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ $this->currentSiteLogoUrl ? 'Replace logo' : 'Upload logo' }}
                                    <input type="file" id="site-logo" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                                </label>

                                @if($this->currentSiteLogoUrl)
                                    <button type="button"
                                            x-data="{
                                                armed: false,
                                                _t: null,
                                                arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                                unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                                destroy() { clearTimeout(this._t); }
                                            }"
                                            @click="armed ? (unarm(), $wire.removeLogo()) : arm()"
                                            wire:loading.attr="disabled"
                                            wire:target="removeLogo"
                                            :class="armed
                                                ? 'border-amber-400 bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/40'
                                                : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300'"
                                            class="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-xs font-semibold
                                                   transition-all duration-200 active:scale-95
                                                   hover:border-rose-400 hover:text-rose-600 dark:hover:text-rose-400
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                                   disabled:opacity-60 disabled:cursor-not-allowed">
                                        <span x-show="!armed">Remove logo</span>
                                        <span x-show="armed" x-cloak>Confirm</span>
                                    </button>
                                @endif
                            </div>

                            <div wire:loading wire:target="siteLogo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400 mt-3">
                                <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Uploading…
                            </div>

                            @error('siteLogo') <span class="text-rose-500 dark:text-rose-400 text-xs mt-2 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveBranding"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveBranding">Save Branding</span>
                        <span wire:loading wire:target="saveBranding" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <div
            id="panel-location"
            role="tabpanel"
            aria-labelledby="tab-location"
            :class="tab === 'location' ? 'block' : 'hidden'"
            class="p-5 sm:p-6"
        >
            <form wire:submit="saveSiteLocation" class="space-y-5">

                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Site Location</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        The default map centre for public pages. Drop a pin to set the reference point; the address auto-fills.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            wire:click="$dispatch('request-geolocation')"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Use my location
                    </button>

                    @if($this->hasSiteLocation)
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 rounded-full px-2 py-1">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                            Pinned
                        </span>
                        <button type="button"
                                wire:click="clearSiteLocation"
                                class="text-[11px] font-semibold text-gray-500 hover:text-rose-600 dark:text-gray-400 dark:hover:text-rose-400 transition px-1.5 active:scale-95">
                            Clear
                        </button>
                    @endif
                </div>

                <div class="relative">
                    <div
                        wire:ignore
                        wire:key="site-location-map-stable"
                        x-data="locationPicker({
                            containerId: 'site-location-map',
                            wireMethod: 'setSiteLocation',
                            geocodeMethod: 'resolveSiteAddress',
                            initialLat: {{ $siteLatitude ?? 'null' }},
                            initialLng: {{ $siteLongitude ?? 'null' }},
                        })"
                        x-init="
                            const __el = document.getElementById('site-location-map');
                            if (__el) {
                                const __data = $data;
                                const __boot = () => __data.init();
                                if (Math.min(__el.clientWidth, __el.clientHeight) > 0) {
                                    __boot();
                                } else {
                                    const __ro = new ResizeObserver((entries) => {
                                        for (const __e of entries) {
                                            if (Math.min(__e.contentRect.width, __e.contentRect.height) > 0) {
                                                __ro.disconnect();
                                                __boot();
                                                return;
                                            }
                                        }
                                    });
                                    __ro.observe(__el);
                                }
                            }
                        "
                        x-on:map:pin-cleared.window="clearMarker()"
                        class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 h-[320px] sm:h-[380px]"
                    >
                        <x-map
                            id="site-location-map"
                            :center="$this->siteMapCenter"
                            :zoom="$this->siteMapZoom"
                            height="100%"
                            provider="carto-voyager"
                            theme="auto"
                            :max-zoom="22"
                            class="h-full w-full"
                        >
                            <x-map-controls
                                :zoom="true"
                                :compass="true"
                                :locate="false"
                                :fullscreen="true"
                                :scale="true"
                                position="top-right"
                            />
                        </x-map>

                        <div x-cloak
                             :class="hasPin ? 'hidden' : ''"
                             class="absolute inset-x-0 top-3 mx-auto w-max pointer-events-none
                                    rounded-full bg-gray-900/80 backdrop-blur-sm text-white
                                    text-[11px] font-semibold px-3 py-1.5">
                            Click the map to drop a pin
                        </div>
                    </div>

                    <div wire:loading wire:target="resolveSiteAddress"
                         class="absolute bottom-3 left-3 z-10 pointer-events-none">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-900/85 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5 shadow-lg">
                            <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Looking up address…
                        </span>
                    </div>
                </div>

                @if($this->hasSiteLocation)
                    <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 tabular-nums">
                        {{ number_format($siteLatitude, 6) }}, {{ number_format($siteLongitude, 6) }}
                    </p>
                @endif

                @error('siteLatitude') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                @error('siteLongitude') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror

                <div class="pt-4 border-t border-gray-100 dark:border-gray-700/60 space-y-4">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</span>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label for="field-site-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                            <input type="text" id="field-site-address" wire:model="siteAddress" class="input" placeholder="Street, Building, etc." maxlength="255">
                        </div>
                        <div>
                            <label for="field-site-barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                            <input type="text" id="field-site-barangay" wire:model="siteBarangay" class="input" maxlength="255">
                        </div>
                        <div>
                            <label for="field-site-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City / Municipality</label>
                            <input type="text" id="field-site-city" wire:model="siteCity" class="input" maxlength="255">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="field-site-province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                            <input type="text" id="field-site-province" wire:model="siteProvince" class="input" maxlength="255">
                        </div>
                    </div>
                </div>

                <div class="pt-5 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveSiteLocation"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm transition-all duration-200 active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveSiteLocation">Save Location</span>
                        <span wire:loading wire:target="saveSiteLocation" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Saving…
                        </span>
                    </button>
                </div>
            </form>
        </div>

    </div>

    <x-image-crop-modal />
</div>