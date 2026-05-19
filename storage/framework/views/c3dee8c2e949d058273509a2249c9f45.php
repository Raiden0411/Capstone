<?php
use Livewire\Component;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
?>

<div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($remainingBalance > 0 && !in_array($booking->status, ['cancelled', 'completed'])): ?>
        <button wire:click="confirmAndPay"
                wire:confirm="Receive cash payment of ₱<?php echo e(number_format($remainingBalance, 2)); ?> and confirm booking?"
                class="bg-brand-600 hover:bg-brand-500 text-white font-semibold py-2.5 px-5 rounded-xl shadow-lg shadow-brand-500/20 transition hover:scale-105 flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Confirm & Pay (Cash)
        </button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/79a764ed.blade.php ENDPATH**/ ?>