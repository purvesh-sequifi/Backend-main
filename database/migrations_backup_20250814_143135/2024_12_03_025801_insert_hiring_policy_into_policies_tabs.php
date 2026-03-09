<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // $policyId = DB::table('group_policies')->where('policies', 'Hiring')->value('id');

        // if ($policyId) {
        //     $existingRecord = DB::table('policies_tabs')
        //         ->where('policies_id', $policyId)
        //         ->where('tabs', 'Lead Level Rating')
        //         ->exists();

        //     if (!$existingRecord) {
        //         // Insert the new record
        //         DB::table('policies_tabs')->insert([
        //             'policies_id' => $policyId,
        //             'tabs' => 'Lead Level Rating',
        //             'created_at' => now(),
        //             'updated_at' => now(),
        //         ]);
        //     }
        // }
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
