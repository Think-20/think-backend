<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterCheckinAddAcceptLegalDepartment extends Migration
{
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->integer('accept_legal_department')->nullable();
            $table->integer('accept_legal_department_employee_id')->nullable();
            $table->string('accept_legal_department_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('accept_legal_department');
            $table->dropColumn('accept_legal_department_employee_id');
            $table->dropColumn('accept_legal_department_date');
        });
    }
}
