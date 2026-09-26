<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\UserNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindPermitExpiry extends Command
{
    protected $signature = 'tenants:remind-permit-expiry
                            {--dry-run : Report what would be sent without writing}
                            {--list : Print each tenant and its resolved bucket}';

    protected $description = 'Send escalating reminders to tenant admins as their Mayor\'s Permit approaches expiry';

    public function handle(UserNotificationService $notifications): int
    {
        $today   = now()->startOfDay();
        $dryRun  = (bool) $this->option('dry-run');
        $list    = (bool) $this->option('list');

        $tenants = Tenant::query()
            ->where('is_active', true)
            ->whereNotNull('permit_expires_at')
            ->with([
                'users' => fn ($q) => $q
                    ->whereHas('roles', fn ($r) => $r->where('name', 'admin'))
                    ->select('id', 'tenant_id', 'name', 'email'),
            ])
            ->get();

        $superadmins = User::role('super-admin')->get(['id', 'name', 'email']);

        $summary = [
            'expired'          => 0,
            'expired_notifs'   => 0,
            'expired_sa'       => 0,
            '7d'               => 0,
            '7d_notifs'        => 0,
            '7d_sa'            => 0,
            '30d'              => 0,
            '30d_notifs'       => 0,
            '30d_sa'           => 0,
            '60d'              => 0,
            '60d_notifs'       => 0,
            '60d_sa'           => 0,
            'skipped'          => 0,
            'sent'             => 0,
            'superadmin_sent'  => 0,
        ];

        foreach ($tenants as $tenant) {
            $expiry = $tenant->permit_expires_at;
            if (! $expiry) {
                $summary['skipped']++;
                continue;
            }

            $daysUntil = (int) $today->diffInDays($expiry->copy()->startOfDay(), false);

            $bucket = $this->resolveBucket($daysUntil);
            if ($bucket === null) {
                $summary['skipped']++;
                continue;
            }

            $year = $expiry->year;

            // ── Tenant-admin notification type ──
            $tenantType = "permit_expiry_{$bucket}_{$year}";

            // ── Superadmin notification type ──
            // Prefix `superadmin_` prevents the superadmin's dedup from
            // colliding with a tenant admin's on the same expiry year
            // (a dual-role owner has both scopes on the same user row).
            $superadminType = "permit_expiry_superadmin_{$bucket}_{$year}";

            $tenantPayload     = $this->buildPayload($bucket, $expiry, forSuperadmin: false, tenant: $tenant);
            $superadminPayload = $this->buildPayload($bucket, $expiry, forSuperadmin: true,  tenant: $tenant);

            if ($list) {
                $this->line(sprintf(
                    '  [%s] tenant #%d %s — %d day(s) → %s',
                    $expiry->format('Y-m-d'),
                    $tenant->id,
                    $tenant->name,
                    $daysUntil,
                    $bucket,
                ));
            }

            $bucketNotifs   = 0;
            $bucketSaNotifs = 0;

            // ── Tenant admins ──
            foreach ($tenant->users as $admin) {
                $alreadySent = UserNotification::query()
                    ->forUser($admin->id)
                    ->where('type', $tenantType)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                if (! $dryRun) {
                    $notifications->notify($admin, array_merge($tenantPayload, [
                        'scope' => UserNotification::SCOPE_BUSINESS,
                        'type'  => $tenantType,
                        'url'   => route('tenant.settings.index'),
                    ]));
                }

                $bucketNotifs++;
                $summary['sent']++;
            }

            // ── Superadmins ──
            foreach ($superadmins as $sa) {
                $alreadySent = UserNotification::query()
                    ->forUser($sa->id)
                    ->where('type', $superadminType)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                if (! $dryRun) {
                    $notifications->notify($sa, array_merge($superadminPayload, [
                        'scope' => UserNotification::SCOPE_PLATFORM,
                        'type'  => $superadminType,
                        'url'   => route('superadmin.tenants.preview', $tenant),
                    ]));
                }

                $bucketSaNotifs++;
                $summary['superadmin_sent']++;
            }

            $summary[$bucket]++;
            $summary["{$bucket}_notifs"] += $bucketNotifs;
            $summary["{$bucket}_sa"]     += $bucketSaNotifs;
        }

        $this->newLine();
        $this->table(
            ['Bucket', 'Tenants', 'Tenant Notifs', 'SA Notifs'],
            [
                ['Expired',  $summary['expired'], $summary['expired_notifs'], $summary['expired_sa']],
                ['7 days',   $summary['7d'],      $summary['7d_notifs'],      $summary['7d_sa']],
                ['30 days',  $summary['30d'],     $summary['30d_notifs'],     $summary['30d_sa']],
                ['60 days',  $summary['60d'],     $summary['60d_notifs'],     $summary['60d_sa']],
                ['Skipped',  $summary['skipped'], '—',                        '—'],
                [
                    'TOTAL',
                    $summary['expired'] + $summary['7d'] + $summary['30d'] + $summary['60d'],
                    $summary['sent'],
                    $summary['superadmin_sent'],
                ],
            ]
        );

        $this->info(sprintf(
            '%s %d tenant + %d superadmin notification(s).%s',
            $dryRun ? '[DRY RUN] Would send' : 'Sent',
            $summary['sent'],
            $summary['superadmin_sent'],
            $dryRun ? ' No rows written.' : '',
        ));

        return self::SUCCESS;
    }

    private function resolveBucket(int $daysUntil): ?string
    {
        if ($daysUntil < 0)   return 'expired';
        if ($daysUntil <= 7)  return '7d';
        if ($daysUntil <= 30) return '30d';
        if ($daysUntil <= 60) return '60d';

        return null;
    }

    /**
     * @return array{title: string, message: string, icon: string, color: string}
     */
    private function buildPayload(
        string $bucket,
        Carbon $expiry,
        bool $forSuperadmin,
        Tenant $tenant,
    ): array {
        $date = $expiry->format('M j, Y');
        $name = $tenant->name;

        return match ($bucket) {
            '60d' => [
                'title'   => $forSuperadmin
                    ? "{$name}'s permit expires in 2 months"
                    : 'Permit expires in 2 months',
                'message' => $forSuperadmin
                    ? "{$name}'s Mayor's Permit expires on {$date}. Reach out to confirm they've started renewal."
                    : "Your Mayor's Permit expires on {$date}. Start preparing your renewal documents now.",
                'icon'    => 'clock',
                'color'   => 'amber',
            ],
            '30d' => [
                'title'   => $forSuperadmin
                    ? "{$name}'s permit expires in 1 month"
                    : 'Permit expires in 1 month',
                'message' => $forSuperadmin
                    ? "{$name}'s Mayor's Permit expires on {$date}. Confirm the tenant has scheduled renewal."
                    : "Your Mayor's Permit expires on {$date}. Schedule your renewal appointment soon.",
                'icon'    => 'clock',
                'color'   => 'amber',
            ],
            '7d' => [
                'title'   => $forSuperadmin
                    ? "{$name}'s permit expires in 1 week"
                    : 'Permit expires in 1 week',
                'message' => $forSuperadmin
                    ? "{$name}'s Mayor's Permit expires on {$date}. Final window to act."
                    : "Your Mayor's Permit expires on {$date}. Upload a renewed permit to keep your listing active.",
                'icon'    => 'alert',
                'color'   => 'rose',
            ],
            'expired' => [
                'title'   => $forSuperadmin
                    ? "{$name}'s permit has expired"
                    : "Mayor's Permit expired",
                'message' => $forSuperadmin
                    ? "{$name}'s Mayor's Permit expired on {$date}. Consider whether the listing should remain public."
                    : "Your permit expired on {$date}. Upload a renewed permit to reactivate your listing.",
                'icon'    => 'alert',
                'color'   => 'rose',
            ],
            default => [
                'title'   => $forSuperadmin
                    ? "{$name}'s permit renewal reminder"
                    : 'Permit renewal reminder',
                'message' => $forSuperadmin
                    ? "{$name}'s Mayor's Permit expires on {$date}."
                    : "Your Mayor's Permit expires on {$date}.",
                'icon'    => 'clock',
                'color'   => 'amber',
            ],
        };
    }
}