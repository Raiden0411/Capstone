<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Register New Tenant')]
class extends Component {
    
    #[Validate('required|min:3|max:255|unique:tenants,name')]
    public $name = '';

    #[Validate('required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    public $slug = '';

    #[Validate('required|integer|exists:type_of_tenants,id')]
    public $type_of_tenant_id = '';

    #[Validate('required|string|max:255')]
    public $address = '';

    #[Validate('required|email|max:255|unique:tenants,email|unique:users,email')]
    public $email = '';

    #[Validate('nullable|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/')]
    public $contact_number = '';

    #[Validate('required|numeric|min:-90|max:90')]
    public $latitude = 10.900977766937142;

    #[Validate('required|numeric|min:-180|max:180')]
    public $longitude = 123.07055771888716;

    #[Validate('required|string|max:255')]
    public $admin_name = '';

    #[Validate('required|min:8')]
    public $password = '';

    #[Computed]
    public function tenantTypes()
    {
        return TypeOfTenant::all();
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered. Please use a different email address.',
            'slug.regex'   => 'The slug may only contain lowercase letters, numbers, and hyphens.',
        ];
    }

    /**
     * Sanitize all string inputs before validation: trim and strip HTML tags.
     */
    protected function sanitize(): void
    {
        $fields = ['name', 'address', 'contact_number', 'admin_name', 'email'];
        foreach ($fields as $field) {
            if (is_string($this->$field)) {
                $this->$field = trim(strip_tags($this->$field));
            }
        }
        // Slug is auto‑generated, but ensure it's clean
        $this->slug = Str::slug($this->name);
    }

    /**
     * Auto‑update slug when name changes.
     */
    public function updatedName($value)
    {
        $this->slug = Str::slug(trim($value));
    }

    /**
     * Trim any input on update (runs after property update but before sanitization).
     */
    public function updated($property)
    {
        if (in_array($property, ['name', 'address', 'contact_number', 'admin_name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function save()
    {
        $this->sanitize();  // Clean all inputs first
        $this->validate();

        DB::transaction(function () {
            $tenant = Tenant::create([
                'name'              => $this->name,
                'slug'              => $this->slug,
                'type_of_tenant_id' => $this->type_of_tenant_id,
                'address'           => $this->address,
                'email'             => $this->email,
                'contact_number'    => $this->contact_number,
                'latitude'          => $this->latitude,
                'longitude'         => $this->longitude,
                'is_active'         => true,
            ]);

            $user = User::create([
                'name'       => $this->admin_name,
                'email'      => $this->email,
                'password'   => Hash::make($this->password),
                'tenant_id'  => $tenant->id,
                'is_active'  => true,
            ]);

            $user->assignRole('admin');
        });

        session()->flash('message', 'Business Location & Admin Account successfully created!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

<div>
    <div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white">

        {{-- Flash Message --}}
        @if (session()->has('message'))
            <div class="mb-6 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 p-4 rounded-md shadow-sm">
                <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Register New Business</h1>
                <p class="text-gray-500 dark:text-slate-400 mt-1">Add a new business location and setup their admin account.</p>
            </div>
            <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
                &larr; Back to Tenants
            </a>
        </div>

        <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Left Column: Form Fields --}}
            <div class="space-y-6">
                {{-- Business Details Card --}}
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-700/50 pb-2">Business Details</h2>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Business Name</label>
                                <input type="text" wire:model.live.debounce.300ms="name" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                                @error('name') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">URL Slug</label>
                                <input type="text" wire:model="slug" readonly 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-slate-300 cursor-not-allowed focus:ring-blue-500" placeholder="auto-generated-slug">
                                @error('slug') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tenant Type</label>
                                <select wire:model="type_of_tenant_id" 
                                        class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">-- Select Type --</option>
                                    @foreach($this->tenantTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type }}</option>
                                    @endforeach
                                </select>
                                @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Contact Number</label>
                                <input type="text" wire:model="contact_number" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                                @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Headquarters Address</label>
                            <input type="text" wire:model="address" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="Street, City, Province">
                            @error('address') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Coordinates --}}
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Latitude</label>
                                <input type="text" wire:model.live="latitude" onfocus="this.select()" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Longitude</label>
                                <input type="text" wire:model.live="longitude" onfocus="this.select()" 
                                       class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                                @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Admin Account Card --}}
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b border-gray-200 dark:border-slate-700/50 pb-2">Admin Account Setup</h2>
                    <p class="text-sm text-gray-500 dark:text-slate-400 mb-4">This creates the login account for the business owner.</p>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Admin Full Name</label>
                            <input type="text" wire:model="admin_name" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. John Doe">
                            @error('admin_name') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Login Email</label>
                            <input type="email" wire:model="email" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="admin@business.com">
                            <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">This email acts as both the public contact and the admin's login ID.</p>
                            @error('email') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Initial Password</label>
                            <input type="password" wire:model="password" 
                                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="••••••••">
                            @error('password') <span class="text-red-500 dark:text-red-400 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                {{-- Submit Button --}}
                <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">Register Business & Admin</span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Processing...
                    </span>
                </button>
            </div>

            {{-- Right Column: Map Component --}}
            <div class="h-fit sticky top-6">
                <x-location-map :readonly="false" height="650px" />
            </div>
        </form>
    </div>
</div>