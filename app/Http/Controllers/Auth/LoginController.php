<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm(): View
    {
        /** @var view-string $view */
        $view = 'public.auth.login-form';

        return view($view);
    }

    /**
     * Handle login submission.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->filled('remember'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is not yet active. Please wait for approval.',
            ])->onlyInput('email');
        }

        session()->regenerate();

        $redirect = $request->input('redirect');
        if ($redirect && $this->isSafeRedirect($redirect)) {
            return redirect()->to($redirect);
        }

        return redirect()->route($this->redirectRouteFor($user));
    }

    /**
     * Post-login landing route for an authenticated user.
     *
     * Order matters: platform-level actors first, then tenant admins,
     * then tenant team members. Everything else (tourists, applicants)
     * falls through to the public homepage.
     *
     * Tenant membership is resolved WITHOUT consulting Spatie's ambient
     * team context. At this point in the request lifecycle the context
     * is still the guest sentinel (0) — SetPermissionsTeamId ran before
     * Auth::attempt() promoted the visitor to a user — so
     * $user->getAllPermissions() returns an empty set and every tenant
     * employee would misroute to the homepage.
     */
    protected function redirectRouteFor(User $user): string
    {
        if ($user->hasRole('super-admin')) {
            return 'superadmin.dashboard';
        }

        if ($user->hasRole('admin')) {
            return 'tenant.dashboard';
        }

        if ($user->tenant_id && $this->hasTenantAdminAccess($user)) {
            return 'tenant.employee.dashboard';
        }

        return 'home';
    }

    /**
     * Team-agnostic equivalent of the access check performed by
     * IsTenantAdmin::handle(): does the user hold any role or direct
     * permission at their own tenant?
     *
     * Uses direct table reads so the result does not depend on
     * getPermissionsTeamId(). Mirrors the two paths the middleware
     * accepts — a pivot role at the user's team, or a direct pivot
     * permission at the user's team.
     */
    protected function hasTenantAdminAccess(User $user): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        $modelType = $user::class;
        $userId    = $user->getKey();
        $teamId    = $user->tenant_id;

        $hasDirectPermission = DB::table('model_has_permissions')
            ->where('model_id', $userId)
            ->where('model_type', $modelType)
            ->where('team_id', $teamId)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        return DB::table('model_has_roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', $modelType)
            ->where('model_has_roles.team_id', $teamId)
            ->exists();
    }

    private function isSafeRedirect(string $url): bool
    {
        return str_starts_with($url, '/')
            || parse_url($url, PHP_URL_HOST) === request()->getHost();
    }
}