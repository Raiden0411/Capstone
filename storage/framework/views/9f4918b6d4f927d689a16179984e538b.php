<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-8">

    
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Platform Analytics</h1>
        <p class="text-gray-500 dark:text-slate-400 mt-1">
            Aggregated tenant metrics – no individual tenant data is displayed.
        </p>
    </div>

    
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Total Tenants</h3>
            <p class="mt-2 text-3xl font-bold"><?php echo e($this->stats['total_tenants']); ?></p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Active</h3>
            <p class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400"><?php echo e($this->stats['active_tenants']); ?></p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">Pending</h3>
            <p class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400"><?php echo e($this->stats['pending_tenants']); ?></p>
        </div>
        <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-slate-400 uppercase">New This Month</h3>
            <p class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo e($this->stats['new_this_month']); ?></p>
        </div>
    </div>

    
    <div class="rounded-2xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-6 shadow-sm overflow-x-auto">
        <h2 class="text-lg font-semibold mb-4">Tenant Growth</h2>
        <div id="chartWrapper" style="min-width: 600px;">
            <canvas id="tenantGrowthChart" height="150"></canvas>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f6a90942.blade.php ENDPATH**/ ?>