<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $filename = 'tenants-' . now()->format('Y-m-d-His') . '.csv';
        $search   = (string) $request->input('search', '');
        $status   = (string) $request->input('status', 'all');
        $typeId   = $request->input('type');
        $start    = $request->input('start');
        $end      = $request->input('end');

        return response()->streamDownload(function () use ($search, $status, $typeId, $start, $end): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Business', 'Type', 'Email', 'Contact', 'Address',
                'Admin Name', 'Admin Email', 'Properties', 'Bookings',
                'Status', 'Created',
            ]);

            Tenant::with([
                    'typeOfTenant:id,type',
                    'users:id,tenant_id,name,email',
                ])
                ->withCount(['properties', 'bookings'])
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($sub) use ($search): void {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('contact_number', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
                })
                ->when($status !== 'all' && $status !== '', fn ($q) => $q->where('is_active', $status === 'active'))
                ->when($typeId, fn ($q) => $q->where('type_of_tenant_id', $typeId))
                ->when($start && $end, function ($q) use ($start, $end): void {
                    $s = rescue(fn () => \Carbon\Carbon::parse($start)->startOfDay());
                    $e = rescue(fn () => \Carbon\Carbon::parse($end)->endOfDay());
                    if ($s && $e) {
                        $q->whereBetween('created_at', [$s, $e]);
                    }
                })
                ->orderByDesc('created_at')
                ->chunk(200, function ($tenants) use ($out): void {
                    foreach ($tenants as $t) {
                        $admin = $t->users->first();
                        fputcsv($out, [
                            $t->id,
                            $t->name,
                            $t->typeOfTenant->type ?? '',
                            $t->email,
                            $t->contact_number,
                            $t->address,
                            $admin->name  ?? '',
                            $admin->email ?? '',
                            $t->properties_count,
                            $t->bookings_count,
                            $t->is_active ? 'Active' : 'Pending',
                            $t->created_at?->format('Y-m-d') ?? '',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}