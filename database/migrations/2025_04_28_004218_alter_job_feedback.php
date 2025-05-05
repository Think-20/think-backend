<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterJobFeedback extends Migration
{
    public function up()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->string('feedback_user_name')->nullable();
            $table->string('feedback_user_email')->nullable();
            $table->string('feedback_user_phone')->nullable();
            $table->string('feedback_status')->nullable();
            $table->string('feedback_hash')->nullable();
            $table->string('recommendation_rating')->nullable();
            $table->string('overall_project_rating')->nullable();
            $table->string('sales_support_rating')->nullable();
            $table->string('project_feedback')->nullable();

        });
    }

    public function down()
    {
        Schema::table('job', function (Blueprint $table) {
            $table->dropColumn('feedback_user_name');
            $table->dropColumn('feedback_user_email');
            $table->dropColumn('feedback_user_phone');
            $table->dropColumn('feedback_status');
            $table->dropColumn('feedback_hash');
            $table->dropColumn('recommendation_rating');
            $table->dropColumn('overall_project_rating');
            $table->dropColumn('sales_support_rating');
            $table->dropColumn('project_feedback');
        });
    }
}