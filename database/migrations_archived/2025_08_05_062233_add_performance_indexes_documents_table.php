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
        // SKIP - Documents table already has optimal index: idx_user_doc_action
        // Schema::table('documents', function (Blueprint $table) {
        //     // These indexes already exist as idx_user_doc_action
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_user_response_action');
            $table->dropIndex('idx_response_action_status');
            $table->dropIndex('idx_user_created');
            $table->dropIndex('idx_action_updated');
        });
    }
};
