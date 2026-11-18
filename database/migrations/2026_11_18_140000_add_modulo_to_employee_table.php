<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddModuloToEmployeeTable extends Migration
{
    /**
     * Modulo do usuario: job, cedente ou cotista.
     * Um employee tem um modulo. O papel (preenchimento/avalista/administrador)
     * continua em cedente_role_employee.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->string('modulo', 20)->default('job')->after('name');
            $table->index('modulo');
        });

        if (Schema::hasTable('cedente_role_employee')) {
            $employeeIds = DB::table('cedente_role_employee')->pluck('employee_id');
            if ($employeeIds->count() > 0) {
                DB::table('employee')
                    ->whereIn('id', $employeeIds->all())
                    ->update(['modulo' => 'cedente']);
            }
        }

        if (Schema::hasColumn('user', 'acesso_cotista') && Schema::hasColumn('user', 'acesso_cedente')) {
            $cotistaOnly = DB::table('user')
                ->where('acesso_cotista', 1)
                ->where(function ($query) {
                    $query->where('acesso_cedente', 0)->orWhereNull('acesso_cedente');
                })
                ->whereNotNull('employee_id')
                ->pluck('employee_id');

            if ($cotistaOnly->count() > 0) {
                DB::table('employee')
                    ->whereIn('id', $cotistaOnly->all())
                    ->update(['modulo' => 'cotista']);
            }
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('employee', function (Blueprint $table) {
            $table->dropIndex(['modulo']);
            $table->dropColumn('modulo');
        });
    }
}
