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
        Schema::create('employee_admin_only_fields', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('configuration_id');
            $table->string('field_name');
            $table->string('field_type');
            $table->string('field_required');
            $table->text('attribute_option')->nullable();
            $table->tinyInteger('field_permission')->comment('1 => visible to user, 2 => not visible to user');
            $table->tinyInteger('field_data_entry')->comment('1 => Hiring Wizard, 2 => User profile');
            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_admin_only_fields');
    }
};
