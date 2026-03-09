<?php

namespace App\Http\Requests\RequestApproval;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {

        // Determine if description is required based on domain
        $requireDescription = in_array(config('app.domain_name'), ['hawx', 'hawxw2']);
        $rules = [
            'adjustment_type_id' => 'required|exists:adjustement_types,id',
        ];
        // dd($requireDescription);

        if ($this->has('adjustment_type_id') && $this->input('adjustment_type_id') == 10) {
            $rules = array_merge($rules, [
                'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
                'customer_pid' => 'required',
            ]);
        } else {
            $rules = array_merge($rules, [
                'document' => 'image|mimes:jpg,png,jpeg,gif,svg|max:2048',
            ]);
        }

        // Add description validation only if domain is hawx or hawxw2
        if ($requireDescription) {
            $rules['description'] = 'required';
        }

        return $rules;
    }

    /**
     * Handle a failed validation attempt.
     *
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['error' => $validator->errors()], 400)
        );
    }
}
