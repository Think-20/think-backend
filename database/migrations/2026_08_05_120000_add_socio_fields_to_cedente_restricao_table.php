<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSocioFieldsToCedenteRestricaoTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        Schema::table('cedente_restricao', function (Blueprint $table) {
            $table->unsignedInteger('socio_indice')->nullable()->after('campo_restrito');
            $table->string('socio_nome', 512)->nullable()->after('socio_indice');
            $table->string('qualificacao_representante_legal', 512)->nullable()->after('socio_nome');
            $table->string('nome_representante_legal', 512)->nullable()->after('qualificacao_representante_legal');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('cedente_restricao', function (Blueprint $table) {
            $table->dropColumn([
                'socio_indice',
                'socio_nome',
                'qualificacao_representante_legal',
                'nome_representante_legal',
            ]);
        });
    }
}
