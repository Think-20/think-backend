<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Extra extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('extra', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('checkin_id');
            $table->string('description');
            $table->double('value');
            $table->integer('requester');
            $table->integer('budget');
            $table->timestamps();

            #$table->foreign('checkin_id')->references('id')->on('checkin')->nullable();
            
            #$table->foreign('requester')->references('id')->on('person')->nullable();
            $table->foreign('budget')->references('id')->on('employee')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('extra');
    }
}
