<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedentePessoaVinculadaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente_pessoa_vinculada', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cedente_id');
            $table->boolean('e_parte_relacionada')->default(false);
            $table->boolean('e_avalista')->default(false);
            $table->string('nome');
            // 1 sócio, 2 administrador, 3 procurador, 4 representante legal (quando e_parte_relacionada)
            $table->unsignedTinyInteger('tipo_parte_relacionada')->nullable();
            $table->string('nacionalidade')->nullable();
            $table->string('email')->nullable();
            $table->string('cpf', 14)->nullable();
            $table->string('telefone', 32)->nullable();
            $table->boolean('beneficiario_final')->default(false);
            $table->boolean('assinante_operacao')->default(false);
            $table->boolean('assinante_obrigatorio')->default(false);
            $table->string('estado_civil')->nullable();
            $table->string('regime_casamento')->nullable();
            $table->string('profissao')->nullable();
            $table->unsignedInteger('address_id')->nullable();
            $table->timestamps();

            $table->foreign('cedente_id')->references('id')->on('cedente')->onDelete('cascade');
            $table->foreign('address_id')->references('id')->on('address')->onDelete('set null');

            $table->index('cedente_id');
            $table->index(['cedente_id', 'e_parte_relacionada']);
            $table->index(['cedente_id', 'e_avalista']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente_pessoa_vinculada');
    }
}
