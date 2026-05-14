<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Property;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\BookingItem;
use App\Models\BookingService;
use App\Models\Payment;
use App\Services\PayMongoService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

new 
#[Layout('layouts.app')]
#[Title('Complete Your Booking')]
class extends Component
{
    public Property $property;

    #[Validate('required|string|max:255')]
    public $customerName = '';
    #[Validate('nullable|string|max:20')]
    public $customerPhone = '';
    #[Validate('nullable|email|max:255')]
    public $customerEmail = '';
    #[Validate('nullable|string')]
    public $customerAddress = '';

    #[Validate('required|date|after_or_equal:today')]
    public $check_in;
    #[Validate('required|date|after:check_in')]
    public $check_out;

    public $selectedServices = [];
    public $totalAmount = 0;
    public $totalDays = 1;         

    #[Validate('required|in:cash,card,gcash,paymaya')]
    public $payment_method = 'cash';

    public function mount($publicproperty)
    {
        $this->property = Property::with('tenant')
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($publicproperty);

        if (!$this->property->tenant || !$this->property->tenant_id) {
            abort(404, 'This property is not associated with any business.');
        }

        if (Auth::check()) {
            $this->customerName  = Auth::user()->name;
            $this->customerEmail = Auth::user()->email;
        }

        $this->check_in  = now()->format('Y-m-d');
        $this->check_out = now()->addDay()->format('Y-m-d');
        $this->calculateTotal();
    }

