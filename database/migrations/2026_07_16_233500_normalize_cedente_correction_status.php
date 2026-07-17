<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeCedenteCorrectionStatus extends Migration
{
    /**
     * Solicitar correcoes continua sendo um resultado de avaliacao, mas os
     * cedentes devem aparecer no workflow como inconsistentes.
     *
     * @return void
     */
    public function up()
    {
        DB::table('cedente')
            ->where('status', 'solicitar_correcoes')
            ->update(['status' => 'inconsistente']);
    }

    /**
     * A conversao nao e reversivel: um status inconsistente pode ter sido
     * originado pelo SERPRO, por arquivo recusado ou por uma avaliacao.
     *
     * @return void
     */
    public function down()
    {
        // Intencionalmente vazio para nao classificar inconsistencias incorretamente.
    }
}
