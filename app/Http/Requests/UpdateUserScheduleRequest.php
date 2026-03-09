<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserScheduleRequest extends FormRequest
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
            // 'schedule_id'   => 'required|numeric',
            // 'schedule_from' => 'required|date_format:H:i:s|before:schedule_to',
            // 'schedule_to'   => 'required|date_format:H:i:s|after:schedule_from',
            'schedule_from' => 'required|date_format:H:i:s',
            'schedule_to' => ['required', 'date_format:H:i:s', 'after:schedule_from'], // Ensure clock_out is after clock_in
        ];
    }

    public function messages(): array
    {
        return [
            // 'schedule_id.required'      => 'Schedule ID is required',
            // 'schedule_id.numeric'       => 'Schedule ID must be a number',
            // 'schedule_from.required'    => 'Schedule start time is required',
            // 'schedule_from.date_format' => 'Schedule start time must be in the format H:i:s',
            // 'schedule_from.before'      => 'Schedule start time must be before the end time',
            // 'schedule_to.required'      => 'Schedule end time is required',
            // 'schedule_to.date_format'   => 'Schedule end time must be in the format H:i:s',
            'schedule_to.after' => 'Schedule to time must be after the schedule from time',
        ];
    }
}
