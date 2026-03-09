<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        DB::statement("ALTER TABLE `user_additional_office_override_histories`
CHANGE `office_overrides_type` `office_overrides_type` enum('per sale','per kw','percent') COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `office_overrides_amount`,
CHANGE `old_office_overrides_type` `old_office_overrides_type` enum('per sale','per kw','percent') COLLATE 'utf8mb4_unicode_ci' NOT NULL AFTER `old_office_overrides_amount`;");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_additional_office_override_histories', function (Blueprint $table) {
            //
        });
    }
};
