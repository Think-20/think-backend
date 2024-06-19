<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class InactiveTime extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*Schema::create('inactive_time', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            //$table->integer('month');
            //$table->integer('year');
            $table->double('notification_time');
            $table->double('inactive_time');
            $table->timestamps();
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('inactive_time');
    }
}
