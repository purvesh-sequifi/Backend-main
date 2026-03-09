<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserScheduleRequest extends FormRequest
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
            'user_id.*' => 'required|numeric',
            'office_id' => 'required',
            'is_flexible' => 'required|boolean',
            'is_repeat' => 'required|boolean',
            'schedules' => 'required|array',
            'schedules.*.lunch_duration' => 'nullable|string',
            'schedules.*.work_days' => 'required|numeric|in:1,2,3,4,5,6,7',
            // 'schedules.*.schedule_from' => 'required|date_format:H:i:s|before:schedules.*.schedule_to',
            // 'schedules.*.schedule_to' => 'required|date_format:H:i:s|after:schedules.*.schedule_from',
            'schedules.*.schedule_from' => 'required|date_format:Y-m-d H:i:s',
            // 'schedules.*.schedule_to' => 'required|date_format:Y-m-d H:i:s',
            'schedules.*.schedule_to' => 'required|date_format:Y-m-d H:i:s|after_or_equal:schedules.*.schedule_from',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.array' => 'User ID must be an array',
            'user_id.*.numeric' => 'Each User ID must be a number',
            'office_id.required' => 'Office ID is required',
            'office_id.numeric' => 'Office ID must be a number',
            'is_flexible.required' => 'The flexibility status is required',
            'is_flexible.boolean' => 'The flexibility status must be a boolean',
            'is_repeat.required' => 'The repeat status is required',
            'is_repeat.boolean' => 'The repeat status must be a boolean',
            'schedules.required' => 'Schedules are required',
            'schedules.array' => 'Schedules must be an array',
            'schedules.*.lunch_duration.required' => 'Lunch duration is required',
            'schedules.*.lunch_duration.string' => 'Lunch duration must be a string',
            'schedules.*.work_days.required' => 'Work days are required',
            'schedules.*.work_days.numeric' => 'Work days must be a number',
            'schedules.*.work_days.in' => 'Work days must be between 1 and 7',
            'schedules.*.schedule_from.required' => 'Schedule start time is required',
            'schedules.*.schedule_from.date_format' => 'Schedule start time must be in the format H:i:s',
            'schedules.*.schedule_from.before' => 'Schedule start time must be before the end time',
            'schedules.*.schedule_to.required' => 'Schedule end time is required',
            'schedules.*.schedule_to.date_format' => 'Schedule end time must be in the format H:i:s',
            // 'schedules.*.schedule_to.after' => 'Schedule end time must be after the start time',
            'schedules.*.schedule_to.after_or_equal' => 'Schedule end time must be after or equal to the start time',
        ];
    }
}
