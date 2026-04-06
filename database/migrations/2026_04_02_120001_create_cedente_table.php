<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedenteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nome');
            $table->string('documento');
            $table->string('email')->nullable();
            $table->decimal('faturamento_anual', 15, 2)->nullable();
            $table->unsignedInteger('minimo_assinantes')->nullable();
            $table->unsignedInteger('address_id')->nullable();
            $table->boolean('sistema_financeiro_nacional')->default(false);
            $table->string('telefone', 32)->nullable();
            $table->timestamps();

            $table->foreign('address_id')->references('id')->on('address')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente');
    }
}
