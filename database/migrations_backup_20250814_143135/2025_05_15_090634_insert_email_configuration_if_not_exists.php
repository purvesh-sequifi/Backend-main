<?php

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
        if (! DB::table('email_configuration')->exists()) {
            DB::table('email_configuration')->insert([
                'id' => 1,
                'email_from_name' => 'No Return',
                'email_from_address' => 'no-return@sequifi.com',
                'service_provider' => 'custom',
                'host_mailer' => 'smtp',
                'host_name' => 'smtp.sendgrid.net',
                'smtp_port' => '587',
                'timeout' => '0',
                'security_protocol' => 'TLS',
                'authentication_method' => 'user_name/password',
                'token_app_id' => '',
                'token_app_key' => '',
                'user_name' => 'apikey',
                'password' => 'K3lXUFRNc0plUUFCZkJWNXRVVi91UGF5TWt0WGFTR2MzZ0ZjaG8xNHZBL0pOeklyNjV0UTFMcmxwdGlVbEJkdTNmam94UStpMGlIVDY1YktkTVlpc2lIRVFORDU0Y2FaWHJ5VlBITmJxMWM9',
                'status' => 1,
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
