<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAvaliacaoFieldsToCedenteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->text('observacao')->nullable()->after('telefone');
            $table->decimal('limite_aprovado', 14, 2)->nullable()->after('observacao');
            $table->unsignedSmallInteger('prazo_atualizacao_cadastral')->nullable()->after('limite_aprovado')
                ->comment('Prazo em meses para SLA apos aprovacao');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->dropColumn(['observacao', 'limite_aprovado', 'prazo_atualizacao_cadastral']);
        });
    }
}
