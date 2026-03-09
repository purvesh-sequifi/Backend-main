<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Core\Traits\SpatieLogsActivity;

class OverrideArchive extends Model
{
    use HasFactory, SpatieLogsActivity;
    
    protected $table = 'override_archive';
    
    protected $fillable = [
        'original_id', 'override_type', 'user_id', 'sale_user_id', 'pid', 'type',
        'kw', 'amount', 'overrides_amount', 'overrides_type', 'pay_period_from',
        'pay_period_to', 'overrides_settlement_type', 'status', 'office_id',
        'calculated_redline', 'calculated_redline_type',
        
        // Normal override fields
        'payroll_id', 'product_id', 'during', 'product_code', 'net_epc', 'comment',
        'adjustment_amount', 'customer_signoff', 'is_mark_paid', 'is_next_payroll',
        'is_displayed', 'ref_id', 'is_move_to_recon', 'recon_status',
        'is_onetime_payment', 'one_time_payment_id', 'worker_type',
        
        // Projection override fields
        'customer_name', 'override_over', 'total_override', 'is_stop_payroll', 'date',
        
        // Archive fields
        'deleted_at', 'deleted_by', 'deletion_reason', 'original_pay_period_from',
        'original_pay_period_to', 'can_restore', 'restoration_pay_period_from',
        'restoration_pay_period_to',
        'custom_sales_field_id', // Custom Sales Field feature
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'pay_period_from' => 'date',
        'pay_period_to' => 'date',
        'original_pay_period_from' => 'date',
        'original_pay_period_to' => 'date',
        'restoration_pay_period_from' => 'date',
        'restoration_pay_period_to' => 'date',
        'can_restore' => 'boolean',
        'override_type' => 'string'
    ];

    // Relationships
    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function saleUser()
    {
        return $this->belongsTo(User::class, 'sale_user_id');
    }

    public function userInfo()
    {
        return $this->hasOne(User::class, 'id', 'sale_user_id')
            ->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin');
    }

    public function overrideUser()
    {
        return $this->hasOne(User::class, 'id', 'user_id')
            ->select('id', 'first_name', 'last_name', 'position_id', 'image', 'sub_position_id', 'is_manager', 'is_super_admin');
    }

    public function userDetail()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->select('id', 'first_name', 'last_name', 'image', 'position_id', 'sub_position_id', 'is_super_admin', 'is_manager', 'terminate', 'dismiss')
            ->with('parentPositionDetail');
    }

    // Scopes
    public function scopeNormalOverrides($query)
    {
        return $query->where('override_type', 'normal');
    }

    public function scopeProjectionOverrides($query)
    {
        return $query->where('override_type', 'projection');
    }

    public function scopeRestorable($query)
    {
        return $query->where('can_restore', 1)->where('status', 1);
    }

    public function scopeForPid($query, $pid)
    {
        return $query->where('pid', $pid);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSaleUser($query, $saleUserId)
    {
        return $query->where('sale_user_id', $saleUserId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecentlyDeleted($query, $days = 30)
    {
        return $query->where('deleted_at', '>=', now()->subDays($days));
    }

    /**
     * Get the custom sales field for this archived override
     */
    public function customSalesField()
    {
        return $this->belongsTo(Crmcustomfields::class, 'custom_sales_field_id');
    }
}
