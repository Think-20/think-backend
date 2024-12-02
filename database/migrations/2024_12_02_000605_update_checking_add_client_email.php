<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckingAddClientEmail extends Migration
{
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->string('client_email')->nullable();            
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('client_email');            
        });
    }
}
