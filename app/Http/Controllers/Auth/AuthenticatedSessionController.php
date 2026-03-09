<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\UsersAdditionalEmail;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Handle an incoming api authentication request.
     */
    public function apiStore(LoginRequest $request): Response
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect'],
            ]);
        }

        $user = User::where('email', $request->email)->first();
        if (empty($user)) {
            $additional_user_id = UsersAdditionalEmail::where('email', $request->email)->value('user_id');
            if (! empty($additional_user_id)) {
                $user = User::where('id', $additional_user_id)->first();
            }
        }

        return response($user);
    }

    /**
     * Verifies user token.
     */
    public function apiVerifyToken(Request $request): Response
    {
        $request->validate([
            'api_token' => 'required',
        ]);

        $user = User::where('api_token', $request->api_token)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['Invalid token'],
            ]);
        }

        return response($user);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
