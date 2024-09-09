<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTaskColumsTaxes extends Migration
{
    public function up()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->double('credenciais_taxas')->nullable();
            $table->double('credenciais_taxas_reaproveitamento')->nullable();
            $table->double('credenciais_taxas_porcentagem')->nullable();
            $table->double('seguro')->nullable();
            $table->double('seguro_reaproveitamento')->nullable();
            $table->double('seguro_porcentagem')->nullable();
            $table->double('desconto')->nullable();
            $table->double('desconto_reaproveitamento')->nullable();
            $table->double('desconto_porcentagem')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->dropColumn('credenciais_taxas');
            $table->dropColumn('credenciais_taxas_reaproveitamento');
            $table->dropColumn('credenciais_taxas_porcentagem');
            $table->dropColumn('seguro');
            $table->dropColumn('seguro_reaproveitamento');
            $table->dropColumn('seguro_porcentagem');
            $table->dropColumn('desconto');
            $table->dropColumn('desconto_reaproveitamento');
            $table->dropColumn('desconto_porcentagem');
        });
    }
}
