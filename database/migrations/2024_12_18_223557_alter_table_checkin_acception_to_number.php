<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableCheckinAcceptionToNumber extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->integer('accept_client')->nullable();
            $table->integer('approval')->nullable();

            $table->integer('accept_proposal')->nullable();
            $table->integer('accept_production')->nullable();

            $table->integer('board_approval')->nullable();
            $table->integer('financial_acceptance')->nullable();

        });
    }
    public function down()
    {   
    }
}
