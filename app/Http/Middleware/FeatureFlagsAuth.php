<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FeatureFlagsAuth Middleware
 * 
 * Protects admin dashboard routes.
 * Requires authenticated super admin in all environments.
 */
class FeatureFlagsAuth
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated with feature-flags guard
        if (!Auth::guard('feature-flags')->check()) {
            return redirect()->route('admin.login')
                ->with('error', 'Please login to access this page.');
        }

        // Require super admin privileges in all environments
        $user = Auth::guard('feature-flags')->user();
        if (!$user->is_super_admin) {
            Auth::guard('feature-flags')->logout();
            return redirect()->route('admin.login')
                ->with('error', 'Access denied. Super admin privileges required.');
        }

        return $next($request);
    }
}
