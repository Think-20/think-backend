<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateExtrasRebrain extends Migration
{
    public function up()
    {

        Schema::rename('extra', 'extra_item');

        Schema::create('extra', function (Blueprint $table) {
            $table->increments('id');
            $table->string('description');
            $table->integer('job_id');

            $table->string('accept_client');
            $table->string('accept_client_date');
            $table->string('approval');
            $table->string('approval_date');
            $table->string('hash');
            $table->string('obs');
            
            //$table->foreign('job_id')->references('id')->on('job')->nullable();

            $table->timestamps();
        });

        Schema::table('event', function (Blueprint $table) {

            $table->integer('organization_id')->nullable();

            //$table->foreign('organization_id')->references('id')->on('organization')->nullable();
        });

        Schema::table('extra_item', function (Blueprint $table) {
            $table->dropColumn('checkin_id')->nullable();

            $table->integer('extra_id')->nullable();

            //$table->foreign('extra_id')->references('id')->on('extra')->nullable();
        });


        
    }

    public function down()
    {
        Schema::table('event', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('extra');

        Schema::rename('extra_item' , 'extra' );

        Schema::table('extra', function (Blueprint $table) {
            $table->dropColumn('extra_id');
            $table->dropColumn('reason_for_rejection');

            $table->integer('extra_id')->nullable();
        });
    }
}
