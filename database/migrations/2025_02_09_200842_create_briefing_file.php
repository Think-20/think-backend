<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBriefingFile extends Migration
{
    public function up()
    {
        Schema::create('briefing_file', function (Blueprint $table) {
            $table->increments('id');
            $table->string('responsible_id')->nullable();
            $table->string('task_id')->nullable();
            $table->string('name')->nullable();
            $table->double('original_name')->nullable();
            $table->string('type')->nullable();            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('briefing_file');
    }
}
