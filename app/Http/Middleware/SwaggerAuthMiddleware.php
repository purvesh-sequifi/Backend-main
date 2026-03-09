<?php

namespace App\Http\Middleware;

use App\Models\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SwaggerAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('api-key') ?? $request['api-key'];
        if (! $apiKey) {
            return response()->json(['message' => 'API key is missing.'], 401);
        }

        $setting = Settings::where('key', 'SWAGGER_API_KEY')->first();

        if (! $setting) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($setting->value != $apiKey) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
