<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckingAddFinancialAcceptance extends Migration
{
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->boolean('financial_acceptance')->nullable();
            $table->integer('financial_acceptance_employee_id')->nullable();
            $table->dateTime('financial_acceptance_date')->nullable();

            $table->foreign('financial_acceptance_employee_id')->references('id')->on('employee')->nullable();
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('financial_acceptance');
            $table->dropColumn('financial_acceptance_employee_id');
            $table->dropColumn('financial_acceptance_date');
        });
    }
}
