<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddComissionAttendanceAndPercentagemInJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->integer('attendance_comission_id')->nullable();
            $table->foreign('attendance_comission_id')->references('id')->on('employee');
            $table->double('comission_percentage')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropForeign(['attendance_comission_id']);
            $table->dropColumn('attendance_comission_id');
            $table->dropColumn('comission_percentage');
        });
    }
}
