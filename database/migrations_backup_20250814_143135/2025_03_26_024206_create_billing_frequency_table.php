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
        if (! Schema::hasTable('billing_frequency')) {

            Schema::create('billing_frequency', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->integer('status')->default(0);
                $table->timestamps();
            });

            // Insert data
            DB::table('billing_frequency')->insert([
                [
                    'name' => 'Monthly',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Quaterly',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Half Yearly',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Annually',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Weekly',
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
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
        Schema::dropIfExists('billing_frequency');
    }
};
