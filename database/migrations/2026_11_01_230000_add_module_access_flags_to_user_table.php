<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddModuleAccessFlagsToUserTable extends Migration
{
    /**
     * Uma coluna por módulo. O mesmo usuário pode ter mais de uma.
     * Ainda não há trava nas rotas: o valor só fica gravado para uso futuro.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->boolean('acesso_job')->default(false);
            $table->boolean('acesso_cedente')->default(false);
            $table->boolean('acesso_cotista')->default(false);
        });

        DB::table('user')->update(['acesso_job' => true]);

        if (Schema::hasTable('cedente_role_employee')) {
            $employeeIds = DB::table('cedente_role_employee')->pluck('employee_id');
            if ($employeeIds->count() > 0) {
                DB::table('user')
                    ->whereIn('employee_id', $employeeIds->all())
                    ->update(['acesso_cedente' => true]);
            }
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['acesso_job', 'acesso_cedente', 'acesso_cotista']);
        });
    }
}
