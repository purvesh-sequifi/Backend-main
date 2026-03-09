<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClockInClockOutRequest extends FormRequest
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
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'clock_in' => 'required|date',
            'clock_out' => ['required', 'date', 'after:clock_in'], // Ensure clock_out is after clock_in
        ];
    }

    public function messages(): array
    {
        return [
            'clock_out.after' => 'The clock out time must be after the clock in time.',
        ];
    }
}
