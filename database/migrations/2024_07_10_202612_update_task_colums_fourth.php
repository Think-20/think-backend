<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTaskColumsFourth extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    /*public function up()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->double('custo_total_meta_porcentagem');
            $table->double('imposto_meta_porcentagem');
            $table->double('comissao_vendas_meta_porcentagem');
            $table->double('bonificacao_projeto_interno_meta_porcentagem');
            $table->double('bonificacao_orcamento_meta_porcentagem');
            $table->double('bonificacao_gerente_producao_meta_porcentagem');
            $table->double('bonificacao_producao_meta_porcentagem');
            $table->double('bonificacao_detalhamento_meta_porcentagem');
            $table->double('total_estande_meta_porcentagem');            
        });
    }*/

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->dropColumn('custo_total_meta_porcentagem');
            $table->dropColumn('imposto_meta_porcentagem');
            $table->dropColumn('comissao_vendas_meta_porcentagem');
            $table->dropColumn('bonificacao_projeto_interno_meta_porcentagem');
            $table->dropColumn('bonificacao_orcamento_meta_porcentagem');
            $table->dropColumn('bonificacao_gerente_producao_meta_porcentagem');
            $table->dropColumn('bonificacao_producao_meta_porcentagem');
            $table->dropColumn('bonificacao_detalhamento_meta_porcentagem');
            $table->dropColumn('total_estande_meta_porcentagem');            
        });
    }
}
