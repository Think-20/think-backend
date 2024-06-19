<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateTaskColumnsPlaceProducer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /*Schema::table('task', function (Blueprint $table) {
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
        });*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task', function (Blueprint $table) {
            $table->dropColumn('frete_logistica');
            $table->dropColumn('diversos_operacional');

            $table->dropColumn('mezanino');
            $table->dropColumn('dt_event');
            $table->dropColumn('dt_inicio_event');
            $table->dropColumn('dt_montagem');
            $table->dropColumn('dt_fim_event');
            $table->dropColumn('dt_desmontagem');
        });

        Schema::table('job', function (Blueprint $table) {
            $table->dropColumn('producer');
        });
    }
}
