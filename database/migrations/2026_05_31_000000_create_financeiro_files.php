<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateFinanceiroFiles extends Migration
{
    public function up()
    {
        Schema::create('financeiro_files', function (Blueprint $table) {
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
        Schema::dropIfExists('financeiro_files');
    }
}
