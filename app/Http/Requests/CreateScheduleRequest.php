<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduleRequest extends FormRequest
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
            'user_id' => 'required|array',
            'office_id' => 'required',
            'schedules' => 'required|array',
            // 'schedules.*.schedule_date' => 'required|date|after_or_equal:2024-01-01',
            // 'schedules.*.day_number' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.day_number' => 'required|string|in:0,1,2,3,4,5,6',
            'schedules.*.clock_in' => 'required|date_format:H:i:s|before:schedules.*.clock_out',
            'schedules.*.clock_out' => 'required|date_format:H:i:s|after:schedules.*.clock_in',
        ];
    }

    public function messages(): array
    {
        return [
            // 'schedules.*.schedule_date.required' => 'The date field is required.',
            'schedules.*.schedule_date.date' => 'The date must be a valid date.',
            // 'schedules.*.schedule_date.after_or_equal' => 'The date must be on or after 2024-01-01.',
            'schedules.*.day_number.required' => 'The day field is required.',
            'schedules.*.day_number.string' => 'The day must be a string.',
            'schedules.*.day_number.in' => 'The day must be one of the following: 1-Monday, 2-Tuesday, 3-Wednesday, 4-Thursday, 5-Friday, 6-Saturday, 7-Sunday.',
            'schedules.*.clock_in.required' => 'The clock in field is required.',
            'schedules.*.clock_in.date_format' => 'The clock in must be in the format H:i:s.',
            'schedules.*.clock_in.before' => 'The clock in must be before clock out.',
            'schedules.*.clock_out.required' => 'The clock out field is required.',
            'schedules.*.clock_out.date_format' => 'The clock out must be in the format H:i:s.',
            'schedules.*.clock_out.after' => 'The clock out must be after clock in.',
        ];
    }
}
