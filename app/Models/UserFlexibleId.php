<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class UserFlexibleId extends Model
{
    use HasFactory, SoftDeletes, SpatieLogsActivity;

    protected $table = 'user_flexible_ids';

    // Constants for flexible ID types
    const TYPE_FLEXI_ID_1 = 'flexi_id_1';

    const TYPE_FLEXI_ID_2 = 'flexi_id_2';

    const TYPE_FLEXI_ID_3 = 'flexi_id_3';

    const VALID_TYPES = [
        self::TYPE_FLEXI_ID_1,
        self::TYPE_FLEXI_ID_2,
        self::TYPE_FLEXI_ID_3,
    ];

    protected $fillable = [
        'user_id',
        'flexible_id_type',
        'flexible_id_value',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the user that owns the flexible ID.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who created this flexible ID.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this flexible ID.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the user who deleted this flexible ID.
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Set flexible_id_value attribute to lowercase and trim whitespace.
     * Manager requirement: All flexible IDs must be stored in lowercase and without leading/trailing spaces.
     */
    public function setFlexibleIdValueAttribute($value)
    {
        // Validate input type
        if (! is_string($value) && ! is_null($value) && $value !== '') {
            throw new \InvalidArgumentException('Flexible ID value must be a string or null');
        }

        // Validate length (database column is typically 255 chars)
        if (is_string($value) && strlen($value) > 255) {
            throw new \InvalidArgumentException('Flexible ID value cannot exceed 255 characters');
        }

        // Normalize: lowercase and trim whitespace
        $this->attributes['flexible_id_value'] = ! empty($value) ? strtolower(trim($value)) : $value;
    }

    /**
     * Boot the model and set up automatic audit logging.
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically set created_by when creating
        static::creating(function ($model) {
            // Check for duplicate user_id + flexible_id_type combination (excluding soft-deleted)
            $existingActive = self::where('user_id', $model->user_id)
                ->where('flexible_id_type', $model->flexible_id_type)
                ->whereNull('deleted_at')
                ->first();

            if ($existingActive) {
                throw new \Exception("User already has an active flexible ID of type '{$model->flexible_id_type}'. Only one per type is allowed.");
            }

            // CRITICAL: Check for global uniqueness of flexible_id_value (case-insensitive)
            // Flexible IDs must be globally unique across all users and types
            if (! empty($model->flexible_id_value)) {
                $normalizedValue = strtolower(trim($model->flexible_id_value));
                $duplicateValue = self::whereRaw('LOWER(TRIM(flexible_id_value)) = ?', [$normalizedValue])
                    ->whereNull('deleted_at')
                    ->first();

                if ($duplicateValue) {
                    $existingUser = $duplicateValue->user;
                    $userName = $existingUser ? "{$existingUser->first_name} {$existingUser->last_name} (ID: {$existingUser->id})" : "User ID: {$duplicateValue->user_id}";
                    throw new \Exception("Flexible ID '{$model->flexible_id_value}' is already in use by {$userName}. Flexible IDs must be globally unique across the platform.");
                }
            }

            if (! $model->created_by && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (! $model->updated_by && auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        // Automatically set updated_by when updating
        static::updating(function ($model) {
            // Check for global uniqueness when updating flexible_id_value
            if ($model->isDirty('flexible_id_value') && ! empty($model->flexible_id_value)) {
                $normalizedValue = strtolower(trim($model->flexible_id_value));
                $duplicateValue = self::whereRaw('LOWER(TRIM(flexible_id_value)) = ?', [$normalizedValue])
                    ->where('id', '!=', $model->id) // Exclude current record
                    ->whereNull('deleted_at')
                    ->first();

                if ($duplicateValue) {
                    $existingUser = $duplicateValue->user;
                    $userName = $existingUser ? "{$existingUser->first_name} {$existingUser->last_name} (ID: {$existingUser->id})" : "User ID: {$duplicateValue->user_id}";
                    throw new \Exception("Flexible ID '{$model->flexible_id_value}' is already in use by {$userName}. Flexible IDs must be globally unique across the platform.");
                }
            }

            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        // Automatically set deleted_by when soft deleting
        static::deleting(function ($model) {
            if ($model->isForceDeleting()) {
                // Hard delete - don't set deleted_by
                return;
            }

            if (auth()->check()) {
                $model->deleted_by = auth()->id();
            }

            // IMPORTANT: Keep flexible_id_value when soft deleting
            // The creating/updating events already exclude soft-deleted records (whereNull('deleted_at'))
            // This prevents unique constraint violations during soft delete
        });

        // Use 'deleted' event to ensure deleted_by is persisted after soft delete
        static::deleted(function ($model) {
            if ($model->isForceDeleting()) {
                return;
            }

            // If deleted_by was set in the deleting event, persist it now
            // Query database directly to check current persisted state (getOriginal() is unreliable after soft delete)
            if ($model->deleted_by) {
                $currentRecord = DB::table('user_flexible_ids')
                    ->where('id', $model->id)
                    ->select('deleted_by')
                    ->first();

                // Only update if deleted_by is not yet persisted in the database
                if ($currentRecord && is_null($currentRecord->deleted_by)) {
                    DB::table('user_flexible_ids')
                        ->where('id', $model->id)
                        ->update(['deleted_by' => $model->deleted_by]);
                }
            }
        });
    }

    /**
     * Scope to filter by flexible ID type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('flexible_id_type', $type);
    }

    /**
     * Scope to filter by flexible ID value (case-insensitive).
     * Only includes active (non-soft-deleted) records.
     */
    public function scopeByValue($query, $value)
    {
        return $query->whereRaw('LOWER(flexible_id_value) = LOWER(?)', [$value])
            ->whereNull('deleted_at');
    }

    /**
     * Find user by any flexible ID value.
     */
    public static function findUserByFlexibleId($value)
    {
        $flexibleId = self::byValue($value)->first();

        return $flexibleId ? $flexibleId->user : null;
    }

    /**
     * Check if a flexible ID value already exists (case-insensitive).
     * Only checks active (non-soft-deleted) records.
     */
    public static function valueExists($value, $excludeId = null)
    {
        $query = self::whereRaw('LOWER(flexible_id_value) = LOWER(?)', [$value])
            ->whereNull('deleted_at'); // Exclude soft-deleted records

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Get the display name for the flexible ID type.
     */
    public function getTypeDisplayNameAttribute()
    {
        return match ($this->flexible_id_type) {
            self::TYPE_FLEXI_ID_1 => 'Flexi ID 1',
            self::TYPE_FLEXI_ID_2 => 'Flexi ID 2',
            self::TYPE_FLEXI_ID_3 => 'Flexi ID 3',
            default => 'Unknown Type',
        };
    }
}
