<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveNewInputsDataInJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropColumn('orders_value');
            $table->dropColumn('attendance_value');
            $table->dropColumn('creation_value');
            $table->dropColumn('pre_production_value');
            $table->dropColumn('production_value');
            $table->dropColumn('details_value');
            $table->dropColumn('budget_si_value');
            $table->dropColumn('bv_value');
            $table->dropColumn('over_rates_value');
            $table->dropColumn('discounts_value');
            $table->dropColumn('taxes_value');
            $table->dropColumn('logistics_value');
            $table->dropColumn('equipment_value');
            $table->dropColumn('total_cost_value');
            $table->dropColumn('gross_profit_value');
            $table->dropColumn('profit_value');
            $table->dropColumn('final_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->string('orders_value')->nullable();
            $table->string('attendance_value')->nullable();
            $table->string('creation_value')->nullable();
            $table->string('pre_production_value')->nullable();
            $table->string('production_value')->nullable();
            $table->string('details_value')->nullable();
            $table->string('budget_si_value')->nullable();
            $table->string('bv_value')->nullable();
            $table->string('over_rates_value')->nullable();
            $table->string('discounts_value')->nullable();
            $table->string('taxes_value')->nullable();
            $table->string('logistics_value')->nullable();
            $table->string('equipment_value')->nullable();
            $table->string('total_cost_value')->nullable();
            $table->string('gross_profit_value')->nullable();
            $table->string('profit_value')->nullable();
            $table->string('final_value')->nullable();
        });
    }
}
