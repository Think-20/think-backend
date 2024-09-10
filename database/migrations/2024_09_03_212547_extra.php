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


            $table->date('approval_date')->nullable();
            $table->string('extra_commission')->nullable();
            $table->integer('billing_employee_id')->nullable();
            $table->date('date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('settlement_date')->nullable();


            $table->timestamps();

            #$table->foreign('checkin_id')->references('id')->on('checkin')->nullable();

            $table->foreign('budget')->references('id')->on('employee')->nullable();
            $table->foreign('billing_employee_id')->references('id')->on('employee')->nullable();
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
