<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Positions extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'positions';

    protected $fillable = [
        'position_name',
        'department_id',
        'parent_id',
        'org_parent_id',
        'group_id',
        'is_manager',
        'is_stack',
        'is_selfgen', // 0 = None, 1 = SelfGen, 2 = Closer, 3 = Setter
        'order_by',
        'offer_letter_template_id',
        'worker_type', // w2, 1099
        'setup_status',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function childPositions(): HasMany
    {
        return $this->hasMany(self::class, 'org_parent_id')->with('childPositions', 'group', 'payFrequency.frequencyType', 'positionDepartmentDetail', 'userDeduction', 'deductionlimit', 'reconciliation')->withcount('peoples');
    }

    public function childPositionsNew(): HasMany
    {
        return $this->hasMany(self::class, 'org_parent_id')->with('childPositionsNew', 'group', 'payFrequency.frequencyType', 'positionDepartmentDetail')->withCount('peoples')->withCount('product');
    }

    public function ChieldPosition(): HasMany
    {
        return $this->hasMany(self::class, 'org_parent_id')->with('ChieldPosition')->withcount('peoples');
    }

    public function subposition(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->with('subposition');
    }

    public function departmentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id');
    }

    public function deducationname(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionDeduction::class, 'position_id', 'id')->with('costcenter');
    }

    public function product(): HasMany
    {
        return $this->hasMany(\App\Models\PositionProduct::class, 'position_id', 'id')->with('productName');
    }

    public function positionTiers(): HasMany
    {
        return $this->hasMany(\App\Models\PositionTier::class, 'position_id', 'id')->with('tiersSchema');
    }

    public function Commission(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommission::class, 'position_id', 'id')->with('tiersRange');
    }

    public function Upfront(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionUpfronts::class, 'position_id', 'id')->with('tiersRange');
    }

    public function deductionname(): HasMany
    {
        return $this->hasMany(\App\Models\PositionCommissionDeduction::class, 'position_id', 'id')->with('costcenter');
    }

    public function Override(): HasMany
    {
        return $this->hasMany(\App\Models\PositionOverride::class, 'position_id', 'id')->with('overridesDetail', 'overridessattlement', 'tiersRange');
    }

    public function OverrideTier(): HasOne
    {
        return $this->hasOne(\App\Models\PositionTierOverride::class, 'position_id', 'id');
    }

    public function positionDepartmentDetail(): HasOne
    {
        return $this->hasOne(\App\Models\Department::class, 'id', 'department_id')->select('id', 'name');
    }

    public function positionOverridesDetail(): HasMany
    {
        return $this->hasMany(\App\Models\PositionOverride::class, 'id')->with('overridesDetail', 'overridessattlement');
    }

    public function people(): HasMany
    {
        return $this->hasMany('App\Models\user', 'position_id', 'id');
    }

    public function deductionlimit(): HasOne
    {
        return $this->hasOne(\App\Models\PositionsDeductionLimit::class, 'position_id', 'id');
    }

    public function reconciliation(): HasMany
    {
        return $this->hasMany(\App\Models\PositionReconciliations::class, 'position_id', 'id');
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(\App\Models\PositionReconciliations::class, 'position_id', 'id');
    }

    public function payFrequency(): HasOne
    {
        return $this->hasOne(\App\Models\PositionPayFrequency::class, 'position_id', 'id')->with('frequencyType');
    }

    public function PayRollByPosition(): HasMany
    {
        return $this->hasMany(\App\Models\Payroll::class, 'position_id', 'id');
    }

    public function group(): HasOne
    {
        return $this->hasOne(\App\Models\GroupMaster::class, 'id', 'group_id');
    }

    public function chields(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->with('chields');
    }

    public function orgChields(): HasMany
    {
        return $this->hasMany(self::class, 'org_parent_id')->with('orgChields')->withcount('peoples');
    }

    public function peoples(): HasMany
    {
        return $this->hasMany('App\Models\user', 'sub_position_id', 'id')->where('dismiss', 0);
    }

    public function userDeduction(): HasMany
    {
        return $this->hasMany(\App\Models\GroupMaster::class, 'id', 'position_id');
    }

    // added by deepak
    public function getAncestorIds()
    {
        $ancestorIds = [$this->org_parent_id];
        $ancestor = $this;
        while ($ancestor = $ancestor->parent) {
            $ancestorIds[] = $ancestor->org_parent_id;
        }

        return $ancestorIds;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Positions::class, 'org_parent_id');
    }

    // use for check that atleast one offer letter template is associated.
    public function offerLetter(): BelongsTo
    {
        return $this->belongsTo(NewSequiDocsTemplate::class, 'offer_letter_template_id');
    }

    public function deductionsetting(): HasOne
    {
        return $this->hasOne(\App\Models\PositionCommissionDeductionSetting::class, 'position_id', 'id');
    }

    public function wage(): HasOne
    {
        return $this->hasOne(\App\Models\Wage::class, 'position_id', 'id');
    }

    public function position_wage(): HasOne
    {
        return $this->hasOne(\App\Models\PositionWage::class, 'position_id', 'id');
    }

    //
    public function allAssociatedOfferLettersWithTemplate(): HasMany
    {
        return $this->hasMany(NewSequiDocsTemplatePermission::class, 'position_id')
            ->where('position_type', 'receipient')
            ->whereHas('NewSequiDocsTemplate')
            ->with(['NewSequiDocsTemplate' => function ($query) {
                $query->where('category_id', 1); // offerletter only
            }]);
        // ->with('NewSequiDocsTemplate');
    }

    public function hirePermission(): HasOne
    {
        return $this->hasOne(PositionHirePermission::class, 'position_id');
    }

    protected static function booted()
    {
        // static::addGlobalScope('notSuperAdmin', function (Builder $builder) {
        //     $builder->where('position_name', '!=', 'Super Admin');
        // });
    }
}
