<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckingAddHashExtras extends Migration
{
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->string('hash')->nullable();
            
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('hash');
            
        });
    }
}
