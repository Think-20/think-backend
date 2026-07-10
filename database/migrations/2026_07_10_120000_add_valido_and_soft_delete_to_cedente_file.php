<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddValidoAndSoftDeleteToCedenteFile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente_file', function (Blueprint $table) {
            $table->dropUnique('cedente_file_cedente_id_document_type_unique');
        });

        Schema::table('cedente_file', function (Blueprint $table) {
            $table->boolean('valido')->default(false)->after('document_type');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cedente_file', function (Blueprint $table) {
            $table->dropColumn('valido');
            $table->dropSoftDeletes();
        });

        Schema::table('cedente_file', function (Blueprint $table) {
            $table->unique(['cedente_id', 'document_type']);
        });
    }
}
