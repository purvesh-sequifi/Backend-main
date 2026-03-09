<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApiMissingDataValidatedRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // return \Auth::check();
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        switch (request()->method) {
            case 'POST':
                return [
                    'pid' => 'required',
                    'customer_name' => 'required',
                    'customer_state' => 'required',
                    'rep_id' => 'required',
                    'setter_id' => 'required',
                    'rep_email' => 'required',
                    'kw' => 'required',
                ];
            case 'PUT':
                return [

                ];
        }
    }

    /**
     * Get the validation error messages.
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field can not be blank.',
            // 'unique' => 'The :attribute field should be unique.'
            // 'unique' => 'The :attribute field alreay exist.'
        ];
    }
}
