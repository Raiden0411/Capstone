<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleExportController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);

        $search   = (string) $request->input('search', '');
        $filename = 'roles-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($search): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Role Name', 'Permissions Count', 'Protected System Role']);

            Role::query()
                ->where('guard_name', 'web')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->withCount('permissions')
                ->orderBy('name')
                ->chunk(200, function ($roles) use ($out): void {
                    foreach ($roles as $role) {
                        fputcsv($out, [
                            $role->id,
                            $role->name,
                            $role->permissions_count,
                            $role->name === 'super-admin' ? 'Yes' : 'No',
                        ]);
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}