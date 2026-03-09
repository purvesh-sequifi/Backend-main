<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAutomationRuleRequest extends FormRequest
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
            'automation_title' => 'required|string|max:255',
            'rule' => 'nullable|array',
            // 'rule.*' => 'sometimes',
            'status' => 'required|integer|in:'.AutomationRule::STATUS_INACTIVE.','.AutomationRule::STATUS_ACTIVE, // Ensure status is 0 (inactive) or 1 (active)
            'user_id' => 'required|integer|exists:users,id',
            'category' => 'required|string|in:'.implode(',', AutomationRule::getCategories()),
        ];
    }

    public function messages(): array
    {
        return [
            'automation_title.required' => 'The automation title is required.',
            'automation_title.max' => 'The automation title may not exceed 255 characters.',
            'rule.array' => 'The rule must be a valid JSON object.',
            'status.required' => 'The status field is required.',
            'status.in' => 'The status must be either 0 (inactive) or 1 (active).',
            'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The user ID must reference a valid user.',
        ];
    }
}
