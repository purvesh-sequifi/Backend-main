<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArenaStaticToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $staticToken = config('arena.static_token', '1269|W00YCe4q7RB3SpfYtPRrbkuFXytn5mdhvwHibH4g4c0d7bb77');
        $authHeader = $request->header('Authorization');
        if (! $authHeader || $authHeader !== 'Bearer '.$staticToken) {
            // Return an unauthenticated response
            return response()->json(['error' => 'Unauthenticated. Invalid or missing token.'], 401);
        }

        return $next($request);
    }
}