    public function updatedCheckIn()
    {
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_in > $maxDate) {
            $this->check_in = $maxDate;
        }
        if ($this->check_out && Carbon::parse($this->check_in)->gte(Carbon::parse($this->check_out))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function updatedCheckOut()
    {
        // Ensure check‑out is within 30 days from today
        $maxDate = now()->addDays(30)->format('Y-m-d');
        if ($this->check_out > $maxDate) {
            $this->check_out = $maxDate;
        }
        if (Carbon::parse($this->check_out)->lte(Carbon::parse($this->check_in))) {
            $this->check_out = Carbon::parse($this->check_in)->addDay()->format('Y-m-d');
        }
        $this->calculateTotal();
    }

    public function addService($serviceId)
    {
        $this->selectedServices[$serviceId] = ($this->selectedServices[$serviceId] ?? 0) + 1;
        $this->calculateTotal();
    }

    public function removeService($serviceId)
    {
        unset($this->selectedServices[$serviceId]);
        $this->calculateTotal();
    }

    public function calculateTotal()
    {
        $checkIn  = Carbon::parse($this->check_in);
        $checkOut = Carbon::parse($this->check_out);
        $this->totalDays = max(1, $checkIn->diffInDays($checkOut));
        $this->totalAmount = $this->property->price * $this->totalDays;
        foreach ($this->selectedServices as $serviceId => $qty) {
            $service = Service::find($serviceId);
            if ($service) $this->totalAmount += $service->price * $qty;
        }
    }

    public function getAvailableServicesProperty()
    {
        return Service::where('tenant_id', $this->property->tenant_id)
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function submit()
    {
        $this->validate();
        $tenantId = $this->property->tenant_id;
        if (!$tenantId) {
            session()->flash('error', 'This property is not linked to a valid business.');
            return;
        }

        // Availability conflict check
        $conflict = BookingItem::where('property_id', $this->property->id)
            ->whereHas('booking', function ($query) {
                $query->whereNotIn('status', ['cancelled', 'completed'])
                      ->where('check_in', '<', $this->check_out)
                      ->where('check_out', '>', $this->check_in);
            })->exists();

        if ($conflict) {
            session()->flash('error', 'This property is not available for the selected dates. Please choose different dates.');
            return;
        }

        $redirect = DB::transaction(function () use ($tenantId) {
            $customer = Customer::firstOrCreate(
                ['email' => $this->customerEmail, 'tenant_id' => $tenantId],
                ['name' => $this->customerName, 'phone' => $this->customerPhone, 'address' => $this->customerAddress]
            );
            $booking = Booking::create([
                'tenant_id'         => $tenantId,
                'customer_id'       => $customer->id,
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'check_in'          => $this->check_in,
                'check_out'         => $this->check_out,
                'total_amount'      => $this->totalAmount,
                'status'            => 'pending',
            ]);
            BookingItem::create([
                'tenant_id'   => $tenantId,
                'booking_id'  => $booking->id,
                'property_id' => $this->property->id,
                'price'       => $this->property->price,
                'quantity'    => 1,
                'subtotal'    => $this->property->price * $this->totalDays,
            ]);
            foreach ($this->selectedServices as $serviceId => $qty) {
                $service = Service::find($serviceId);
                if ($service) {
                    BookingService::create([
                        'tenant_id'  => $tenantId,
                        'booking_id' => $booking->id,
                        'service_id' => $serviceId,
                        'quantity'   => $qty,
                        'subtotal'   => $service->price * $qty,
                    ]);
                }
            }
            if ($this->payment_method === 'cash') {
                Payment::create([
                    'tenant_id'        => $tenantId,
                    'booking_id'       => $booking->id,
                    'amount'           => $this->totalAmount,
                    'payment_method'   => 'cash',
                    'payment_status'   => 'paid',
                    'paid_at'          => now(),
                ]);
                return null; // cash booking – stay on page
            }

            // Online payment via PayMongo
            $payMongo = app(PayMongoService::class);
            $session = $payMongo->createCheckoutSession([
                'customer_name'        => $customer->name,
                'customer_email'       => $customer->email ?? 'guest@example.com',
                'customer_phone'       => $customer->phone,
                'amount'               => $this->totalAmount,
                'description'          => "Booking #{$booking->booking_reference}",
                'item_name'            => 'Tourism Activity',
                'success_url'          => route('tenant.payments.success', ['booking' => $booking->id]),
                'cancel_url'           => route('tenant.payments.cancel', ['booking' => $booking->id]),
                'metadata'             => ['booking_id' => $booking->id, 'tenant_id' => $tenantId],
                'payment_method_types' => [$this->payment_method],
            ]);

            if ($session) {
                Payment::create([
                    'tenant_id'           => $tenantId,
                    'booking_id'          => $booking->id,
                    'amount'              => $this->totalAmount,
                    'payment_method'      => $this->payment_method,
                    'payment_status'      => 'unpaid',
                    'paymongo_session_id' => $session['data']['id'],
                ]);
                return redirect()->away($session['data']['attributes']['checkout_url']);
            }

            // Fallback if PayMongo fails
            Payment::create([
                'tenant_id'      => $tenantId,
                'booking_id'     => $booking->id,
                'amount'         => $this->totalAmount,
                'payment_method' => $this->payment_method,
                'payment_status' => 'unpaid',
            ]);
            return null;
        });

        if ($redirect instanceof \Illuminate\Http\RedirectResponse) {
            return $redirect;
        }

        session()->flash('message', 'Booking confirmed! Thank you for your reservation.');
        return redirect()->route('my-bookings');
    }
};
?>

@push('styles')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&family=Inter:wght@200;300;400;500;600;700&display=swap');

    .booking-page { min-height: 100vh; }
</style>
@endpush

<div class="relative z-10 min-h-screen py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-8 items-start">

        {{-- Main Form Column --}}
        <div class="anim-up">
            <a href="{{ route('tenant.show', $property->tenant->slug) }}" wire:navigate
               class="inline-flex items-center gap-1 text-xs uppercase tracking-wider text-white/50 hover:text-brand-400 transition-colors mb-6">
                ← Back to {{ $property->tenant->name }}
            </a>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-4 h-px bg-brand-500"></span>
                <span class="text-xs tracking-[0.2em] uppercase text-brand-500 font-semibold">Reservation</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-white mb-8">
                Complete Your <em class="italic text-brand-400">Booking</em>
            </h1>

            @if(session()->has('error'))
                <div class="bg-red-500/10 border border-red-400/30 text-red-300 p-4 rounded-xl text-sm mb-6">
                    {{ session('error') }}
                </div>
            @endif

            <form wire:submit.prevent="submit" class="glass-card p-6 md:p-8 space-y-8 anim-up delay-1">

                {{-- 1. Guest Info --}}
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">1</span>
                        <h2 class="font-display text-lg font-medium text-white">Your Details</h2>
                    </div>
                    @auth
                        <div class="glass mb-4 p-4 rounded-xl flex items-center justify-between">
                            <span class="text-sm text-white/70">Booking as <strong class="text-white font-semibold">{{ Auth::user()->name }}</strong> · {{ Auth::user()->email }}</span>
                            <button type="button" onclick="document.getElementById('guest-fields').classList.toggle('hidden')"
                                    class="text-xs font-semibold uppercase tracking-wider text-brand-400 hover:opacity-80">Edit</button>
                        </div>
                        <div id="guest-fields" class="hidden">
                    @else
                        <div id="guest-fields">
                    @endauth
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Full Name *</label>
                                <input type="text" wire:model="customerName"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                @error('customerName') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Email</label>
                                <input type="email" wire:model="customerEmail"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                @error('customerEmail') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Phone</label>
                                <input type="text" wire:model="customerPhone"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                       placeholder="+63 9xx xxx xxxx">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Address</label>
                                <input type="text" wire:model="customerAddress"
                                       class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 2. Stay Dates --}}
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">2</span>
                        <h2 class="font-display text-lg font-medium text-white">Visit Dates</h2>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Check-in *</label>
                            <input type="date" wire:model.live="check_in"
                                   min="{{ now()->format('Y-m-d') }}"
                                   max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('check_in') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-white/50 mb-1">Check-out *</label>
                            <input type="date" wire:model.live="check_out"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   max="{{ now()->addDays(30)->format('Y-m-d') }}"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('check_out') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="mt-4 inline-flex items-center gap-2 bg-brand-500/10 border border-brand-500/20 rounded-full px-4 py-1.5 text-sm text-white/70">
                        <span class="font-display text-lg text-white">{{ $totalDays }}</span> day{{ $totalDays > 1 ? 's' : '' }} selected
                    </div>
                </section>

                {{-- 3. Extra Services --}}
                @if($this->availableServices->isNotEmpty())
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">3</span>
                        <h2 class="font-display text-lg font-medium text-white">Extra Services</h2>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-5">
                        @foreach($this->availableServices as $service)
                            <button type="button" wire:click="addService({{ $service->id }})"
                                    class="glass px-4 py-2 rounded-full text-sm text-white/80 hover:bg-white/10 hover:text-white transition flex items-center gap-2">
                                {{ $service->name }}
                                <span class="text-brand-400 font-semibold">+₱{{ number_format($service->price) }}</span>
                            </button>
                        @endforeach
                    </div>
                    @if(count($selectedServices))
                        <div class="glass p-4 rounded-xl">
                            <table class="w-full text-sm">
                                <thead class="text-xs uppercase tracking-wider text-white/40 border-b border-white/10">
                                    <tr><th class="pb-2 text-left">Service</th><th class="pb-2 text-center">Qty</th><th class="pb-2 text-right">Subtotal</th><th></th></tr>
                                </thead>
                                <tbody class="text-white/80">
                                    @foreach($selectedServices as $serviceId => $qty)
                                        @php $svc = App\Models\Service::find($serviceId); @endphp
                                        @if($svc)
                                            <tr class="border-b border-white/5">
                                                <td class="py-2">{{ $svc->name }}</td>
                                                <td class="text-center">{{ $qty }}</td>
                                                <td class="text-right font-medium">₱{{ number_format($svc->price * $qty, 2) }}</td>
                                                <td class="text-center">
                                                    <button wire:click="removeService({{ $serviceId }})"
                                                            class="w-5 h-5 rounded-full border border-red-400/40 text-red-300 hover:bg-red-500 hover:text-white inline-flex items-center justify-center text-xs transition">
                                                        ✕
                                                    </button>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
                @endif

                {{-- 4. Payment --}}
                <section>
                    <div class="flex items-center gap-3 mb-5">
                        <span class="w-7 h-7 rounded-full bg-brand-600 text-white flex items-center justify-center text-xs font-bold">
                            {{ $this->availableServices->isNotEmpty() ? '4' : '3' }}
                        </span>
                        <h2 class="font-display text-lg font-medium text-white">Payment Method</h2>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        @foreach([['cash','Cash','💵'],['gcash','GCash','📱'],['paymaya','PayMaya','💳'],['card','Card','🏦']] as [$val,$label,$icon])
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="payment_method" value="{{ $val }}" class="peer hidden">
                                <div class="glass px-4 py-3 rounded-xl text-sm text-white/60 peer-checked:border-brand-500 peer-checked:bg-brand-500/10 peer-checked:text-white transition">
                                    <span class="mr-1">{{ $icon }}</span> {{ $label }}
                                </div>
                            </label>
                        @endforeach
                    </div>
                    {{-- No reference number input --}}
                </section>

            </form>
        </div>

        {{-- Summary Sidebar --}}
        <div class="lg:sticky lg:top-24 anim-up delay-2">
            <div class="bg-black/60 backdrop-blur-xl border border-white/10 rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-6 border-b border-white/10">
                    <h3 class="font-display text-2xl font-medium text-white">{{ $property->name }}</h3>
                    <p class="text-sm text-white/50">{{ $property->propertyType->name ?? 'Property' }} · {{ $property->tenant->name }}</p>
                    <div class="mt-4 flex items-baseline gap-1">
                        <span class="font-display text-3xl text-brand-400">₱{{ number_format($property->price, 2) }}</span>
                        <span class="text-xs text-white/40">/ day</span>
                    </div>
                </div>
                <div class="p-6 border-b border-white/10 space-y-2 text-sm text-white/70">
                    <div class="flex justify-between">
                        <span>{{ $totalDays }} day{{ $totalDays > 1 ? 's' : '' }}</span>
                        <span class="font-medium text-white">₱{{ number_format($property->price * $totalDays, 2) }}</span>
                    </div>
                    @foreach($selectedServices as $serviceId => $qty)
                        @php $svc = App\Models\Service::find($serviceId); @endphp
                        @if($svc)
                            <div class="flex justify-between">
                                <span>{{ $svc->name }} ×{{ $qty }}</span>
                                <span class="font-medium text-white">₱{{ number_format($svc->price * $qty, 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
                <div class="p-6 flex justify-between items-end">
                    <span class="text-xs font-semibold uppercase tracking-widest text-white/40">Total</span>
                    <span class="font-display text-3xl font-medium text-brand-400">₱{{ number_format($totalAmount, 2) }}</span>
                </div>
                <button wire:click="submit" wire:loading.attr="disabled"
                        class="w-full py-4 bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm uppercase tracking-wider transition disabled:opacity-50">
                    <span wire:loading.remove>
                        <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $payment_method === 'cash' ? 'Confirm Booking' : 'Proceed to Pay' }}
                    </span>
                    <span wire:loading>
                        <svg class="inline-block w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Processing…
                    </span>
                </button>
                <div class="px-6 py-4 text-center text-xs text-white/30 border-t border-white/5">
                    Free cancellation within 24 hours.<br>No charges until confirmed.
                </div>
            </div>
        </div>

    </div>
</div>