<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingReceiptController extends Controller
{
    public function show(Request $request, $bookingId)
    {
        /** @var Booking $booking */
        $booking = Booking::withoutGlobalScope(TenantScope::class)->findOrFail($bookingId);

        abort_unless(Auth::id() === $booking->user_id, 403);

        $booking->loadMissing([
            'items'                 => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'items.property'        => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'items.property.tenant' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'services'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'services.service'      => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'payments'              => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
        ]);

        $firstItem = $booking->items->first();
        $property  = $firstItem?->property;
        $tenant    = $property?->tenant;

        return view('public.pages.booking-receipt', [
            'booking'  => $booking,
            'property' => $property,
            'tenant'   => $tenant,
        ]);
    }
}