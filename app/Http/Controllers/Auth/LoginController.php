<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();

            if ($user && ! $user->is_active) {
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

            // Role-based redirect fallback
            if ($user && $user->hasRole('super-admin')) {
                return redirect()->route('superadmin.dashboard');
            }
            if ($user && $user->hasRole('admin')) {
                return redirect()->route('tenant.dashboard');
            }
            if ($user && $user->tenant_id && $user->getAllPermissions()->count() > 0) {
                return redirect()->route('tenant.employee.dashboard');
            }

            return redirect()->route('home');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    private function isSafeRedirect(string $url): bool
    {
        return str_starts_with($url, '/') || parse_url($url, PHP_URL_HOST) === request()->getHost();
    }
}