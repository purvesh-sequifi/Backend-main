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
        Schema::table('w2_user_transfer_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('w2_user_transfer_histories', 'type')) {
                $table->string('type', 100)->nullable()->comment('1099, w2')->after('contractor_transfer_date');
            }
            if (! Schema::hasColumn('w2_user_transfer_histories', 'deleted_at')) {
                $table->softDeletes();
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
        Schema::table('w2_user_transfer_histories', function (Blueprint $table) {
            //
        });
    }
};
