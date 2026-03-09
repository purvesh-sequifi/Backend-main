<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeIdSetting extends Model
{
    use HasFactory;
    use SpatieLogsActivity;

    protected $table = 'employee_id_setting';

    protected $fillable = [
        'prefix',
        'id_code',
        'id_code_no_to_start_from',
        'onbording_prefix',
        'onbording_id_code',
        'onbording_id_code_no_to_start_from',
        'select_offer_letter_to_send',
        'select_agreement_to_sign',
        'automatic_hiring_status',
        'approval_onboarding_position',
        'require_approval_status',
        'approval_position',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function AdditionalInfoForEmployeeToGetStarted(): HasMany
    {
        return $this->hasMany(\App\Models\AdditionalInfoForEmployeeToGetStarted::class, 'configuration_id', 'id');
    }

    public function EmployeePersonalDetail(): HasMany
    {
        return $this->hasMany(\App\Models\EmployeePersonalDetail::class, 'configuration_id', 'id');
    }

    public function DocumentType(): HasMany
    {
        return $this->hasMany(\App\Models\DocumentType::class, 'configuration_id', 'id');
    }

    public function DocumentToUpdate(): HasMany
    {
        return $this->hasMany(\App\Models\DocumentType::class, 'configuration_id', 'id');
    }

    public function EmployeeAdminOnlyFields(): HasMany
    {
        return $this->hasMany(\App\Models\EmployeeAdminOnlyFields::class, 'configuration_id', 'id');
    }
}
