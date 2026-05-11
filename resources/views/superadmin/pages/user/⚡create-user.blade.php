<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Create Global User')] 
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('required|email|unique:users,email')]
    public $email = '';
    
    #[Validate('required|min:8|confirmed')]
    public $password = '';
    
    public $password_confirmation = '';
    
    #[Validate('nullable|exists:tenants,id')]
    public $tenant_id = '';
    
    #[Validate('required|exists:roles,name')]
    public $role = '';
    
    public string $tenantSearch = '';
    public bool $isPlatformUser = false;

    public function updated($property)
    {
        if (in_array($property, ['name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    #[Computed]
    public function tenants() 
    { 
        return Tenant::orderBy('name')
            ->when($this->tenantSearch, function ($query) {
                $query->where('name', 'like', '%' . $this->tenantSearch . '%');
            })
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function roles() 
    { 
        return Role::orderBy('name')->get();
    }

    public function updatedIsPlatformUser($value)
    {
        if ($value) {
            $this->tenant_id = '';
            $this->tenantSearch = '';
        }
    }

    public function selectTenant($id, $name)
    {
        $this->tenant_id = $id;
        $this->tenantSearch = $name;
        $this->isPlatformUser = false;
    }

    public function clearTenant()
    {
        $this->tenant_id = '';
        $this->tenantSearch = '';
    }

    public function store()
    {
        $this->validate();

        $tenantId = $this->isPlatformUser ? null : ($this->tenant_id ?: null);

        DB::transaction(function () use ($tenantId) {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'tenant_id' => $tenantId,
                'is_active' => true,
            ]);

            $user->assignRole($this->role);
        });
        
        session()->flash('message', "User '{$this->name}' created successfully.");
        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

<div>
    {{-- Tom Select CDN (light + dark theme) --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" data-navigate-once>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.dark.css" rel="stylesheet" data-navigate-once>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js" data-navigate-once></script>

    <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Flash Message --}}
        @if (session()->has('message'))
            <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
                {{ session('message') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Create New User</h1>
            <a href="{{ route('superadmin.users.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-white/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors flex items-center gap-1">
                &larr; Back to Users
            </a>
        </div>

        <form wire:submit="store" class="space-y-6 bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-5 sm:p-6 shadow-sm dark:shadow-none">
            {{-- Basic Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Full Name</label>
                    <input type="text" wire:model="name" 
                           class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition" placeholder="e.g. Jane Doe">
                    @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Email Address</label>
                    <input type="email" wire:model="email" 
                           class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition" placeholder="jane@example.com">
                    @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Password --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Password</label>
                    <input type="password" wire:model="password" 
                           class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition" placeholder="••••••••">
                    @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Confirm Password</label>
                    <input type="password" wire:model="password_confirmation" 
                           class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition" placeholder="••••••••">
                </div>
            </div>

            {{-- Tenant & Role Section --}}
            <div class="pt-4 border-t border-gray-200 dark:border-white/10">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Access & Permissions</h3>
                
                {{-- Platform User Toggle --}}
                <div class="mb-4">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="isPlatformUser" class="sr-only peer">
                        <div class="relative w-11 h-6 bg-gray-200 dark:bg-white/10 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-brand-500/30 dark:peer-focus:ring-brand-500/30 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 dark:after:border-white/20 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                        <span class="ms-3 text-sm font-medium text-gray-700 dark:text-white/70">Platform User (No Business Affiliation)</span>
                    </label>
                    <p class="text-xs text-gray-500 dark:text-white/40 mt-1 ml-14">Enable if this user should have global platform access without being tied to a specific business.</p>
                </div>

                {{-- Tenant Selection --}}
                @if(!$isPlatformUser)
                <div class="mb-4" x-data="tenantSelector()" x-init="init()">
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Assign Business (Tenant)</label>
                    
                    <div wire:ignore>
                        <select x-ref="select" class="w-full">
                            <option value="">Search for a business...</option>
                            @foreach($this->tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                @endif

                {{-- Role Selection --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-1">Assign System Role</label>
                    <select wire:model="role" class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        <option value="">-- Select a Role --</option>
                        @foreach($this->roles as $roleData)
                            <option value="{{ $roleData->name }}">
                                {{ ucwords(str_replace(['-', '_'], ' ', $roleData->name)) }}
                                @if($roleData->name === 'super-admin')
                                    (Full Platform Access)
                                @elseif($roleData->name === 'admin')
                                    (Business Owner)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            
            {{-- Form Actions --}}
            <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-white/10">
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span wire:loading.remove>Create User</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Creating...
                    </span>
                </button>
                <a href="{{ route('superadmin.users.index') }}" wire:navigate class="bg-white dark:bg-white/5 text-gray-700 dark:text-white/70 border border-gray-300 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/10 font-medium py-2.5 px-6 rounded-xl transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    function tenantSelector() {
        return {
            ts: null,
            init() {
                let checkInterval = setInterval(() => {
                    if (typeof TomSelect !== 'undefined') {
                        clearInterval(checkInterval);
                        this.initTomSelect();
                    }
                }, 100);
            },
            initTomSelect() {
                const tenantData = @js($this->tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));
                const isDark = document.documentElement.classList.contains('dark');

                this.ts = new TomSelect(this.$refs.select, {
                    create: false,
                    placeholder: 'Search for a business...',
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name'],
                    options: tenantData,
                    className: isDark ? 'dark' : '',
                    onChange: (value) => {
                        if (value) {
                            const option = this.ts.options[value];
                            @this.selectTenant(value, option.name);
                        } else {
                            @this.clearTenant();
                        }
                    }
                });

                @this.$watch('tenantSearch', (value) => {
                    if (!value && this.ts) {
                        this.ts.clear();
                    }
                });
                
                @this.$watch('isPlatformUser', (value) => {
                    if (value && this.ts) {
                        this.ts.clear();
                    }
                });
            }
        };
    }
</script>
