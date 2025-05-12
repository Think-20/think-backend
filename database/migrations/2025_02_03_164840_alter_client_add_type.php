<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterClientAddType extends Migration
{   
    public function up()
    {
        Schema::table('client', function (Blueprint $table) {
            //$table->string('type')->nullable();
        });
    }

    public function down()
    {
        Schema::table('client', function (Blueprint $table) {
            //$table->dropColumn('type');
        });
    }
} 
