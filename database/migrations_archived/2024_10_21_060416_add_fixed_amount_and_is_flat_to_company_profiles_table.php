<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->decimal('fixed_amount', 8, 2)->nullable()->after('stripe_autopayment'); // Adjust the size and precision as needed
            $table->boolean('is_flat')->default(0)->after('fixed_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['fixed_amount', 'is_flat']);
        });
    }
};
