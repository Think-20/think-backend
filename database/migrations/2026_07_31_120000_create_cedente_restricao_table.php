<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedenteRestricaoTable extends Migration
{
    /**
     * Restricoes Vadu (informativas). Nao alteram o status do cedente.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente_restricao', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cedente_id');
            $table->string('campo_restrito', 512);
            $table->string('codigo', 128)->nullable();
            $table->text('descricao')->nullable();
            $table->text('valor_vadu')->nullable();
            $table->string('fonte', 64)->default('vadu');
            $table->timestamps();

            $table->foreign('cedente_id')->references('id')->on('cedente')->onDelete('cascade');
            $table->index('cedente_id');
            $table->index(['cedente_id', 'campo_restrito'], 'cedente_restricao_campo_idx');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente_restricao');
    }
}
