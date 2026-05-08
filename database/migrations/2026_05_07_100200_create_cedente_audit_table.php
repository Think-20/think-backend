<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedenteAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente_audit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cedente_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 32);
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->foreign('cedente_id')->references('id')->on('cedente')->onDelete('cascade');
            $table->index('cedente_id');
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente_audit');
    }
}

