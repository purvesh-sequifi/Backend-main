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
        Schema::table('s_clearance_tokens', function (Blueprint $table) {
            $table->text('mfa_token')->nullable()->after('token');
            $table->tinyInteger('token_key_used')->nullable()->after('mfa_token');
            $table->tinyInteger('mfa_token_key_used')->nullable()->after('token_key_used');
            $table->string('mfa_expiration_time')->nullable()->after('expiration_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('s_clearance_tokens', function (Blueprint $table) {
            $table->dropColumn('mfa_token');
            $table->dropColumn('token_key_used');
            $table->dropColumn('mfa_token_key_used');
            $table->dropColumn('mfa_expiration_time');
        });
    }
};
