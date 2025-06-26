<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDeletedAtToJobTable extends Migration
{
    public function up()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
