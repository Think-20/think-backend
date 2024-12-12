<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckingLogChanges extends Migration
{
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->integer('project_change_employee')->nullable();
            $table->string('project_change_date')->nullable();

            $table->integer('memorial_change_employee')->nullable();
            $table->string('memorial_change_date')->nullable();

            $table->integer('budget_change_employee')->nullable();
            $table->string('budget_change_date')->nullable();

            $table->foreign('project_change_employee')->references('id')->on('employee')->nullable();
            $table->foreign('memorial_change_employee')->references('id')->on('employee')->nullable();
            $table->foreign('budget_change_employee')->references('id')->on('employee')->nullable();
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('project_change_employee');
            $table->dropColumn('project_change_date');
            $table->dropColumn('memorial_change_employee');
            $table->dropColumn('memorial_change_date');
            $table->dropColumn('budget_change_employee');
            $table->dropColumn('budget_change_date');
        });
    }
}