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
        if (! Schema::hasColumn('settings', 'is_encrypted')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->boolean('is_encrypted')->default(0)->after('value');
            });
        }

        // Encrypt values for specific keys and update the is_encrypted column
        $settingsToEncrypt = DB::table('settings')
            ->whereIn('key', [
                'AWS_ACCESS_KEY_ID_PUBLIC',
                'AWS_SECRET_ACCESS_KEY_PUBLIC',
                'AWS_ACCESS_KEY_ID_PRIVATE',
                'AWS_SECRET_ACCESS_KEY_PRIVATE',
            ])
            ->where('is_encrypted', 0)
            ->get();

        foreach ($settingsToEncrypt as $setting) {
            $encryptedValue = openssl_encrypt(
                $setting->value,
                env('ENCRYPTION_CIPHER_ALGO'),
                env('ENCRYPTION_KEY'),
                0,
                env('ENCRYPTION_IV')
            );

            DB::table('settings')
                ->where('id', $setting->id)
                ->update([
                    'value' => $encryptedValue,
                    'is_encrypted' => 1,
                ]);
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
