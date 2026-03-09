<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use App\Jobs\ProcessDeductionRecalculation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class UserDeduction extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'user_deduction';

    protected $fillable = [
        'user_id',
        'position_id',
        'sub_position_id',
        'deduction_type',
        'cost_center_name',
        'cost_center_id',
        'ammount_par_paycheck',
        'deduction_setting_id',
        'effective_date',
        'is_deleted',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static function boot()
    {

        // Call the parent class's boot method
        parent::boot();

        static::updated(function ($user_deduction) {

            Log::info('£££££££££££££££££££££££££');
            Log::info($user_deduction->user_id);

            $Payroll = Payroll::where('user_id', $user_deduction->user_id)->first();

            Log::info('$Payroll');
            Log::info($Payroll);

            // pay_period_from
            // pay_period_to

            if ($Payroll) {
                $pay_period_from = $Payroll->pay_period_from;
                $pay_period_to = $Payroll->pay_period_to;
                ProcessDeductionRecalculation::dispatch($pay_period_from, $pay_period_to);
            }

        });

    }

    public function costcenter(): BelongsTo
    {
        return $this->belongsTo(\App\Models\CostCenter::class, 'cost_center_id', 'id')->select('id', 'name', 'status');
    }
}
