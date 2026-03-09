<?php

namespace App\Models;

use App\Core\Traits\SpatieLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaleMasterProcess extends Model
{
    use HasFactory, SpatieLogsActivity;

    protected $table = 'sale_master_process';

    protected $fillable = [
        'sale_master_id',
        'pid',
        'closer1_id',
        'closer2_id',
        'setter1_id',
        'setter2_id',
        'closer1_m1',
        'closer2_m1',
        'setter1_m1',
        'setter2_m1',
        'closer1_m2',
        'closer2_m2',
        'setter1_m2',
        'setter2_m2',
        'closer1_m1_paid_status',
        'closer2_m1_paid_status',
        'setter1_m1_paid_status',
        'setter2_m1_paid_status',
        'closer1_m2_paid_status',
        'closer2_m2_paid_status',
        'setter1_m2_paid_status',
        'setter2_m2_paid_status',
        'closer1_m1_paid_date',
        'closer2_m1_paid_date',
        'setter1_m1_paid_date',
        'setter2_m1_paid_date',
        'closer1_m2_paid_date',
        'closer2_m2_paid_date',
        'setter1_m2_paid_date',
        'setter2_m2_paid_date',
        'mark_account_status_id',
        'job_status',
        'pid_status',
        'updated_at',
    ];

    protected $hidden = [
        'created_at',
    ];

    public function setter1Detail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'setter1_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id')->with('office', 'reconciliations');
    }

    public function setter2Detail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'setter2_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id')->with('office', 'reconciliations');
    }

    public function closer1Detail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'closer1_id')->select('id', 'first_name', 'last_name', 'email', 'image', 'office_id', 'sub_position_id')->with('office', 'reconciliations');
    }

    public function closer2Detail(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'closer2_id')->select('id', 'first_name', 'last_name', 'email', 'office_id', 'sub_position_id')->with('office', 'reconciliations');
    }

    public function salesDetail(): HasOne
    {
        return $this->hasOne(\App\Models\SalesMaster::class, 'pid', 'pid')->select('id', 'customer_name', 'pid', 'sales_rep_email', 'm1_date', 'm2_date');
    }

    public function status(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'mark_account_status_id')->select('id', 'account_status');
    }

    public function status1(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'pid_status')->select('id', 'account_status');
    }

    public function closer1m1paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'closer1_m1_paid_status')->select('id', 'account_status');
    }

    public function closer2m1paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'closer2_m1_paid_status')->select('id', 'account_status');
    }

    public function setter1m1paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'setter1_m1_paid_status')->select('id', 'account_status');
    }

    public function setter2m1paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'setter2_m1_paid_status')->select('id', 'account_status');
    }

    public function closer1m2paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'closer1_m2_paid_status')->select('id', 'account_status');
    }

    public function closer2m2paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'closer2_m2_paid_status')->select('id', 'account_status');
    }

    public function setter1m2paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'setter1_m2_paid_status')->select('id', 'account_status');
    }

    public function Setter2m2paidstatus(): HasOne
    {
        return $this->hasOne(\App\Models\MarkAccountStatus::class, 'id', 'setter2_m2_paid_status')->select('id', 'account_status');
    }

    public function closer1ReconcilationWithholds(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationWithholding::class, 'closer_id', 'closer1_id')->select('pid', 'closer_id', 'withhold_amount', 'status', 'created_at', 'updated_at');
    }

    public function closer2ReconcilationWithholds(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationWithholding::class, 'closer_id', 'closer2_id')->select('pid', 'closer_id', 'withhold_amount', 'status', 'created_at', 'updated_at');
    }

    public function setter1ReconcilationWithholds(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationWithholding::class, 'setter_id', 'setter1_id')->select('pid', 'setter_id', 'withhold_amount', 'status', 'created_at', 'updated_at');
    }

    public function setter2ReconcilationWithholds(): HasOne
    {
        return $this->hasOne(\App\Models\UserReconciliationWithholding::class, 'setter_id', 'sette2_id')->select('pid', 'setter_id', 'withhold_amount', 'status', 'created_at', 'updated_at');
    }

    public function user(): HasOne
    {
        return $this->hasOne(\App\Models\User::class, 'id', 'user_id');
    }
}
