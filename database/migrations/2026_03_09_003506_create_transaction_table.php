<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('job_id');
            $table->unsignedTinyInteger('transaction_type')->default(1);
            $table->string('description')->nullable();
            $table->text('observation')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->dateTime('creation_date')->nullable();
            $table->dateTime('receipt_date')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->dateTime('realized_date')->nullable();
            $table->dateTime('billing_date')->nullable();
            $table->unsignedInteger('category_id')->nullable();
            $table->integer('bank_account_id')->nullable();
            $table->unsignedTinyInteger('payment_method')->default(1);
            $table->unsignedInteger('num_installments')->default(1);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->unsignedTinyInteger('period')->default(1);
            $table->string('pix_key')->nullable();
            $table->string('bank')->nullable();
            $table->string('agency')->nullable();
            $table->string('checking_account')->nullable();
            $table->string('ticket_file_directory')->nullable();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('job');
            $table->foreign('category_id')->references('id')->on('category');
            $table->foreign('bank_account_id')->references('id')->on('bank_account');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction');
    }
}
