<?php

namespace App\Http\Requests;

use App\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationRuleRequest extends FormRequest
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
            'status' => 'required|integer|in:0,1',
            'user_id' => 'required|integer|exists:users,id',
            'id' => 'required|integer|exists:automation_rules,id',
            'category' => 'required|string|in:'.implode(',', AutomationRule::getCategories()),
        ];
    }

    public function messages(): array
    {
        return [
            'automation_title.string' => 'The automation title must be a string.',
            'automation_title.max' => 'The automation title may not be greater than 255 characters.',
            'rule.array' => 'The rule must be a valid JSON object.',
            'status.in' => 'The status must be either 0 (inactive) or 1 (active).',
            'user_id.exists' => 'The user ID must exist in the system.',
            'id.exists' => 'The Automation ID must exist in the system.',
        ];
    }
}
