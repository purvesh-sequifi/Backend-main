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
        Schema::table('reconciliations_adjustement_details', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations_adjustement_details', 'comment_by')) {
                $table->string('comment_by')->nullable();
            }
        });
        Schema::table('reconciliations_adjustement', function (Blueprint $table) {
            if (! Schema::hasColumn('reconciliations_adjustement', 'comment_by')) {
                $table->string('comment_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recon_adjustments_tables', function (Blueprint $table) {
            //
        });
    }
};
