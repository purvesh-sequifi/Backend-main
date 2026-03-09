<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('FieldRoutes_Appointment_Data', function (Blueprint $table) {
            // === TIME TRACKING FIELDS ===
            $table->timestamp('time_in')->nullable()->after('date_updated_fr'); // Check-in time
            $table->timestamp('time_out')->nullable()->after('time_in'); // Check-out time
            $table->timestamp('check_in')->nullable()->after('time_out'); // GPS check-in
            $table->timestamp('check_out')->nullable()->after('check_in'); // GPS check-out

            // === WEATHER & ENVIRONMENTAL ===
            $table->decimal('wind_speed', 5, 2)->nullable()->after('check_out'); // Wind speed
            $table->string('wind_direction')->nullable()->after('wind_speed'); // Wind direction
            $table->decimal('temperature', 5, 2)->nullable()->after('wind_direction'); // Temperature

            // === PAYMENT & BILLING ===
            $table->integer('payment_method')->nullable()->after('service_amount'); // Payment method ID
            $table->unsignedBigInteger('ticket_id')->nullable()->after('payment_method'); // Billing ticket ID

            // === SERVICE DETAILS ===
            $table->boolean('serviced_interior')->default(false)->after('customer_satisfaction'); // Interior service
            $table->boolean('do_interior')->default(false)->after('serviced_interior'); // Interior requested
            $table->boolean('call_ahead')->default(false)->after('do_interior'); // Call ahead setting
            $table->boolean('signed_by_customer')->default(false)->after('call_ahead'); // Customer signature
            $table->boolean('signed_by_tech')->default(false)->after('signed_by_customer'); // Tech signature

            // === OFFICE & APPOINTMENT NOTES ===
            $table->text('office_notes')->nullable()->after('service_notes'); // Office notes
            $table->text('appointment_notes')->nullable()->after('office_notes'); // Appointment-specific notes

            // === SCHEDULING & ORGANIZATION ===
            $table->decimal('production_value', 10, 2)->nullable()->after('temperature'); // Production value
            $table->date('due_date')->nullable()->after('production_value'); // Due date
            $table->unsignedBigInteger('group_id')->nullable()->after('due_date'); // Group ID
            $table->integer('sequence')->nullable()->after('group_id'); // Route sequence
            $table->unsignedBigInteger('locked_by')->nullable()->after('sequence'); // Locked by user ID

            // === GPS COORDINATES ===
            $table->decimal('lat_in', 10, 8)->nullable()->after('locked_by'); // Check-in latitude
            $table->decimal('lat_out', 10, 8)->nullable()->after('lat_in'); // Check-out latitude
            $table->decimal('long_in', 11, 8)->nullable()->after('lat_out'); // Check-in longitude
            $table->decimal('long_out', 11, 8)->nullable()->after('long_in'); // Check-out longitude

            // === SUBSCRIPTION & REGION ===
            $table->unsignedBigInteger('subscription_region_id')->nullable()->after('long_out'); // Subscription region
            $table->unsignedBigInteger('subscription_preferred_tech')->nullable()->after('subscription_region_id'); // Preferred tech

            // === TIME WINDOW & SCHEDULING ===
            $table->string('time_window')->nullable()->after('subscription_preferred_tech'); // Time window (AT, AM, PM, etc.)
            $table->time('start_time')->nullable()->after('time_window'); // Start time for appointment
            $table->time('end_time')->nullable()->after('start_time'); // End time for appointment

            // === ADDITIONAL DATA ===
            $table->json('unit_ids')->nullable()->after('end_time'); // Unit IDs array

            // === REASON CODES ===
            $table->unsignedBigInteger('cancellation_reason_id')->nullable()->after('unit_ids'); // Cancellation reason
            $table->unsignedBigInteger('reschedule_reason_id')->nullable()->after('cancellation_reason_id'); // Reschedule reason
            $table->unsignedBigInteger('reserviced_reason_id')->nullable()->after('reschedule_reason_id'); // Re-service reason

            // === ADDITIONAL INDEXES FOR PERFORMANCE ===
            $table->index(['time_in', 'time_out'], 'time_tracking');
            $table->index(['ticket_id'], 'ticket_lookup');
            $table->index(['group_id', 'sequence'], 'group_sequence');
            $table->index(['subscription_region_id'], 'region_lookup');
            $table->index(['lat_in', 'long_in'], 'checkin_location');
            $table->index(['due_date', 'status'], 'due_status');
            $table->index(['subscription_preferred_tech'], 'preferred_tech');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('FieldRoutes_Appointment_Data', function (Blueprint $table) {
            $table->dropIndex(['time_in', 'time_out']);
            $table->dropIndex(['ticket_id']);
            $table->dropIndex(['group_id', 'sequence']);
            $table->dropIndex(['subscription_region_id']);
            $table->dropIndex(['lat_in', 'long_in']);
            $table->dropIndex(['due_date', 'status']);
            $table->dropIndex(['subscription_preferred_tech']);

            $table->dropColumn([
                'time_in', 'time_out', 'check_in', 'check_out',
                'wind_speed', 'wind_direction', 'temperature',
                'payment_method', 'ticket_id',
                'serviced_interior', 'do_interior', 'call_ahead',
                'signed_by_customer', 'signed_by_tech',
                'office_notes', 'appointment_notes',
                'production_value', 'due_date', 'group_id', 'sequence', 'locked_by',
                'lat_in', 'lat_out', 'long_in', 'long_out',
                'subscription_region_id', 'subscription_preferred_tech',
                'time_window', 'start_time', 'end_time',
                'unit_ids',
                'cancellation_reason_id', 'reschedule_reason_id', 'reserviced_reason_id',
            ]);
        });
    }
};
