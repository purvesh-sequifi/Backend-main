<?php

namespace App\Http\Requests\ExternalApi;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PayrollReportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'type' => 'nullable|string',
            'search' => 'nullable|string',
            'perpage' => 'nullable|integer|max:1000',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $startDate = $this->input('start_date');
            $endDate = $this->input('end_date');

            if ($startDate && $endDate) {
                $start = Carbon::parse($startDate);
                $end = Carbon::parse($endDate);

                if ($start->diffInDays($end) > 30) {
                    $validator->errors()->add(
                        'end_date',
                        'The difference between start date and end date cannot exceed 30 days.'
                    );
                }
            }
        });
    }
}
