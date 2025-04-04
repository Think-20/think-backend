<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectPhotosFiles extends Migration
{
    public function up()
    {
        Schema::create('project_photos_file', function (Blueprint $table) {
            $table->increments('id');
            $table->double('responsible_id')->nullable();
            $table->double('task_id')->nullable();
            $table->string('name')->nullable();
            $table->string('original_name')->nullable();
            $table->string('type')->nullable();            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_photos_file');
    }
}
