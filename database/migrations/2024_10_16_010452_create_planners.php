<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlanners extends Migration
{
    public function up()
    {
        Schema::create('planner', function (Blueprint $table) {
            $table->increments('id');
            $table->datetime('date')->nullable();
            $table->string('category')->nullable();
            $table->string('description')->nullable();
            $table->integer('employee_id');
            
            $table->foreign('employee_id')->references('id')->on('employee')->nullable();
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
        Schema::dropIfExists('organization');
    }
}
