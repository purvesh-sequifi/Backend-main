<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // return $next($request);
        // $user = auth('api')->user();
        $user = auth()->user()->position_id;
        // dd($user);
        if (auth()->user()->status == 'active') {
            return $next($request);
        }

        return response()->json('Your account is inactive');
    }
}
