<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class RenameCedenteRoleAvalistaLabelToAprovador extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('cedente_role')
            ->where('id', 2)
            ->orWhere('code', 'avalista')
            ->update([
                'name' => 'Aprovador',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('cedente_role')
            ->where('id', 2)
            ->orWhere('code', 'avalista')
            ->update([
                'name' => 'Avalista',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
