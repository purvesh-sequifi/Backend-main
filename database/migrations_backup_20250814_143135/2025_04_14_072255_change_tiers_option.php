<?php

use App\Models\CompanySetting;
use App\Models\TiersSchema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! TiersSchema::first()) {
            CompanySetting::where(['type' => 'tier'])->update(['status' => '0']);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
