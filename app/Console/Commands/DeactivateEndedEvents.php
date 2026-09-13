<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateEndedEvents extends Command
{
    protected $signature   = 'events:deactivate-ended
                              {--dry-run : Report what would change without writing}
                              {--grace=0 : Hours of grace after the end date before deactivating}';

    protected $description = 'Set is_active = false for events whose end date has passed';

    public function handle(): int
    {
        $graceHours = max(0, (int) $this->option('grace'));
        $cutoff     = now()->subHours($graceHours);

        $query = Event::withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $cutoff);

        if ($this->option('dry-run')) {
            $events = $query->get(['id', 'name', 'end_date']);

            if ($events->isEmpty()) {
                $this->info('No events to deactivate.');
                return self::SUCCESS;
            }

            $this->table(
                ['ID', 'Name', 'End Date'],
                $events->map(fn ($e) => [$e->id, $e->name, $e->end_date?->format('Y-m-d H:i')])
            );

            $this->info("Would deactivate {$events->count()} event(s). [dry-run]");
            return self::SUCCESS;
        }

        $count = $query->update(['is_active' => false]);

        $this->info("Deactivated {$count} ended event(s).");

        if ($count > 0) {
            Log::info('Auto-deactivated ended events', ['count' => $count]);
        }

        return self::SUCCESS;
    }
}