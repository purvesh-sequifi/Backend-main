<?php

namespace App\Http\Requests\Account;

use App\Rules\MatchOldPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class SettingsPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', new MatchOldPassword],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}
