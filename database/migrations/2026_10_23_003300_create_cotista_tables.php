<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCotistaTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cotista', function (Blueprint $table) {
            $table->increments('id');

            $table->string('nome_completo')->nullable();
            $table->string('tipo_pessoa', 16)->nullable();
            $table->string('documento', 32)->nullable();
            $table->string('tin', 32)->nullable();
            $table->string('nome_mae')->nullable();
            $table->string('sexo', 32)->nullable();
            $table->string('local_nascimento')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('estado_civil', 64)->nullable();
            $table->string('regime_casamento', 64)->nullable();
            $table->string('nome_conjuge')->nullable();
            $table->string('cpf_conjuge', 32)->nullable();
            $table->unsignedSmallInteger('numero_dependentes')->nullable();
            $table->string('nacionalidade', 128)->nullable();
            $table->boolean('possui_outra_nacionalidade')->default(false);
            $table->text('outras_nacionalidades')->nullable();
            $table->string('tipo_documento', 64)->nullable();
            $table->string('numero_documento', 64)->nullable();
            $table->date('data_emissao_documento')->nullable();
            $table->string('orgao_emissor', 64)->nullable();
            $table->string('uf_pais_emissor', 64)->nullable();

            $table->unsignedInteger('endereco_residencial_id')->nullable();
            $table->unsignedInteger('endereco_comercial_id')->nullable();
            $table->string('tempo_residencia', 64)->nullable();
            $table->string('endereco_correspondencia', 32)->nullable();
            $table->boolean('autorizacao_residencia_outros_paises')->default(false);
            $table->text('paises_residencia')->nullable();
            $table->boolean('possui_outro_domicilio_fiscal')->default(false);
            $table->text('outros_domicilios_fiscais')->nullable();
            $table->string('telefone', 32)->nullable();
            $table->string('celular', 32)->nullable();
            $table->string('email')->nullable();
            $table->boolean('us_person')->default(false);

            $table->string('natureza_ocupacao')->nullable();
            $table->string('instituicao_trabalho')->nullable();
            $table->string('cnpj_empregador', 32)->nullable();
            $table->string('telefone_comercial', 32)->nullable();
            $table->string('profissao')->nullable();
            $table->string('cargo')->nullable();
            $table->string('email_comercial')->nullable();

            $table->string('codigo_operacional_cvm', 64)->nullable();
            $table->string('rde_cademp', 64)->nullable();
            $table->string('pais_origem', 128)->nullable();
            $table->string('representante_brasil')->nullable();
            $table->string('representante_coresponsavel')->nullable();
            $table->string('custodiante')->nullable();
            $table->string('administrador_carteira')->nullable();
            $table->string('situacao', 32)->nullable();

            $table->boolean('proposito_cambio')->default(false);
            $table->boolean('proposito_aplicacoes')->default(false);
            $table->boolean('proposito_intermediacao')->default(false);
            $table->boolean('proposito_credito')->default(false);
            $table->boolean('proposito_acessorias')->default(false);
            $table->text('proposito_outros')->nullable();

            $table->boolean('e_ppe')->default(false);
            $table->string('ppe_cargo')->nullable();
            $table->boolean('relacionamento_ppe')->default(false);
            $table->string('ppe_relacionada_cargo')->nullable();
            $table->string('ppe_relacionada_nome')->nullable();
            $table->string('ppe_relacionada_cpf', 32)->nullable();
            $table->string('ppe_relacionada_nacionalidade', 128)->nullable();

            $table->string('status', 32)->default('rascunho');
            $table->date('sla')->nullable();
            $table->unsignedSmallInteger('prazo_atualizacao_cadastral')->nullable();
            $table->timestamps();

            $table->foreign('endereco_residencial_id')->references('id')->on('address')->onDelete('set null');
            $table->foreign('endereco_comercial_id')->references('id')->on('address')->onDelete('set null');
            $table->index('status');
            $table->index('documento');
            $table->index('sla');
        });

        Schema::create('cotista_referencia', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('nome_completo');
            $table->string('nacionalidade', 128)->nullable();
            $table->string('telefone', 32)->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });

        Schema::create('cotista_conta', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('banco', 128);
            $table->string('agencia', 16);
            $table->string('numero_conta', 32);
            $table->enum('tipo_conta', ['poupanca', 'investimentos']);
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });

        Schema::create('cotista_item_patrimonial', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('categoria')->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('valor_atual', 15, 2)->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });

        Schema::create('cotista_participacao', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 32)->nullable();
            $table->string('nacionalidade', 128)->nullable();
            $table->decimal('percentual_capital', 6, 2)->nullable();
            $table->boolean('instituicao_financeira')->default(false);
            $table->string('giin', 32)->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });

        Schema::create('cotista_operador', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->boolean('opera_conta_propria')->default(false);
            $table->boolean('autoriza_ordens_procurador')->default(false);
            $table->string('nome_completo')->nullable();
            $table->string('cpf', 32)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->unsignedInteger('address_id')->nullable();
            $table->text('descricao_poderes')->nullable();
            $table->boolean('e_ppe')->default(false);
            $table->string('ppe_cargo')->nullable();
            $table->boolean('relacionamento_ppe')->default(false);
            $table->string('ppe_relacionada_cargo')->nullable();
            $table->string('ppe_relacionada_nome')->nullable();
            $table->string('ppe_relacionada_cpf', 32)->nullable();
            $table->string('ppe_relacionada_nacionalidade', 128)->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->foreign('address_id')->references('id')->on('address')->onDelete('set null');
            $table->unique('cotista_id');
        });

        Schema::create('cotista_file', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('name');
            $table->string('original_name');
            $table->string('type', 64)->nullable();
            $table->unsignedTinyInteger('document_type');
            $table->boolean('valido')->default(false);
            $table->string('storage_disk', 16)->default('local');
            $table->string('storage_key', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });

        Schema::create('cotista_suitability', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('perfil', 64)->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->unique('cotista_id');
        });

        Schema::create('cotista_suitability_resposta', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_suitability_id');
            $table->unsignedTinyInteger('pergunta');
            $table->unsignedTinyInteger('opcao');
            $table->timestamps();

            $table->foreign('cotista_suitability_id', 'cotista_suit_resp_suitability_fk')
                ->references('id')->on('cotista_suitability')->onDelete('cascade');
            $table->unique(['cotista_suitability_id', 'pergunta'], 'cotista_suit_resp_pergunta_unique');
        });

        Schema::create('cotista_audit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 32);
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->json('changes')->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
            $table->index('user_id');
            $table->index('event');
            $table->index('created_at');
        });

        Schema::create('cotista_inconsistencia', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cotista_id');
            $table->string('campo_inconsistente', 512);
            $table->text('valor_serpro')->nullable();
            $table->timestamps();

            $table->foreign('cotista_id')->references('id')->on('cotista')->onDelete('cascade');
            $table->index('cotista_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cotista_inconsistencia');
        Schema::dropIfExists('cotista_audit');
        Schema::dropIfExists('cotista_suitability_resposta');
        Schema::dropIfExists('cotista_suitability');
        Schema::dropIfExists('cotista_file');
        Schema::dropIfExists('cotista_operador');
        Schema::dropIfExists('cotista_participacao');
        Schema::dropIfExists('cotista_item_patrimonial');
        Schema::dropIfExists('cotista_conta');
        Schema::dropIfExists('cotista_referencia');
        Schema::dropIfExists('cotista');
    }
}
