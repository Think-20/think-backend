<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdatePlannersSubcategory extends Migration
{
    public function up()
    {
        Schema::table('planner', function (Blueprint $table) {            
            #$table->string('subcategory')->nullable();
            #$table->integer('modality_id');

            #$table->foreign('modality_id')->references('id')->on('timecard_place')->nullable();
        });
    }
    public function down()
    {
        Schema::table('planner', function (Blueprint $table) {            
            $table->dropColumn('subcategory')->nullable();
            $table->dropColumn('modality_id');
        });
    }
} 