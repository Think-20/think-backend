<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Evita falha em modo SQL strict: UF/nome de estado > 2 chars; CPF formatado ou CNPJ colado no campo cpf.
 */
class ExtendAddressEstadoAndCedentePessoaCpf extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('address', function (Blueprint $table) {
            $table->string('estado', 64)->nullable()->change();
        });

        Schema::table('cedente_pessoa_vinculada', function (Blueprint $table) {
            $table->string('cpf', 32)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('address', function (Blueprint $table) {
            $table->string('estado', 2)->nullable()->change();
        });

        Schema::table('cedente_pessoa_vinculada', function (Blueprint $table) {
            $table->string('cpf', 14)->nullable()->change();
        });
    }
}
