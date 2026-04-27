<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Legado: `bank_id` em `bank_account` pode referenciar `bank_account_type` por engano.
 * O código espera `bank_id` na tabela `bank`.
 */
class FixBankAccountBankIdForeignToBankTable extends Migration
{
    /**
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('bank') || !Schema::hasTable('bank_account')) {
            return;
        }

        $schema = DB::getDatabaseName();
        $fks = DB::select('
            SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE k
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
                AND k.TABLE_SCHEMA = r.CONSTRAINT_SCHEMA
            WHERE k.TABLE_SCHEMA = ?
                AND k.TABLE_NAME = ?
                AND k.COLUMN_NAME = ?
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ', [$schema, 'bank_account', 'bank_id']);

        $alreadyPointsToBank = false;
        foreach ($fks as $fk) {
            if ($fk->REFERENCED_TABLE_NAME === 'bank') {
                $alreadyPointsToBank = true;
            }
        }

        foreach ($fks as $fk) {
            if ($fk->REFERENCED_TABLE_NAME !== 'bank') {
                DB::statement('ALTER TABLE `bank_account` DROP FOREIGN KEY `' . $fk->CONSTRAINT_NAME . '`');
            }
        }

        if (!$alreadyPointsToBank) {
            Schema::table('bank_account', function (Blueprint $table) {
                $table->foreign('bank_id')->references('id')->on('bank');
            });
        }
    }

    /**
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('bank_account')) {
            return;
        }

        $schema = DB::getDatabaseName();
        $fks = DB::select('
            SELECT k.CONSTRAINT_NAME, k.REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE k
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME
                AND k.TABLE_SCHEMA = r.CONSTRAINT_SCHEMA
            WHERE k.TABLE_SCHEMA = ?
                AND k.TABLE_NAME = ?
                AND k.COLUMN_NAME = ?
                AND k.REFERENCED_TABLE_NAME = ?
        ', [$schema, 'bank_account', 'bank_id', 'bank']);

        foreach ($fks as $fk) {
            DB::statement('ALTER TABLE `bank_account` DROP FOREIGN KEY `' . $fk->CONSTRAINT_NAME . '`');
        }
    }
}
