<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
       return view('public.auth.login-form');
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
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Your account is not yet active. Please wait for approval.',
                ]);
            }

            session()->regenerate();

            // Role‑based redirect
            if ($user->hasRole('super-admin')) {
                return redirect()->route('superadmin.dashboard');
            }
            if ($user->hasRole('admin')) {
                return redirect()->route('tenant.dashboard');
            }
            if ($user->tenant_id && $user->getAllPermissions()->count() > 0) {
                return redirect()->route('tenant.employee.dashboard');
            }
            return redirect()->route('home');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }
}