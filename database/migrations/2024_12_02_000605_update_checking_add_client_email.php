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
            $table->boolean('extras_accept_client')->nullable();
            $table->string('extras_accept_client_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('client_email');
            $table->dropColumn('extras_accept_client');
            $table->dropColumn('extras_accept_client_date');    
        });
    }
}
