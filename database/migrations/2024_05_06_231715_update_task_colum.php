<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTaskColum extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->double('marcenaria')->nullable();
            $table->double('revestimentos_epeciais')->nullable();
            $table->double('estrutura_metalicas')->nullable();
            $table->double('material_mezanino')->nullable();
            $table->double('fechamento_vidro')->nullable();
            $table->double('vitrines')->nullable();
            $table->double('acrilico')->nullable();
            $table->double('mobiliario')->nullable();
            $table->double('refrigeracao_climatizacao')->nullable();
            $table->double('paisagismo')->nullable();
            $table->double('comunicacao_visual')->nullable();
            $table->double('equipamento_audio_visual')->nullable();
            $table->double('itens_especiais')->nullable();
            $table->double('execucao')->nullable();
            $table->double('logistica')->nullable();

            $table->double('coeficiente_margem')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropColumn('marcenaria');
            $table->dropColumn('revestimentos_epeciais');
            $table->dropColumn('estrutura_metalicas');
            $table->dropColumn('material_mezanino');
            $table->dropColumn('fechamento_vidro');
            $table->dropColumn('vitrines');
            $table->dropColumn('acrilico');
            $table->dropColumn('mobiliario');
            $table->dropColumn('refrigeracao_climatizacao');
            $table->dropColumn('paisagismo');
            $table->dropColumn('comunicacao_visual');
            $table->dropColumn('equipamento_audio_visual');
            $table->dropColumn('itens_especiais');
            $table->dropColumn('execucao');
            $table->dropColumn('logistica ');

            $table->dropColumn('coeficiente_margem');
        });
    }
}
