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
        if (Schema::hasColumn('cedente', 'fund_id')) {
            return;
        }

        Schema::table('cedente', function (Blueprint $table) {
            $table->unsignedInteger('fund_id')->nullable()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasColumn('cedente', 'fund_id')) {
            return;
        }

        Schema::table('cedente', function (Blueprint $table) {
            $table->dropColumn('fund_id');
        });
    }
}
