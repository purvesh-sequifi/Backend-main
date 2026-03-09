<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * FeatureFlagAuthController
 * 
 * Handles authentication for the feature flags Blade dashboard.
 * Only super admins can access the dashboard.
 */
class FeatureFlagAuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm(): View
    {
        return view('admin.login');
    }

    /**
     * Handle login attempt
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('feature-flags')->attempt($credentials)) {
            $user = Auth::guard('feature-flags')->user();
            
            // Check if user is super admin
            if (!$user->is_super_admin) {
                Auth::guard('feature-flags')->logout();
                return back()->withErrors([
                    'email' => 'Access denied. Super admin privileges required.',
                ]);
            }

            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('feature-flags')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
