<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreDocumentSignerRequest extends FormRequest
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
            'signer_id' => ['required', 'exists:onboarding_employees,id'],
            'envelope_id' => ['required', 'exists:envelopes,id'],
            'consent' => ['required', Rule::in(['1', 1])],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'ApiName' => 'storeDocumentSigner',
            'status' => false,
        ], 422)); // You can customize the response status code and message
    }
}
