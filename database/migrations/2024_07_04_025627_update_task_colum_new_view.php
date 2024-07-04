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
            $table->double('frete_logistica')->nullable();
            $table->double('diversos_operacional')->nullable();

            $table->double('mezanino')->nullable();
            $table->date('dt_event')->nullable();
            $table->date('dt_inicio_event')->nullable();
            $table->date('dt_montagem')->nullable();
            $table->date('dt_fim_event')->nullable();
            $table->date('dt_desmontagem')->nullable();
        });

        Schema::table('job', function (Blueprint $table) {
            $table->string('producer')->nullable();
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
            /*$table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');*/            
        });

        Schema::table('job', function (Blueprint $table) {
            /*$table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');
            $table->dropColumn('total_geral_estande_visibily');*/   
        });
    }
}
