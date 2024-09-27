<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckinTableClientApproval extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            
            $table->boolean('accept_client')->nullable();
            $table->dateTime('accept_client_date')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            
            $table->dropColumn('accept_client');
            $table->dropColumn('accept_client_date');
            
        });
    }
}
