<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class WorkflowJob extends Migration
{
    public function up()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->integer('creation_status')->default(1);
            $table->integer('production_status')->default(1);
        });
    }

    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropColumn('creation_status');
            $table->dropColumn('production_status');
        });
    }
}
