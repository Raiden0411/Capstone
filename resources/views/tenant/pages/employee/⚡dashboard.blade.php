<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new
#[Layout('tenant.layouts.app')]
#[Title('My Dashboard')]
class extends Component {};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="font-display text-3xl md:text-4xl font-bold text-white">
            Welcome, {{ auth()->user()->name }}
        </h1>
        <p class="text-white/60 mt-1">Here’s a quick overview of what you can do today.</p>
    </div>

    {{-- Permission‑aware cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">

        {{-- Bookings --}}
        @can('view bookings')
        <a href="{{ route('tenant.bookings.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Bookings</h2>
            <p class="text-xs text-white/60">View & manage reservations</p>
        </a>
        @endcan

        {{-- New Customer --}}
        @can('view customers')
        <a href="{{ route('tenant.customers.create') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">New Customer</h2>
            <p class="text-xs text-white/60">Register a walk‑in guest</p>
        </a>
        @endcan

        {{-- Properties --}}
        @can('view properties')
        <a href="{{ route('tenant.properties.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Properties</h2>
            <p class="text-xs text-white/60">Browse room inventory</p>
        </a>
        @endcan

        {{-- Payments --}}
        @can('view payments')
        <a href="{{ route('tenant.payments.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Payments</h2>
            <p class="text-xs text-white/60">View payment history</p>
        </a>
        @endcan

        {{-- Services --}}
        @can('view services')
        <a href="{{ route('tenant.services.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Services</h2>
            <p class="text-xs text-white/60">Manage add‑on services</p>
        </a>
        @endcan

        {{-- Employees --}}
        @can('view employees')
        <a href="{{ route('tenant.employees.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Employees</h2>
            <p class="text-xs text-white/60">Manage team access</p>
        </a>
        @endcan

        {{-- Analytics --}}
        @can('view analytics')
        <a href="{{ route('tenant.analytics.index') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3v18h18"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l4-4 4 4 5-5"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h1l4 4 4-4h1"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Analytics</h2>
            <p class="text-xs text-white/60">Revenue & occupancy stats</p>
        </a>
        @endcan

        {{-- Tourist Spot Profile (every employee can see this) --}}
        <a href="{{ route('tenant.settings.overview') }}" wire:navigate
           class="glass-card !rounded-xl p-6 text-white hover:bg-white/10 transition flex flex-col items-center text-center gap-3 group">
            <div class="w-12 h-12 rounded-xl bg-brand-500/20 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold">Spot Profile</h2>
            <p class="text-xs text-white/60">Edit public business page</p>
        </a>

    </div>
</div>