<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FrEmployeeData extends Model
{
    protected $table = 'fr_employee_data';

    protected $fillable = [
        'employee_id',
        'office_id',
        'office_name',
        'active',
        'fname',
        'lname',
        'initials',
        'nickname',
        'type',
        'phone',
        'email',
        'username',
        'experience',
        'pic',
        'employee_link',
        'license_number',
        'supervisor_id',
        'roaming_rep',
        'regional_manager_office_ids',
        'sequifi_id',
        'last_login',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'status',
        'hire_date',
        'termination_date',
        'roles',
        'permissions',
        'additional_data',
        'primary_team',
        'access_control_profile_id',
        'start_address',
        'start_city',
        'start_state',
        'start_zip',
        'start_lat',
        'start_lng',
        'end_address',
        'end_city',
        'end_state',
        'end_zip',
        'end_lat',
        'end_lng',
    ];

    protected $casts = [
        'active' => 'boolean',
        'roaming_rep' => 'boolean',
        'linked_employee_ids' => 'array',
        'regional_manager_office_ids' => 'array',
        'roles' => 'array',
        'permissions' => 'array',
        'additional_data' => 'array',
        'start_lat' => 'decimal:6',
        'start_lng' => 'decimal:6',
        'end_lat' => 'decimal:6',
        'end_lng' => 'decimal:6',
        'last_login' => 'datetime',
    ];

    /**
     * Get the Sequifi user associated with this FieldRoutes employee.
     */
    public function sequifiUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sequifi_id');
    }

    /**
     * Mutator: ensure email is trimmed when set.
     * Trims unicode whitespace from both ends and converts empty string to null.
     */
    public function setEmailAttribute($value)
    {
        if (is_string($value)) {
            // Remove zero-width and BOM-like characters
            $clean = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u', '', $value);
            // Trim any kind of unicode whitespace around
            $clean = preg_replace('/^[\p{Z}\s\h\v]+|[\p{Z}\s\h\v]+$/u', '', $clean);
            $this->attributes['email'] = ($clean === '') ? null : $clean;
        } else {
            $this->attributes['email'] = $value ?: null;
        }
    }
}
