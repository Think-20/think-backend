<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCheckinTable extends Migration
{
 
    /*public function up()
    {
        Schema::create('checkin', function (Blueprint $table) {
            $table->increments('id');
            $table->string('place');
            $table->boolean('accept_proposal');
            $table->boolean('accept_production');
            $table->boolean('accept_board');
            $table->double('m2_venda_stand_meta_porcentagem');

            $table->foreign('project_version_id')->references('id')->on('employee');
            $table->foreign('descriptive_memorial')->references('id')->on('employee');
            $table->foreign('project_version_id')->references('id')->on('employee');
            $table->timestamps();
        });
    }*/

    /*public function down()
    {
        //Schema::dropIfExists('checkin');
    }*/
    
}
