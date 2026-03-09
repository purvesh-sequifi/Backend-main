<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldRoutesAppointmentData extends Model
{
    use HasFactory;

    protected $table = 'FieldRoutes_Appointment_Data';

    protected $fillable = [
        // Primary identifiers
        'appointment_id',
        'office_id_fr',
        'office_id',
        'office_name',

        // Relationship links
        'customer_id',
        'subscription_id',
        'original_appointment_id',

        // Appointment status & core data
        'status',
        'status_text',
        'sales_anchor',

        // Scheduling information
        'scheduled_date',
        'scheduled_time',
        'date_added',
        'date_completed',
        'date_cancelled',
        'date_updated_fr',

        // Time tracking
        'time_in',
        'time_out',
        'check_in',
        'check_out',

        // Weather & environmental
        'wind_speed',
        'wind_direction',
        'temperature',

        // Service information
        'service_id',
        'service_type',
        'target_pests',

        // Route & location
        'route_id',
        'spot_id',

        // Employee/technician assignments
        'employee_id',
        'employee_name',
        'sequifi_id',
        'assigned_tech',
        'assigned_tech_name',
        'serviced_by',
        'serviced_by_name',
        'completed_by',
        'completed_by_name',
        'cancelled_by',
        'cancelled_by_name',
        'additional_techs',

        // Sales information
        'sales_team_id',
        'sales_team_name',

        // Service details
        'service_notes',
        'office_notes',
        'appointment_notes',
        'service_amount',
        'products_used',
        'duration_minutes',
        'payment_method',
        'ticket_id',
        'serviced_interior',
        'do_interior',
        'call_ahead',
        'signed_by_customer',
        'signed_by_tech',

        // Scheduling & organization
        'production_value',
        'due_date',
        'group_id',
        'sequence',
        'locked_by',

        // GPS coordinates
        'lat_in',
        'lat_out',
        'long_in',
        'long_out',

        // Subscription & region
        'subscription_region_id',
        'subscription_preferred_tech',

        // Time window & scheduling
        'time_window',
        'start_time',
        'end_time',

        // Additional data
        'unit_ids',

        // Reason codes
        'cancellation_reason_id',
        'reschedule_reason_id',
        'reserviced_reason_id',

        // Appointment outcomes
        'completion_notes',
        'cancellation_notes',
        'customer_present',
        'customer_satisfaction',

        // Raw data storage
        'appointment_data',

        // Sync metadata
        'sync_status',
        'last_synced_at',
        'last_modified',
        'sync_batch_id',
        'sync_notes',

        // Field-specific timestamps
        'status_changed_at',
        'schedule_changed_at',
        'service_details_changed_at',
        'tech_assignment_changed_at',
        'completion_details_changed_at',

        // Change tracking
        'field_changes',
    ];

    protected $casts = [
        'appointment_id' => 'integer',
        'office_id_fr' => 'integer',
        'customer_id' => 'integer',
        'subscription_id' => 'integer',
        'original_appointment_id' => 'integer',
        'status' => 'integer',
        'sales_anchor' => 'boolean',
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'date_added' => 'datetime',
        'date_completed' => 'datetime',
        'date_cancelled' => 'datetime',
        'date_updated_fr' => 'datetime',

        // Time tracking
        'time_in' => 'datetime',
        'time_out' => 'datetime',
        'check_in' => 'datetime',
        'check_out' => 'datetime',

        // Weather & environmental
        'wind_speed' => 'decimal:2',
        'temperature' => 'decimal:2',

        'service_id' => 'integer',
        'target_pests' => 'array',
        'route_id' => 'integer',
        'spot_id' => 'integer',
        'sequifi_id' => 'integer',
        'assigned_tech' => 'integer',
        'serviced_by' => 'integer',
        'completed_by' => 'integer',
        'cancelled_by' => 'integer',
        'additional_techs' => 'array',
        'sales_team_id' => 'integer',
        'service_amount' => 'decimal:2',
        'duration_minutes' => 'integer',

        // Payment & billing
        'payment_method' => 'integer',
        'ticket_id' => 'integer',

        // Service details
        'serviced_interior' => 'boolean',
        'do_interior' => 'boolean',
        'call_ahead' => 'boolean',
        'signed_by_customer' => 'boolean',
        'signed_by_tech' => 'boolean',

        // Scheduling & organization
        'production_value' => 'decimal:2',
        'due_date' => 'date',
        'group_id' => 'integer',
        'sequence' => 'integer',
        'locked_by' => 'integer',

        // GPS coordinates
        'lat_in' => 'decimal:8',
        'lat_out' => 'decimal:8',
        'long_in' => 'decimal:8',
        'long_out' => 'decimal:8',

        // Subscription & region
        'subscription_region_id' => 'integer',
        'subscription_preferred_tech' => 'integer',

        // Additional data
        'unit_ids' => 'array',

        // Reason codes
        'cancellation_reason_id' => 'integer',
        'reschedule_reason_id' => 'integer',
        'reserviced_reason_id' => 'integer',

        'customer_present' => 'boolean',
        'customer_satisfaction' => 'integer',
        'appointment_data' => 'array',
        'products_used' => 'array',
        'last_synced_at' => 'datetime',
        'last_modified' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',

        // Field-specific timestamps
        'status_changed_at' => 'datetime',
        'schedule_changed_at' => 'datetime',
        'service_details_changed_at' => 'datetime',
        'tech_assignment_changed_at' => 'datetime',
        'completion_details_changed_at' => 'datetime',

        // Change tracking
        'field_changes' => 'array',
    ];

    /**
     * Get the customer associated with this appointment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(FieldRoutesCustomerData::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the subscription associated with this appointment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(FieldRoutesRawData::class, 'subscription_id', 'subscription_id');
    }

    /**
     * Get the assigned technician.
     */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'assigned_tech', 'employee_id');
    }

    /**
     * Get the technician who serviced the appointment.
     */
    public function servicingTechnician(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'serviced_by', 'employee_id');
    }

    /**
     * Get the employee who owns this appointment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'employee_id', 'employee_id');
    }

    /**
     * Get the user who completed the appointment.
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'completed_by', 'employee_id');
    }

    /**
     * Get the user who cancelled the appointment.
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'cancelled_by', 'employee_id');
    }

    /**
     * Get the integration/office for this appointment.
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'office_name', 'description');
    }

    /**
     * Get the original appointment if this was rescheduled.
     */
    public function originalAppointment(): BelongsTo
    {
        return $this->belongsTo(FieldRoutesAppointmentData::class, 'original_appointment_id', 'appointment_id');
    }

    /**
     * Scope to get pending appointments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope to get completed appointments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get no show appointments.
     */
    public function scopeNoShow($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope to get rescheduled appointments.
     */
    public function scopeRescheduled($query)
    {
        return $query->where('status', -2);
    }

    /**
     * Scope to get cancelled appointments.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', -1);
    }

    /**
     * Scope to filter by office.
     */
    public function scopeByOffice($query, $officeName)
    {
        return $query->where('office_name', $officeName);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by subscription.
     */
    public function scopeBySubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    /**
     * Scope to filter by assigned technician.
     */
    public function scopeByTechnician($query, $techId)
    {
        return $query->where('assigned_tech', $techId);
    }

    /**
     * Scope to filter by serviced technician.
     */
    public function scopeServicedBy($query, $techId)
    {
        return $query->where('serviced_by', $techId);
    }

    /**
     * Scope to filter by route.
     */
    public function scopeByRoute($query, $routeId)
    {
        return $query->where('route_id', $routeId);
    }

    /**
     * Scope to filter by scheduled date.
     */
    public function scopeScheduledFor($query, $date)
    {
        return $query->whereDate('scheduled_date', $date);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', today());
    }

    /**
     * Scope to get this week's appointments.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('scheduled_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope to get sales anchor appointments (first for subscription).
     */
    public function scopeSalesAnchor($query)
    {
        return $query->where('sales_anchor', true);
    }

    /**
     * Scope for recent syncs.
     */
    public function scopeRecentSync($query, $hours = 24)
    {
        return $query->where('last_synced_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope to filter by service type.
     */
    public function scopeByServiceType($query, $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    /**
     * Scope to filter by sales team.
     */
    public function scopeBySalesTeam($query, $salesTeamId)
    {
        return $query->where('sales_team_id', $salesTeamId);
    }

    /**
     * Get the appointment status text.
     */
    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            0 => 'Pending',
            1 => 'Completed',
            2 => 'No Show',
            -2 => 'Rescheduled',
            -1 => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Get the appointment status emoji.
     */
    public function getStatusEmojiAttribute()
    {
        return match ($this->status) {
            0 => '⏳',
            1 => '✅',
            2 => '❌',
            -2 => '🔄',
            -1 => '🚫',
            default => '❓'
        };
    }

    /**
     * Get the appointment status with emoji.
     */
    public function getStatusDisplayAttribute()
    {
        return $this->status_emoji.' '.$this->status_text;
    }

    /**
     * Check if appointment is pending.
     */
    public function isPending()
    {
        return $this->status === 0;
    }

    /**
     * Check if appointment is completed.
     */
    public function isCompleted()
    {
        return $this->status === 1;
    }

    /**
     * Check if appointment is cancelled.
     */
    public function isCancelled()
    {
        return $this->status === -1;
    }

    /**
     * Check if appointment is rescheduled.
     */
    public function isRescheduled()
    {
        return $this->status === -2;
    }

    /**
     * Check if appointment is no show.
     */
    public function isNoShow()
    {
        return $this->status === 2;
    }

    /**
     * Check if appointment is overdue.
     */
    public function isOverdue()
    {
        return $this->scheduled_date < today() && $this->isPending();
    }

    /**
     * Get days until scheduled appointment.
     */
    public function daysUntilScheduled()
    {
        if (! $this->scheduled_date) {
            return null;
        }

        return today()->diffInDays($this->scheduled_date, false);
    }

    /**
     * Get days since completion.
     */
    public function daysSinceCompletion()
    {
        if (! $this->date_completed) {
            return null;
        }

        return $this->date_completed->diffInDays(now());
    }

    /**
     * Get formatted scheduled date and time.
     */
    public function getScheduledDateTimeAttribute()
    {
        if (! $this->scheduled_date) {
            return null;
        }

        $date = $this->scheduled_date->format('M j, Y');

        if ($this->scheduled_time) {
            $time = $this->scheduled_time->format('g:i A');

            return "{$date} at {$time}";
        }

        return $date;
    }

    /**
     * Get duration in hours and minutes.
     */
    public function getFormattedDurationAttribute()
    {
        if (! $this->duration_minutes) {
            return null;
        }

        $hours = intval($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }

    /**
     * Get customer satisfaction text.
     */
    public function getCustomerSatisfactionTextAttribute()
    {
        return match ($this->customer_satisfaction) {
            1 => 'Very Dissatisfied',
            2 => 'Dissatisfied',
            3 => 'Neutral',
            4 => 'Satisfied',
            5 => 'Very Satisfied',
            default => null
        };
    }

    /**
     * Get customer satisfaction stars.
     */
    public function getCustomerSatisfactionStarsAttribute()
    {
        if (! $this->customer_satisfaction) {
            return null;
        }

        return str_repeat('⭐', $this->customer_satisfaction);
    }

    /**
     * Get the last change timestamp for a specific field group
     */
    public function getLastChangeFor($fieldGroup)
    {
        $changes = $this->field_changes ?? [];

        return isset($changes[$fieldGroup]) ? Carbon::parse($changes[$fieldGroup]) : null;
    }

    /**
     * Check if a field group was changed after a given date
     */
    public function wasChangedAfter($fieldGroup, $date)
    {
        $lastChange = $this->getLastChangeFor($fieldGroup);

        return $lastChange && $lastChange->gt(Carbon::parse($date));
    }
}
