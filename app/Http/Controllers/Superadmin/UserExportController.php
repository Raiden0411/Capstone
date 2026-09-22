<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $search       = (string) $request->input('search', '');
        $roleFilter   = (string) $request->input('roleFilter', '');
        $statusFilter = (string) $request->input('statusFilter', '');
        $sortOption   = (string) $request->input('sortOption', 'latest');

        $filename = 'users-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($search, $roleFilter, $statusFilter, $sortOption): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Role', 'Business', 'Status', 'Created']);

            User::query()
                ->with([
                    'tenant:id,name',
                    'roles:id,name',
                ])
                ->when($search !== '', function ($q) use ($search): void {
                    $s = trim($search);
                    $q->where(function ($sub) use ($s): void {
                        $sub->where('name', 'like', "%{$s}%")
                            ->orWhere('email', 'like', "%{$s}%");
                    });
                })
                ->when($roleFilter !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $roleFilter)))
                ->when($statusFilter !== '', fn ($q) => $q->where('is_active', $statusFilter === 'active'))
                ->when($sortOption === 'name_asc',  fn ($q) => $q->orderBy('name', 'asc'))
                ->when($sortOption === 'name_desc', fn ($q) => $q->orderBy('name', 'desc'))
                ->when($sortOption === 'email_asc', fn ($q) => $q->orderBy('email', 'asc'))
                ->when($sortOption === 'oldest',    fn ($q) => $q->orderBy('created_at', 'asc'))
                ->when($sortOption === 'latest',    fn ($q) => $q->orderBy('created_at', 'desc'))
                ->chunk(500, function ($users) use ($out): void {
                    foreach ($users as $u) {
                        fputcsv($out, [
                            $u->id,
                            $u->name,
                            $u->email,
                            $u->roles->pluck('name')->implode(', ') ?: 'No role',
                            $u->tenant->name ?? 'Platform Level',
                            $u->is_active ? 'Active' : 'Inactive',
                            $u->created_at->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}