<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CostSheet extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
<<<<<<< HEAD
        /*Schema::create('cost_sheet', function (Blueprint $table) {
            
        });*/
=======
        Schema::create('cost_sheet', function (Blueprint $table) {
            /*$table->increments('id');
            $table->integer('month');
            $table->integer('year');
            $table->double('value');
            $table->timestamps();*/
        });
>>>>>>> 636e96ff3b72254c7fecbad1b8473ad8437571d2
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cost_sheet');
    }
}
