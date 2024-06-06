<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEmployeeToRemindersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->integer('employee_id');
            $table->foreign('employee_id')->references('id')->on('employee');
        });
    }
    
    public function down()
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });
    }
}
