<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStorageFieldsToCedenteFile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cedente_file', function (Blueprint $table) {
            $table->string('storage_disk', 16)->default('local')->after('valido');
            $table->string('storage_key', 512)->nullable()->after('storage_disk');
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
            $table->dropColumn(['storage_disk', 'storage_key']);
        });
    }
}
