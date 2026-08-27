{{-- resources/views/superadmin/pages/user/⚡edit-user.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit User')]
class extends Component {

    use WithFileUploads;

    public User $user;

    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone = '';
    public $avatar;
    public $tenant_id = '';
    public $role = '';
    public string $tenantSearch = '';
    public bool $isPlatformUser = false;
    public bool $is_active = true;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->tenant_id = $user->tenant_id;
        $this->isPlatformUser = is_null($user->tenant_id);
        $this->is_active = (bool) $user->is_active;

        if ($this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
            $this->tenantSearch = $tenant?->name ?? '';
        }

        $this->role = $user->roles->first()?->name ?? '';
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email', 'phone'])) {
            $this->$property = trim($this->$property);
        }
    }

    protected function rules()
    {
        return [
            'name'     => ['required', 'string', 'min:3', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone'    => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar'   => ['nullable', 'image', 'max:2048'],
            'tenant_id'=> ['nullable', 'exists:tenants,id'],
            'role'     => ['required', 'exists:roles,name'],
            'is_active'=> ['boolean'],
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'Invalid Philippine phone number. Use 09xxxxxxxxx or +639xxxxxxxxx.',
        ];
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::orderBy('name')
            ->when($this->tenantSearch, fn($q) => $q->where('name', 'like', '%' . $this->tenantSearch . '%'))
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::orderBy('name')
            ->get()
            ->filter(function ($role) {
                if ($role->name === 'super-admin') {
                    return $this->user->hasRole('super-admin');
                }
                return true;
            })
            ->map(fn($role) => [
                'name'  => $role->name,
                'label' => ucwords(str_replace(['-', '_'], ' ', $role->name)),
            ])
            ->values();
    }

    public function updatedIsPlatformUser($value)
    {
        if ($value) {
            $this->tenant_id = '';
            $this->tenantSearch = '';
        } else {
            if ($this->role === 'tourist') {
                $this->role = '';
            }
        }
    }

    public function clearTenant()
    {
        $this->tenant_id = '';
        $this->tenantSearch = '';
    }

    public function update()
    {
        $this->validate();

        $tenantId = $this->isPlatformUser ? null : ($this->tenant_id ?: null);

        if (!$this->isPlatformUser && !$tenantId) {
            $this->addError('tenant_id', 'Please select a business for this user.');
            return;
        }

        if ($this->user->id === auth()->id() && $this->user->hasRole('super-admin') && $this->role !== 'super-admin') {
            $this->addError('role', 'You cannot remove your own Super Admin role.');
            return;
        }

        DB::transaction(function () use ($tenantId) {
            $avatarPath = $this->user->avatar;
            if ($this->avatar) {
                $avatarPath = $this->avatar->store('user-avatars', 'public');
            }

            $data = [
                'name'      => $this->name,
                'email'     => $this->email,
                'phone'     => $this->phone,
                'avatar'    => $avatarPath,
                'tenant_id' => $tenantId,
                'is_active' => $this->is_active,
            ];

            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }

            $this->user->update($data);
            $this->user->syncRoles([$this->role]);
        });

        session()->flash('message', "User '{$this->user->name}' updated successfully.");
        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

@push('styles')
<style>
    .toggle-switch {
        position: relative;
        display: inline-flex;
        align-items: center;
        cursor: pointer;
    }
    .toggle-switch input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-switch .toggle-track {
        width: 2.75rem;
        height: 1.5rem;
        background: #e5e7eb;
        border-radius: 9999px;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .dark .toggle-switch .toggle-track {
        background: #374151;
    }
    .toggle-switch .toggle-thumb {
        position: absolute;
        left: 0.25rem;
        top: 0.25rem;
        width: 1rem;
        height: 1rem;
        background: white;
        border-radius: 9999px;
        transition: transform 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    .toggle-switch input:checked + .toggle-track {
        background: #22c55e;
    }
    .toggle-switch input:checked + .toggle-track + .toggle-thumb {
        transform: translateX(1.25rem);
    }
    .toggle-switch input:focus-visible + .toggle-track {
        box-shadow: 0 0 0 3px rgba(34,197,94,0.3);
    }
</style>
@endpush

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6"
     x-data="{ showPassword: false, showConfirmPassword: false, avatarPreview: null }">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit User</h1>
        </div>
        <a href="{{ route('superadmin.users.index') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
            ← Back to Users
        </a>
    </div>

    <form wire:submit="update" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-6">

        {{-- Basic Information --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                <input type="text" wire:model="name"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                <input type="email" wire:model="email"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                <input type="text" wire:model="phone"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition"
                       placeholder="09123456789">
                @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Picture (Optional)</label>
                <input type="file"
                       wire:model="avatar"
                       x-ref="avatarInput"
                       accept="image/*"
                       @change="avatarPreview = URL.createObjectURL($refs.avatarInput.files[0])"
                       class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                @error('avatar') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

                {{-- Preview area: show client-side preview if selected, otherwise existing avatar --}}
                <div class="mt-3">
                    <img x-show="avatarPreview"
                         :src="avatarPreview"
                         class="h-20 w-20 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                         alt="Avatar preview">
                    <img x-show="!avatarPreview && @js($user->avatar) !== null"
                         src="{{ $user->avatar ? asset('storage/' . $user->avatar) : '' }}"
                         class="h-20 w-20 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                         alt="Current avatar">
                </div>
            </div>
        </div>

        {{-- Password --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    New Password <span class="text-gray-400 dark:text-gray-500 font-normal">(Optional)</span>
                </label>
                <div class="relative">
                    <input :type="showPassword ? 'text' : 'password'" wire:model="password"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 pr-11 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition"
                           placeholder="Leave blank to keep current">
                    <button type="button" @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                            tabindex="-1" aria-label="Toggle password visibility">
                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                <div class="relative">
                    <input :type="showConfirmPassword ? 'text' : 'password'" wire:model="password_confirmation"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 pr-11 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition"
                           placeholder="••••••••">
                    <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                            tabindex="-1" aria-label="Toggle confirm password visibility">
                        <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                @error('password_confirmation') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Access & Permissions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Access & Permissions</h3>

            {{-- Platform User Toggle --}}
            <div class="mb-4">
                <div class="flex items-center">
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model.live="isPlatformUser">
                        <span class="toggle-track"></span>
                        <span class="toggle-thumb"></span>
                    </label>
                    <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">Platform User (No Business Affiliation)</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-14">Enable if this user should have global platform access without being tied to a specific business.</p>
            </div>

            {{-- Tenant Selection --}}
            @if(!$isPlatformUser)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Business (Tenant) *</label>
                    <input type="text" wire:model.live.debounce.300ms="tenantSearch"
                           placeholder="Search businesses…"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition mb-2">
                    <select wire:model="tenant_id"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                        <option value="">-- Select a business --</option>
                        @foreach($this->tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            {{-- Role Select --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assign Role *</label>
                <select wire:model="role"
                        class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                    <option value="">-- Select a role --</option>
                    @foreach($this->availableRoles as $roleData)
                        <option value="{{ $roleData['name'] }}">{{ $roleData['label'] }}</option>
                    @endforeach
                </select>
                @error('role') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Active Toggle --}}
            <div class="mt-4">
                <div class="flex items-center">
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="is_active">
                        <span class="toggle-track"></span>
                        <span class="toggle-thumb"></span>
                    </label>
                    <span class="ms-3 text-sm font-medium text-gray-700 dark:text-gray-300">Active Account</span>
                </div>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" wire:loading.attr="disabled"
                    class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg shadow-primary-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Update User</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.users.index') }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium py-2.5 px-5 rounded-full transition-colors hover:scale-[1.02] focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>