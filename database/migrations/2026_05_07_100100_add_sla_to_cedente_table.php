<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSlaToCedenteTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente', function (Blueprint $table) {
            $table->date('sla')->nullable()->after('status');
            $table->index('sla');
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
            $table->dropIndex(['sla']);
            $table->dropColumn('sla');
        });
    }
}

