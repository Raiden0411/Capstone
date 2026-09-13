<?php

namespace App\Jobs;

use App\Services\PayMongoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPayMongoPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 5;
    public int $timeout = 30;
    public array $backoff = [10, 30, 60, 120];

    public function __construct(public string $sessionId) {}

    public function handle(PayMongoService $payMongoService): void
    {
        try {
            $payMongoService->processPayment($this->sessionId);
        } catch (Throwable $e) {
            Log::error('PayMongo payment processing failed', [
                'session_id' => $this->sessionId,
                'exception'  => $e,
            ]);

            throw $e; // Let the queue retry.
        }
    }

    public function failed(Throwable $e): void
    {
        Log::critical('PayMongo payment job permanently failed', [
            'session_id' => $this->sessionId,
            'error'      => $e->getMessage(),
        ]);
    }
}