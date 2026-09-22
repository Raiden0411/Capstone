<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserModeController extends Controller
{
    /**
     * Switch the authenticated user's active_mode between `tourist` and
     * `business`. Only available to dual-role accounts (business owners).
     */
    public function switch(Request $request): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless(
            $user->canSwitchModes(),
            403,
            'Mode switching is not available for this account.'
        );

        $target = (string) $request->input('mode');

        if (! in_array($target, [User::MODE_TOURIST, User::MODE_BUSINESS], true)) {
            return back()->with('error', 'Invalid mode requested.');
        }

        // Only persist + flash a message when the mode actually changes.
        if ($user->active_mode !== $target) {
            $user->update(['active_mode' => $target]);
        }

        return $target === User::MODE_BUSINESS
            ? redirect()->route('tenant.dashboard')->with('message', 'Switched to Business mode.')
            : redirect()->route('home')->with('message', 'Switched to Tourist mode.');
    }
}