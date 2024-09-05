<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCheckinTable extends Migration
{

    public function up()
    {
        Schema::create('checkin', function (Blueprint $table) {

            //COLUNAS
            $table->increments('id');
            $table->integer('job_id');

            $table->integer('project')->nullable();
            $table->integer('memorial')->nullable();
            $table->integer('budget')->nullable();


            $table->boolean('approval')->nullable();
            $table->integer('approval_employee_id')->nullable();
            $table->date('approval_date')->nullable();

            $table->boolean('accept_proposal')->nullable();
            $table->integer('accept_proposal_employee_id')->nullable();
            $table->date('accept_proposal_date')->nullable();

            $table->boolean('accept_production')->nullable();
            $table->integer('accept_production_employee_id')->nullable();
            $table->date('accept_production_date')->nullable();

            $table->boolean('board_approval')->nullable();
            $table->integer('board_approval_employee_id')->nullable();
            $table->date('board_approval_date')->nullable();

            $table->integer('area')->nullable();
            $table->string('config')->nullable();
            $table->string('location')->nullable();
            $table->string('pavilion')->nullable();

            $table->unsignedInteger('organization_id')->nullable();

            $table->string('promoter_name')->nullable();
            $table->string('promoter_login')->nullable();
            $table->string('promoter_password')->nullable();
            $table->string('promoter_changed_by')->nullable();
            $table->string('promoter_changed_in')->nullable();

            $table->integer('event_id')->nullable();

            $table->string('approval_note')->nullable();

            $table->integer('client_id')->nullable();
            $table->integer('agency_id')->nullable();
            $table->string('contact_obs')->nullable();

            $table->integer('billing_client_id')->nullable();

            $table->integer('costumer_service_employee')->nullable();
            $table->integer('costumer_service_comission')->nullable();
            $table->integer('costumer_service_employee2')->nullable();
            $table->integer('costumer_service_comission2')->nullable();

            $table->integer('creation_employee')->nullable();
            $table->integer('creation_comission')->nullable();
            $table->integer('creation_employee2')->nullable();
            $table->integer('creation_comission2')->nullable();


            $table->integer('production_manager_employee')->nullable();
            $table->integer('production_manager_comission')->nullable();
            $table->integer('production_manager_employee2')->nullable();
            $table->integer('production_manager_comission2')->nullable();

            $table->integer('budget_employee')->nullable();
            $table->integer('budget_comission')->nullable();
            $table->integer('budget_employee2')->nullable();
            $table->integer('budget_comission2')->nullable();

            $table->integer('detailing_employee')->nullable();
            $table->integer('detailing_comission')->nullable();
            $table->integer('detailing_employee2')->nullable();
            $table->integer('detailing_comission2')->nullable();

            $table->integer('production_employee')->nullable();
            $table->integer('production_comission')->nullable();
            $table->integer('production_employee2')->nullable();
            $table->integer('production_comission2')->nullable();

            $table->string('billing_obs')->nullable();

            $table->integer('billing_amount')->nullable();
            $table->integer('value_base_for_calculation')->nullable();
            $table->integer('bv')->nullable();

            $table->integer('bv_customer_service')->nullable();

            $table->integer('taxes')->nullable();
            $table->integer('equipment')->nullable();
            $table->integer('logistics')->nullable();
            $table->integer('credentials_fees')->nullable();
            $table->integer('insurance')->nullable();
            $table->integer('others')->nullable();
            $table->integer('discount')->nullable();
            $table->integer('final_contract_value')->nullable();

            $table->integer('billing_amount_approved_by')->nullable();
            $table->date('billing_amount_approved_at')->nullable();

            $table->integer('billing_amount_discount_interest')->nullable();
            $table->integer('total_amount_received')->nullable();
            $table->string('billing_amount_obs')->nullable();

            $table->integer('total_amount_extras')->nullable();

            $table->integer('extras_approved_by')->nullable();
            $table->date('extras_approved_at')->nullable();
            $table->integer('extras_discount_interest')->nullable();
            $table->integer('total_amount_extras_received')->nullable();
            $table->string('extras_obs')->nullable();

            #campos novos
            $table->integer('event_changed_by')->nullable();
            $table->date('event_changed_in')->nullable();
            $table->string('organization_login')->nullable();
            $table->string('organization_password')->nullable();

            $table->integer('organization_changed_by')->nullable();
            $table->date('organization_changed_in')->nullable();

            #começo das foreign
            $table->foreign('job_id')->references('id')->on('job')->nullable();
            $table->foreign('project')->references('id')->on('task')->nullable();
            $table->foreign('memorial')->references('id')->on('task')->nullable();
            $table->foreign('budget')->references('id')->on('task')->nullable();

            $table->foreign('approval_employee_id')->references('id')->on('employee')->nullable();
            $table->foreign('accept_proposal_employee_id')->references('id')->on('employee')->nullable();
            $table->foreign('accept_production_employee_id')->references('id')->on('employee')->nullable();
            $table->foreign('board_approval_employee_id')->references('id')->on('employee')->nullable();

            $table->foreign('event_id')->references('id')->on('event')->nullable();

            $table->foreign('client_id')->references('id')->on('client')->nullable();
            $table->foreign('agency_id')->references('id')->on('client')->nullable();

            $table->foreign('billing_client_id')->references('id')->on('client')->nullable();

            $table->foreign('costumer_service_employee')->references('id')->on('employee')->nullable();
            $table->foreign('costumer_service_employee2')->references('id')->on('employee')->nullable();

            $table->foreign('creation_employee')->references('id')->on('employee')->nullable();
            $table->foreign('creation_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('event_changed_by')->references('id')->on('employee')->nullable();
            $table->foreign('organization_changed_by')->references('id')->on('employee')->nullable();


            $table->foreign('production_manager_employee')->references('id')->on('employee')->nullable();
            $table->foreign('production_manager_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('budget_employee')->references('id')->on('employee')->nullable();
            $table->foreign('budget_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('detailing_employee')->references('id')->on('employee')->nullable();
            $table->foreign('detailing_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('production_employee')->references('id')->on('employee')->nullable();
            $table->foreign('production_employee2')->references('id')->on('employee')->nullable();

            $table->foreign('bv_customer_service')->references('id')->on('employee')->nullable();
            $table->foreign('billing_amount_approved_by')->references('id')->on('employee')->nullable();
            $table->foreign('extras_approved_by')->references('id')->on('employee')->nullable();

            $table->foreign('organization_id')->references('id')->on('organization')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('checkin');
    }
}
