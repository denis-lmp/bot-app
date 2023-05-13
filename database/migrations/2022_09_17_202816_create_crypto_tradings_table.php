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
        Schema::create('crypto_tradings', function (Blueprint $table) {
            $table->id();
            $table->string('ticker');
            $table->string('amount');
            $table->string('price');
            $table->string('old_price');
            $table->string('buy_sell');
            $table->unsignedBigInteger('order_id');
            $table->string('status')->nullable();
            $table->integer('checks');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('crypto_tradings');
    }
};
