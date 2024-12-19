<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateCheckinAddCheckinHashAndReasonForMail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->string('checkin_hash')->nullable();
            $table->string('reason_for_rejection')->nullable();
        });
    }

    public function down()
    {
        Schema::table('checkin', function (Blueprint $table) {
            $table->dropColumn('checkin_hash');
            $table->dropColumn('reason_for_rejection');
        });
    }
}
