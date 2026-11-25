<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIsSaleToCedenteTable extends Migration
{
    /**
     * Flag "e uma venda". Quando true, contas de desembolso
     * nao sao exigidas para promocao a pendente.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->boolean('is_sale')->default(false)->after('sistema_financeiro_nacional');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->dropColumn('is_sale');
        });
    }
}
