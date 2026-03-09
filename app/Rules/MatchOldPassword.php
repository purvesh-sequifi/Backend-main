<?php

namespace App\Rules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class MatchOldPassword implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        $user = User::where('email', request()->input('current_email'))->first();

        return Hash::check($value, $user->password);
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'The :attribute is not match with old password.';
    }
}
