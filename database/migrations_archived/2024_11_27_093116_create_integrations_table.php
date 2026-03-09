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
        Schema::create('integrations', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key 'id'
            $table->string('name')->nullable(); // Nullable 'name' column (string type)
            $table->text('value')->nullable(); // Nullable 'value' column (text type)
            $table->tinyInteger('status')->default(0)->nullable(); // Nullable 'status' column with default 0
            $table->timestamps(); // Created at and updated at timestamps (optional, but commonly used)
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integrations');
    }
};
