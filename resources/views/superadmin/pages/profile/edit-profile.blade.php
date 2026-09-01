{{-- resources/views/superadmin/pages/profile/⚡edit-profile.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\SiteSetting;

new 
#[Layout('superadmin.layouts.app')]
#[Title('My Profile')]
class extends Component {

    use WithFileUploads;

    public $name = '';
    public $email = '';

    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    // Site branding
    public $siteLogo;
    public $siteName = '';

    public function mount()
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;

        $this->siteName = SiteSetting::getValue('site_name', 'Tourism Management');
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(Auth::id()),
            ],
            'current_password' => 'required_with:new_password',
            'new_password' => 'nullable|min:8|confirmed',
        ];
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    #[Computed]
    public function currentSiteLogoUrl(): ?string
    {
        $path = SiteSetting::getValue('site_logo');
        return $path ? asset('storage/' . $path) : null;
    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        if ($this->new_password) {
            if (! Hash::check($this->current_password, Auth::user()->password)) {
                $this->addError('current_password', 'The current password is incorrect.');
                return;
            }

            $data['password'] = Hash::make($this->new_password);
        }

        Auth::user()->update($data);

        session()->flash('message', 'Profile updated successfully.');
        return redirect()->route('superadmin.profile');
    }

    public function saveBranding()
    {
        $this->validate([
            'siteName' => 'required|string|max:255',
            'siteLogo' => 'nullable|image|max:2048',
        ]);

        SiteSetting::setValue('site_name', $this->siteName);

        if ($this->siteLogo) {
            $oldPath = SiteSetting::getValue('site_logo');
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $this->siteLogo->store('site', 'public');
            SiteSetting::setValue('site_logo', $path);

            $this->siteLogo = null;
        }

        session()->flash('message', 'Site branding updated successfully.');
        return redirect()->route('superadmin.profile');
    }

    public function removeLogo()
    {
        $oldPath = SiteSetting::getValue('site_logo');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        SiteSetting::forget('site_logo');

        session()->flash('message', 'Site logo removed.');
        return redirect()->route('superadmin.profile');
    }
};
?>

<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">My Profile</h1>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Dashboard
        </a>
    </div>

    <form wire:submit="update" class="card p-5 sm:p-6 space-y-6">

        <div class="flex items-center gap-2 pb-4 border-b border-gray-200 dark:border-gray-700">
            <span class="text-sm text-gray-600 dark:text-gray-400">System Role:</span>
            @php $role = Auth::user()->roles->first(); @endphp
            @if($role)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-500/30">
                    {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                </span>
            @else
                <span class="text-gray-400 dark:text-gray-500 text-xs italic">No role assigned</span>
            @endif
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name</label>
            <input type="text" wire:model="name" autocomplete="name" class="input">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
            <input type="email" wire:model="email" autocomplete="email" class="input">
            @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        <p class="text-sm text-gray-500 dark:text-gray-400">Change password – leave blank to keep current.</p>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
            <input type="password" wire:model="current_password" autocomplete="current-password" class="input">
            @error('current_password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
            <input type="password" wire:model="new_password" autocomplete="new-password" minlength="8" class="input">
            @error('new_password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
            <input type="password" wire:model="new_password_confirmation" autocomplete="new-password" minlength="8" class="input">
        </div>

        <div class="flex flex-col sm:flex-row gap-3 pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.dashboard') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>

    <div class="card p-5 sm:p-6">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Site Branding</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            This logo and name appear in the public website header.
        </p>

        <form wire:submit="saveBranding" class="space-y-6">
            <div class="flex flex-col sm:flex-row items-start gap-6">
                <div class="shrink-0" x-data="{ previewUrl: null }">
                    @php $currentLogoUrl = $this->currentSiteLogoUrl; @endphp
                    <img x-show="previewUrl || @js($currentLogoUrl) !== null"
                         :src="previewUrl || '{{ $currentLogoUrl }}'"
                         alt="Site logo"
                         class="w-24 h-24 object-contain bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 p-2"
                         x-cloak>
                    <div x-show="!previewUrl && !@js($currentLogoUrl)"
                         class="w-24 h-24 rounded-lg bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500 text-3xl font-bold">
                        {{ strtoupper(substr($this->siteName, 0, 1)) }}
                    </div>
                </div>

                <div class="flex-1 space-y-4">
                    <div>
                        <label for="site-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Header Title</label>
                        <input type="text" id="site-name" wire:model="siteName" class="input" placeholder="Tourism Management">
                        @error('siteName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="site-logo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Logo</label>
                        <input type="file" id="site-logo" wire:model="siteLogo"
                               x-ref="siteLogoInput"
                               accept="image/png,image/jpeg,image/svg+xml,image/webp"
                               @change="previewUrl = URL.createObjectURL($refs.siteLogoInput.files[0])"
                               class="block w-full text-sm text-gray-600 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        @error('siteLogo') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span wire:loading.remove>Save Branding</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>

                @if($this->currentSiteLogoUrl)
                    <button type="button" wire:click="removeLogo"
                            wire:confirm="Are you sure you want to remove the current logo?"
                            class="btn-secondary w-full sm:w-auto text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/30 hover:bg-red-50 dark:hover:bg-red-500/10 active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-red-500/50">
                        Remove Logo
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>