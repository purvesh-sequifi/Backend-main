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
        Schema::create('product_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('product_id');
            $table->string('product_code', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert existing product IDs from products table into the new product_codes table
        $products = DB::table('products')->select('id', 'product_id')->get();

        if (! empty($products)) {
            foreach ($products as $product) {
                if (! empty($product->product_id)) {

                    // Check if the product code already exists in the product_codes table
                    $exists = DB::table('product_codes')
                        ->where('product_id', $product->id)
                        ->where('product_code', $product->product_id)
                        ->exists();

                    // Only insert if it doesn't already exist
                    if (! $exists) {
                        DB::table('product_codes')->insert([
                            'product_id' => $product->id,
                            'product_code' => $product->product_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_codes');
    }
};
