<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedenteFileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente_file', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cedente_id');
            $table->string('name');
            $table->string('original_name');
            $table->string('type', 64)->nullable();
            $table->unsignedTinyInteger('document_type');
            $table->timestamps();

            $table->foreign('cedente_id')->references('id')->on('cedente')->onDelete('cascade');
            $table->unique(['cedente_id', 'document_type']);
            $table->index('cedente_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente_file');
    }
}
