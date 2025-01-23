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
            //$table->integer('accept_client')->nullable()->change();*
            //$table->integer('approval')->nullable()->change();*

            //$table->integer('accept_proposal')->nullable()->change();
            //$table->integer('accept_production')->nullable()->change();

            //$table->integer('board_approval')->nullable()->change();
            //$table->integer('financial_acceptance')->nullable()->change();

        });
    }
    public function down()
    {   
    }
}
