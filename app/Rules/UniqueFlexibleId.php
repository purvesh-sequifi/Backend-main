<?php

namespace App\Rules;

use App\Models\UserFlexibleId;
use Illuminate\Contracts\Validation\Rule;

class UniqueFlexibleId implements Rule
{
    protected $excludeUserId;

    protected $flexibleIdValue;

    /**
     * Create a new rule instance.
     *
     * @param  int|null  $excludeUserId  The user ID to exclude from uniqueness check (for updates)
     * @return void
     */
    public function __construct(?int $excludeUserId = null)
    {
        $this->excludeUserId = $excludeUserId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Store the value for error message
        $this->flexibleIdValue = $value;

        // Skip validation if value is empty
        if (empty($value)) {
            return true;
        }

        // Convert value to lowercase for consistent checking (matches the mutator)
        $lowercaseValue = strtolower($value);

        // Check if the flexible ID value already exists (case-insensitive for uniqueness)
        // Only check active (non-soft-deleted) records
        $query = UserFlexibleId::whereRaw('LOWER(flexible_id_value) = ?', [$lowercaseValue])
            ->whereNull('deleted_at'); // Exclude soft-deleted records

        // Exclude ALL of current user's flexible IDs when updating (allows shuffling)
        if ($this->excludeUserId) {
            $query->where('user_id', '!=', $this->excludeUserId);
        }

        $exists = $query->exists();

        // Debug logging for troubleshooting
        \Log::channel('flexible_id_audit')->info('Flexible ID validation', [
            'attribute' => $attribute,
            'original_value' => $value,
            'lowercase_value' => $lowercaseValue,
            'exclude_user_id' => $this->excludeUserId,
            'exists' => $exists,
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings(),
        ]);

        return ! $exists;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return "The flexible ID '{$this->flexibleIdValue}' is already taken. Each flexible ID must be globally unique across the platform.";
    }
}
