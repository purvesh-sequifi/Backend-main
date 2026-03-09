<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldRoutesCustomerData extends Model
{
    use HasFactory;

    protected $table = 'FieldRoutes_Customer_Data';

    protected $fillable = [
        // Primary identifiers
        'customer_id',
        'bill_to_account_id',
        'office_id_fr',
        'office_id',
        'office_name',
        'customer_link',
        'region_id',

        // Personal information
        'fname',
        'lname',
        'company_name',
        'spouse',
        'commercial_account',

        // Status & activity
        'status',
        'status_text',
        'active',
        'date_added',
        'date_updated_fr',
        'date_cancelled',

        // Address information
        'address',
        'city',
        'state',
        'county',
        'zip',
        'lat',
        'lng',
        'square_feet',

        // Contact information
        'phone1',
        'ext1',
        'phone2',
        'ext2',
        'additional_phone',
        'billing_phone',
        'email',

        // Billing contact information
        'billing_company_name',
        'billing_fname',
        'billing_lname',
        'billing_country_id',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'billing_email',

        // Financial information
        'balance',
        'responsible_balance',
        'balance_age',
        'aging_date',
        'responsible_balance_age',
        'responsible_aging_date',
        'auto_pay_status',
        'auto_pay_payment_profile_id',
        'a_pay',
        'paid_in_full',
        'preferred_billing_date',
        'payment_hold_date',
        'max_monthly_charge',

        // Credit card information
        'most_recent_credit_card_last_four',
        'most_recent_credit_card_expiration_date',

        // Source & acquisition
        'source_id',
        'source',
        'customer_source',
        'customer_source_id',
        'customer_sub_source_id',
        'customer_sub_source',

        // Employee/rep information
        'employee_id',
        'employee_name',
        'preferred_tech_id',
        'sequifi_id',
        'added_by_id',

        // Relationship IDs (comma-separated strings)
        'subscription_ids',
        'appointment_ids',
        'ticket_ids',
        'payment_ids',
        'unit_ids',

        // Nested data arrays
        'subscriptions',
        'cancellation_reasons',
        'customer_flags',
        'additional_contacts',

        // Portal access
        'portal_login',
        'portal_login_expires',

        // Account details
        'customer_number',
        'master_account',

        // Location & routing
        'map_code',
        'map_page',
        'special_scheduling',

        // Tax information
        'tax_rate',
        'state_tax',
        'city_tax',
        'county_tax',
        'district_tax',
        'district_tax1',
        'district_tax2',
        'district_tax3',
        'district_tax4',
        'district_tax5',
        'custom_tax',
        'zip_tax_id',

        // Communication preferences
        'sms_reminders',
        'phone_reminders',
        'email_reminders',

        // Property classification
        'use_structures',
        'is_multi_unit',
        'division_id',
        'sub_property_type_id',
        'sub_property_type',

        // Customer flags
        'salesman_a_pay',
        'purple_dragon',
        'termite_monitoring',
        'pending_cancel',

        // Raw data storage
        'customer_data',

        // Sync metadata
        'sync_status',
        'last_synced_at',
        'last_modified',
        'sync_batch_id',
        'sync_notes',
        'field_changes',
    ];

    protected $casts = [
        // Primary identifiers
        'customer_id' => 'integer',
        'bill_to_account_id' => 'integer',
        'office_id_fr' => 'integer',
        'region_id' => 'integer',

        // Personal information
        'commercial_account' => 'boolean',

        // Status & activity
        'status' => 'integer',
        'active' => 'boolean',
        'date_added' => 'datetime',
        'date_updated_fr' => 'datetime',
        'date_cancelled' => 'datetime',

        // Address & location
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'square_feet' => 'integer',

        // Financial information
        'balance' => 'decimal:2',
        'responsible_balance' => 'decimal:2',
        'balance_age' => 'integer',
        'aging_date' => 'date',
        'responsible_balance_age' => 'integer',
        'responsible_aging_date' => 'date',
        'auto_pay_status' => 'integer',
        'paid_in_full' => 'boolean',
        'preferred_billing_date' => 'integer',
        'payment_hold_date' => 'date',
        'max_monthly_charge' => 'decimal:2',

        // Source & acquisition
        'source_id' => 'integer',
        'customer_sub_source_id' => 'integer',

        // Employee/rep information
        'preferred_tech_id' => 'integer',
        'sequifi_id' => 'integer',
        'added_by_id' => 'integer',

        // Nested data arrays
        'subscriptions' => 'array',
        'cancellation_reasons' => 'array',
        'customer_flags' => 'array',
        'additional_contacts' => 'array',

        // Portal access
        'portal_login_expires' => 'datetime',

        // Tax information
        'tax_rate' => 'decimal:6',
        'state_tax' => 'decimal:6',
        'city_tax' => 'decimal:6',
        'county_tax' => 'decimal:6',
        'district_tax' => 'decimal:6',
        'district_tax1' => 'decimal:6',
        'district_tax2' => 'decimal:6',
        'district_tax3' => 'decimal:6',
        'district_tax4' => 'decimal:6',
        'district_tax5' => 'decimal:6',
        'custom_tax' => 'decimal:6',
        'zip_tax_id' => 'integer',

        // Communication preferences
        'sms_reminders' => 'boolean',
        'phone_reminders' => 'boolean',
        'email_reminders' => 'boolean',

        // Property classification
        'use_structures' => 'boolean',
        'is_multi_unit' => 'boolean',
        'division_id' => 'integer',
        'sub_property_type_id' => 'integer',

        // Customer flags
        'salesman_a_pay' => 'boolean',
        'purple_dragon' => 'boolean',
        'termite_monitoring' => 'boolean',
        'pending_cancel' => 'boolean',

        // Raw data storage
        'customer_data' => 'array',

        // Sync metadata
        'last_synced_at' => 'datetime',
        'last_modified' => 'datetime',
        'field_changes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the subscriptions associated with this customer.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(FieldRoutesRawData::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the appointments associated with this customer.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(FieldRoutesAppointmentData::class, 'customer_id', 'customer_id');
    }

    /**
     * Get the employee who added this customer.
     */
    public function addedByEmployee(): BelongsTo
    {
        return $this->belongsTo(FrEmployeeData::class, 'employee_id', 'employee_id');
    }

    /**
     * Get the integration/office for this customer.
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'office_name', 'description');
    }

    /**
     * Scope to get only active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope to get only inactive customers.
     */
    public function scopeInactive($query)
    {
        return $query->where('active', false);
    }

    /**
     * Scope to filter by office.
     */
    public function scopeByOffice($query, $officeName)
    {
        return $query->where('office_name', $officeName);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter customers with outstanding balances.
     */
    public function scopeWithBalance($query, $minimumBalance = 0)
    {
        return $query->where('balance', '>', $minimumBalance);
    }

    /**
     * Scope to filter customers on autopay.
     */
    public function scopeOnAutoPay($query)
    {
        return $query->where('auto_pay_status', '>', 0);
    }

    /**
     * Scope to filter by state.
     */
    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Scope to filter by city.
     */
    public function scopeByCity($query, $city)
    {
        return $query->where('city', $city);
    }

    /**
     * Scope to search by name.
     */
    public function scopeByName($query, $name)
    {
        return $query->where(function ($q) use ($name) {
            $q->where('fname', 'like', "%{$name}%")
                ->orWhere('lname', 'like', "%{$name}%")
                ->orWhere('company_name', 'like', "%{$name}%");
        });
    }

    /**
     * Scope to filter by date range.
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
     * Scope to filter by sequifi_id presence.
     */
    public function scopeWithSequifiId($query)
    {
        return $query->whereNotNull('sequifi_id');
    }

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute()
    {
        if ($this->company_name) {
            return $this->company_name;
        }

        return trim($this->fname.' '.$this->lname);
    }

    /**
     * Get the customer's display name (company or personal).
     */
    public function getDisplayNameAttribute()
    {
        return $this->company_name ?: $this->full_name;
    }

    /**
     * Get the autopay status text.
     */
    public function getAutoPayStatusTextAttribute()
    {
        return match ($this->auto_pay_status) {
            0 => 'Not on AutoPay',
            1 => 'AutoPay CC',
            2 => 'AutoPay ACH',
            default => 'Unknown'
        };
    }

    /**
     * Get the customer's primary phone number formatted.
     */
    public function getFormattedPhoneAttribute()
    {
        $phone = $this->phone1;
        if (! $phone || strlen($phone) !== 10) {
            return $phone;
        }

        return sprintf('(%s) %s-%s',
            substr($phone, 0, 3),
            substr($phone, 3, 3),
            substr($phone, 6, 4)
        );
    }

    /**
     * Get the customer's full address.
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zip,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if customer has outstanding balance.
     */
    public function hasOutstandingBalance()
    {
        return $this->balance > 0;
    }

    /**
     * Check if customer is on autopay.
     */
    public function isOnAutoPay()
    {
        return $this->auto_pay_status > 0;
    }

    /**
     * Get days since last update.
     */
    public function daysSinceLastUpdate()
    {
        if (! $this->date_updated_fr) {
            return null;
        }

        return now()->diffInDays($this->date_updated_fr);
    }

    /**
     * Get the customer's total subscription count.
     */
    public function getTotalSubscriptionsAttribute()
    {
        return $this->subscriptions()->count();
    }

    /**
     * Get the customer's active subscription count.
     */
    public function getActiveSubscriptionsAttribute()
    {
        return $this->subscriptions()->where('active', 1)->count();
    }
}
