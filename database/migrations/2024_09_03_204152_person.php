<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Person extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('person', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('checkin_id');
            $table->integer('bank_account_id');
            $table->string('nome');
            $table->string('cnpj');
            $table->string('cpf');
            $table->timestamps();

            #$table->foreign('checkin_id')->references('id')->on('checkin')->nullable();
            #$table->foreign('bank_account_id')->references('id')->on('bank_account')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment');
    }
}
