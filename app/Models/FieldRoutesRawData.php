<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldRoutesRawData extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'FieldRoutes_Raw_Data';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'subscription_id',
        'customer_id',
        'bill_to_account_id',
        'office_id_fr',
        'office_id',
        'office_name',
        'employee_id',
        'employee_name',
        'sequifi_id',
        'sold_by',
        'sold_by_2',
        'sold_by_3',
        'preferred_tech',
        'added_by',
        'active',
        'active_text',
        'frequency',
        'billing_frequency',
        'agreement_length',
        'contract_added',
        'on_hold',
        'service_id',
        'service_type',
        'followup_service',
        'annual_recurring_services',
        'template_type',
        'parent_id',
        'duration',
        'initial_quote',
        'initial_discount',
        'initial_service_total',
        'yif_discount',
        'recurring_charge',
        'contract_value',
        'annual_recurring_value',
        'max_monthly_charge',
        'initial_billing_date',
        'next_billing_date',
        'billing_terms_days',
        'autopay_payment_profile_id',
        'lead_id',
        'lead_date_added',
        'lead_updated',
        'lead_added_by',
        'lead_source_id',
        'lead_source',
        'lead_status',
        'lead_status_text',
        'lead_stage_id',
        'lead_stage',
        'lead_assigned_to',
        'lead_date_assigned',
        'lead_value',
        'lead_date_closed',
        'lead_lost_reason',
        'lead_lost_reason_text',
        'source_id',
        'source',
        'sub_source_id',
        'sub_source',
        'next_service',
        'last_completed',
        'next_appointment_due_date',
        'last_appointment',
        'preferred_days',
        'preferred_start',
        'preferred_end',
        'call_ahead',
        'seasonal_start',
        'seasonal_end',
        'custom_schedule_id',
        'initial_appointment_id',
        'initial_status',
        'initial_status_text',
        'appointment_ids',
        'completed_appointment_ids',
        'sentricon_connected',
        'sentricon_site_id',
        'region_id',
        'capacity_estimate',
        'unit_ids',
        'add_ons',
        'renewal_frequency',
        'renewal_date',
        'custom_date',
        'expiration_date',
        'initial_invoice',
        'po_number',
        'recurring_ticket',
        'date_cancelled',
        'cancellation_notes',
        'cancelled_by',
        'subscription_link',
        'date_added',
        'date_updated_fr',
        'subscription_data',
        'customer_data',
        'appointment_data',
        'sync_status',
        'last_synced_at',
        'last_modified',
        'sync_batch_id',
        'sync_notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'contract_added' => 'date',
        'initial_billing_date' => 'date',
        'next_billing_date' => 'date',
        'lead_date_added' => 'date',
        'lead_updated' => 'datetime',
        'lead_date_assigned' => 'date',
        'lead_date_closed' => 'date',
        'next_service' => 'date',
        'last_completed' => 'date',
        'next_appointment_due_date' => 'datetime',
        'last_appointment' => 'datetime',
        'seasonal_start' => 'date',
        'seasonal_end' => 'date',
        'renewal_date' => 'date',
        'custom_date' => 'date',
        'expiration_date' => 'date',
        'date_cancelled' => 'date',
        'date_added' => 'datetime',
        'date_updated_fr' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_modified' => 'datetime',
        'subscription_data' => 'array',
        'customer_data' => 'array',
        'appointment_data' => 'array',
        'appointment_ids' => 'array',
        'completed_appointment_ids' => 'array',
        'unit_ids' => 'array',
        'add_ons' => 'array',
        'recurring_ticket' => 'array',
        'on_hold' => 'boolean',
        'call_ahead' => 'boolean',
        'sentricon_connected' => 'boolean',
        'initial_quote' => 'decimal:2',
        'initial_discount' => 'decimal:2',
        'initial_service_total' => 'decimal:2',
        'yif_discount' => 'decimal:2',
        'recurring_charge' => 'decimal:2',
        'contract_value' => 'decimal:2',
        'annual_recurring_value' => 'decimal:2',
        'max_monthly_charge' => 'decimal:2',
        'lead_value' => 'decimal:2',
        'capacity_estimate' => 'decimal:2',
    ];

    /**
     * Get the office this subscription belongs to.
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'office_id', 'name');
    }

    /**
     * Get the employee data for this subscription.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'employee_id', 'employee_id');
    }

    /**
     * Get the customer associated with this subscription.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(FieldRoutesCustomerData::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the appointments associated with this subscription.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(FieldRoutesAppointmentData::class, 'subscription_id', 'subscription_id');
    }

    /**
     * Scope for active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Scope for subscriptions by office.
     */
    public function scopeByOffice($query, $officeName)
    {
        return $query->where('office_name', $officeName);
    }

    /**
     * Scope for subscriptions by employee.
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope for subscriptions by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date_added', [$startDate, $endDate]);
    }

    /**
     * Scope for recent syncs.
     */
    public function scopeRecentSync($query, $hours = 24)
    {
        return $query->where('last_synced_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the status emoji based on active value.
     */
    public function getStatusEmojiAttribute()
    {
        return match ($this->active) {
            1 => '✅',
            0 => '🟡',
            -3 => '🔵',
            default => '❓'
        };
    }

    /**
     * Get the formatted status text.
     */
    public function getStatusTextAttribute()
    {
        return match ($this->active) {
            1 => 'Active',
            0 => 'Frozen',
            -3 => 'Lead',
            default => 'Unknown'
        };
    }
}
