<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class DropFundIdFromCotistaTable extends Migration
{
    /**
     * Cotista nao pertence a fundo. A lista e unica.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('cotista') || ! Schema::hasColumn('cotista', 'fund_id')) {
            return;
        }

        Schema::table('cotista', function (Blueprint $table) {
            $table->dropIndex('cotista_fund_id_index');
            $table->dropColumn('fund_id');
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('cotista') || Schema::hasColumn('cotista', 'fund_id')) {
            return;
        }

        Schema::table('cotista', function (Blueprint $table) {
            $table->unsignedInteger('fund_id')->nullable();
            $table->index('fund_id');
        });
    }
}
