<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateContaDesembolsoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('conta_desembolso', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cedente_id');
            // Conta corrente, Conta poupança, Conta salário
            $table->enum('tipo_conta', ['conta_corrente', 'conta_poupanca', 'conta_salario']);
            $table->string('codigo_banco', 8);
            $table->string('agencia', 16);
            $table->string('numero_conta', 32);
            $table->string('digito_conta', 4)->nullable();
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->foreign('cedente_id')->references('id')->on('cedente')->onDelete('cascade');
            $table->index('cedente_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('conta_desembolso');
    }
}
