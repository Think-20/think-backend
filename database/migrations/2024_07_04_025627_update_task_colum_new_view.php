<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTaskColumNewView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task', function (Blueprint $table) {            
            $table->string('place');
            $table->boolean('total_geral_estande_visibily');
            $table->boolean('liquido_think_visibily');
            $table->double('m2_venda_stand_meta_porcentagem');
            $table->double('m2_venda_stand_logistica_equipamentos_meta_porcentagem');
            $table->double('opcional_equipamento_audio_visual');
            $table->boolean('custo_total_visibily');
            $table->boolean('budget_value_visibily');
            $table->string('producer');            
            $table->double('marcenaria_reaproveitamento');
            $table->double('revestimentos_epeciais_reaproveitamento');
            $table->double('estrutura_metalicas_reaproveitamento');
            $table->double('material_mezanino_reaproveitamento');
            $table->double('fechamento_vidro_reaproveitamento');
            $table->double('vitrines_reaproveitamento');
            $table->double('acrilico_reaproveitamento');
            $table->double('mobiliario_reaproveitamento');
            $table->double('refrigeracao_climatizacao_reaproveitamento');
            $table->double('paisagismo_reaproveitamento');
            $table->double('comunicacao_visual_reaproveitamento');
            $table->double('equipamento_audio_visual_reaproveitamento');
            $table->double('itens_especiais_reaproveitamento');
            $table->double('execucao_reaproveitamento');
            $table->double('servico_diversos_operacional');
            $table->double('servico_diversos_operacional_reaproveitamento');
            $table->double('operacional_logistica');
            $table->double('operacional_logistica_reaproveitamento');
            $table->double('diversos_operacional_meta_porcentagem');
            $table->double('frete_logistica_meta_porcentagem');
            $table->double('custo_total_coeficiente');
            $table->double('imposto_coeficiente');
            $table->double('comissao_vendas_coeficiente');
            $table->double('bonificacao_projeto_interno_coeficiente');
            $table->double('bonificacao_orcamento_coeficiente');
            $table->double('bonificacao_gerente_producao_coeficiente');
            $table->double('bonificacao_producao_coeficiente');
            $table->double('bonificacao_detalhamento_coeficiente');
            $table->double('total_estande_coeficiente');
            $table->double('diversos_operacional_coeficiente');
            $table->double('frete_logistica_coeficiente');
            $table->double('m2_venda_stand_coeficiente');
            $table->double('m2_venda_stand_logistica_equipamentos_coeficiente');            
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
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('liquido_think_visibily');
            $table->dropColumn('m2_venda_stand_meta_porcentagem');
            $table->dropColumn('m2_venda_stand_logistica_equipamentos_meta_porcentagem');
            $table->dropColumn('opcional_equipamento_audio_visual');
            $table->dropColumn('custo_total_visibily');
            $table->dropColumn('budget_value_visibily');
            $table->dropColumn('producer');
            $table->dropColumn('place');
            $table->dropColumn('marcenaria_reaproveitamento');
            $table->dropColumn('revestimentos_epeciais_reaproveitamento');
            $table->dropColumn('estrutura_metalicas_reaproveitamento');
            $table->dropColumn('material_mezanino_reaproveitamento');
            $table->dropColumn('fechamento_vidro_reaproveitamento');
            $table->dropColumn('vitrines_reaproveitamento');
            $table->dropColumn('acrilico_reaproveitamento');
            $table->dropColumn('mobiliario_reaproveitamento');
            $table->dropColumn('refrigeracao_climatizacao_reaproveitamento');
            $table->dropColumn('paisagismo_reaproveitamento');
            $table->dropColumn('comunicacao_visual_reaproveitamento');
            $table->dropColumn('equipamento_audio_visual_reaproveitamento');
            $table->dropColumn('itens_especiais_reaproveitamento');
            $table->dropColumn('execucao_reaproveitamento');
            $table->dropColumn('servico_diversos_operacional');
            $table->dropColumn('servico_diversos_operacional_reaproveitamento');
            $table->dropColumn('operacional_logistica');
            $table->dropColumn('operacional_logistica_reaproveitamento');
            $table->dropColumn('diversos_operacional_meta_porcentagem');
            $table->dropColumn('frete_logistica_meta_porcentagem');
            $table->dropColumn('custo_total_coeficiente');
            $table->dropColumn('imposto_coeficiente');
            $table->dropColumn('comissao_vendas_coeficiente');
            $table->dropColumn('bonificacao_projeto_interno_coeficiente');
            $table->dropColumn('bonificacao_orcamento_coeficiente');
            $table->dropColumn('bonificacao_gerente_producao_coeficiente');
            $table->dropColumn('bonificacao_producao_coeficiente');
            $table->dropColumn('bonificacao_detalhamento_coeficiente');
            $table->dropColumn('total_estande_coeficiente');
            $table->dropColumn('diversos_operacional_coeficiente');
            $table->dropColumn('frete_logistica_coeficiente');
            $table->dropColumn('m2_venda_stand_coeficiente');
            $table->dropColumn('m2_venda_stand_logistica_equipamentos_coeficiente');
        });        
    }
}
