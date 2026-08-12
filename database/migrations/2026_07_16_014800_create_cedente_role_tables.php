<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCedenteRoleTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cedente_role', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 40);
            $table->string('name', 100);
            $table->timestamps();

            $table->unique('code');
        });

        Schema::create('cedente_role_employee', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cedente_role_id');
            $table->integer('employee_id');
            $table->timestamps();

            // Um employee tem no maximo um papel de cedente.
            $table->unique('employee_id');
            $table->unique(['cedente_role_id', 'employee_id']);
        });

        $now = date('Y-m-d H:i:s');
        DB::table('cedente_role')->insert([
            [
                'code' => 'preenchimento',
                'name' => 'Preenchimento de formulario',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'avalista',
                'name' => 'Aprovador',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'administrador',
                'name' => 'Administrador',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cedente_role_employee');
        Schema::dropIfExists('cedente_role');
    }
}
