<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFundIdToCedenteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->unsignedInteger('fund_id')->nullable()->after('id');
            $table->foreign('fund_id')->references('id')->on('fund')->onDelete('restrict');
            $table->index('fund_id');
            $table->unique(['fund_id', 'documento'], 'cedente_fund_documento_unique');
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
            $table->dropForeign(['fund_id']);
            $table->dropUnique('cedente_fund_documento_unique');
            $table->dropIndex(['fund_id']);
            $table->dropColumn('fund_id');
        });
    }
}
