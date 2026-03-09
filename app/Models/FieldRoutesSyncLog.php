<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldRoutesSyncLog extends Model
{
    use HasFactory;

    protected $table = 'fieldroutes_sync_log';

    protected $fillable = [
        'execution_timestamp',
        'start_date',
        'end_date',
        'command_parameters',
        'subscriptionIDs',
        'office_id',
        'office_name',
        'reps_processed',
        'total_available',
        'subscriptions_fetched',
        'records_not_fetched',
        'subscriptions_created',
        'customers_created',
        'appointments_created',
        'subscriptions_updated',
        'customers_updated',
        'appointments_updated',
        'customer_personal_changes',
        'customer_address_changes',
        'customer_status_changes',
        'customer_financial_changes',
        'appointment_status_changes',
        'appointment_schedule_changes',
        'appointment_identifier_changes',
        'records_touched',
        'records_skipped',
        'customers_touched',
        'customers_skipped',
        'appointments_touched',
        'appointments_skipped',
        'errors',
        'duration_seconds',
        'is_dry_run',
        'error_details',
    ];

    protected $casts = [
        'execution_timestamp' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'subscriptionIDs' => 'array',
        'duration_seconds' => 'decimal:2',
        'is_dry_run' => 'boolean',
    ];

    /**
     * Relationship to Integration (Office)
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'office_id');
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('execution_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope for filtering by office
     */
    public function scopeForOffice($query, $officeName)
    {
        return $query->where('office_name', 'like', "%{$officeName}%");
    }

    /**
     * Get total records processed (created + updated)
     */
    public function getTotalRecordsProcessedAttribute()
    {
        return $this->subscriptions_created + $this->subscriptions_updated +
               $this->customers_created + $this->customers_updated +
               $this->appointments_created + $this->appointments_updated;
    }

    /**
     * Get total customer changes
     */
    public function getTotalCustomerChangesAttribute()
    {
        return $this->customer_personal_changes +
               $this->customer_address_changes +
               $this->customer_status_changes +
               $this->customer_financial_changes;
    }

    /**
     * Get total appointment changes
     */
    public function getTotalAppointmentChangesAttribute()
    {
        return $this->appointment_status_changes +
               $this->appointment_schedule_changes +
               $this->appointment_identifier_changes;
    }

    /**
     * Create a log entry from stats array
     */
    public static function createFromStats($stats, $office, $startDate, $endDate, $commandParameters = null, $durationSeconds = null, $isDryRun = false)
    {
        return self::create([
            'execution_timestamp' => now(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'command_parameters' => $commandParameters,
            'subscriptionIDs' => $stats['updated_subscription_ids'] ?? [],
            'office_id' => $office->id ?? null,
            'office_name' => $office->description,
            'reps_processed' => $stats['reps_processed'] ?? 0,
            'total_available' => $stats['total_available'] ?? 0,
            'subscriptions_fetched' => $stats['subscriptions_found'] ?? 0,
            'records_not_fetched' => $stats['records_not_fetched'] ?? 0,
            'subscriptions_created' => $stats['records_created'] ?? 0,
            'customers_created' => $stats['customers_created'] ?? 0,
            'appointments_created' => $stats['appointments_created'] ?? 0,
            'subscriptions_updated' => $stats['records_updated'] ?? 0,
            'customers_updated' => $stats['customers_updated'] ?? 0,
            'appointments_updated' => $stats['appointments_updated'] ?? 0,
            'customer_personal_changes' => $stats['customer_personal_updates'] ?? 0,
            'customer_address_changes' => $stats['customer_address_updates'] ?? 0,
            'customer_status_changes' => $stats['customer_status_updates'] ?? 0,
            'customer_financial_changes' => $stats['customer_financial_updates'] ?? 0,
            'appointment_status_changes' => $stats['status_updates'] ?? 0,
            'appointment_schedule_changes' => $stats['schedule_updates'] ?? 0,
            'appointment_identifier_changes' => $stats['identifier_updates'] ?? 0,
            'records_touched' => $stats['records_touched'] ?? 0,
            'records_skipped' => $stats['records_skipped'] ?? 0,
            'customers_touched' => $stats['customers_touched'] ?? 0,
            'customers_skipped' => $stats['customers_skipped'] ?? 0,
            'appointments_touched' => $stats['appointments_touched'] ?? 0,
            'appointments_skipped' => $stats['appointments_skipped'] ?? 0,
            'errors' => $stats['errors'] ?? 0,
            'duration_seconds' => $durationSeconds,
            'is_dry_run' => $isDryRun,
        ]);
    }
}
