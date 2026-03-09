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
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            $table->tinyInteger('action_item_status')->default('0')->after('ref_id')->comment('0 = Old, 1 = In Action Item');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('approvals_and_requests', function (Blueprint $table) {
            //
        });
    }
};
