<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameCotistaCpfToDocumento extends Migration
{
    /**
     * O documento principal deixa de ser só CPF.
     * tipo_pessoa: fisica ou juridica.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('cotista')) {
            return;
        }

        if (Schema::hasColumn('cotista', 'cpf') && ! Schema::hasColumn('cotista', 'documento')) {
            Schema::table('cotista', function (Blueprint $table) {
                $table->renameColumn('cpf', 'documento');
            });
        }

        $indexNames = $this->indexNames();

        Schema::table('cotista', function (Blueprint $table) use ($indexNames) {
            if (in_array('cotista_cpf_index', $indexNames, true)) {
                $table->dropIndex('cotista_cpf_index');
            }
            if (Schema::hasColumn('cotista', 'documento') && ! in_array('cotista_documento_index', $indexNames, true)) {
                $table->index('documento');
            }
            if (! Schema::hasColumn('cotista', 'tipo_pessoa')) {
                $table->string('tipo_pessoa', 16)->nullable()->after('nome_completo');
            }
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('cotista')) {
            return;
        }

        if (Schema::hasColumn('cotista', 'tipo_pessoa')) {
            Schema::table('cotista', function (Blueprint $table) {
                $table->dropColumn('tipo_pessoa');
            });
        }

        if (Schema::hasColumn('cotista', 'documento') && ! Schema::hasColumn('cotista', 'cpf')) {
            Schema::table('cotista', function (Blueprint $table) {
                $table->renameColumn('documento', 'cpf');
            });
        }

        $indexNames = $this->indexNames();

        Schema::table('cotista', function (Blueprint $table) use ($indexNames) {
            if (in_array('cotista_documento_index', $indexNames, true)) {
                $table->dropIndex('cotista_documento_index');
            }
            if (Schema::hasColumn('cotista', 'cpf') && ! in_array('cotista_cpf_index', $indexNames, true)) {
                $table->index('cpf');
            }
        });
    }

    /**
     * @return string[]
     */
    private function indexNames()
    {
        $names = [];
        foreach (DB::select('SHOW INDEX FROM `cotista`') as $row) {
            $names[] = $row->Key_name;
        }

        return array_values(array_unique($names));
    }
}
