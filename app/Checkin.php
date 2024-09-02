<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Checkin extends Model
{
    protected $table = 'checkin';

    protected $guarded = ['id'];

    public static function list()
    {
        $checkins = Checkin::get();


        foreach ($checkins as $checkin) {
            $checkin->job;
            $checkin->project_object;
            $checkin->memorial_object;
            $checkin->budget_object;
            $checkin->approval_employee;
            $checkin->accept_proposal_employee;
            $checkin->accept_production_employee;
            $checkin->board_approval_employee;
            $checkin->event_object;
            $checkin->client_object;
            $checkin->agency_object;
            $checkin->billing_client_object;
            $checkin->costumer_service_employee_object;
            $checkin->costumer_service_employee2_object;
            $checkin->creation_employee_object;
            $checkin->creation_employee2_object;

            $checkin->production_manager_employee_object;
            $checkin->production_manager_employee2_object;
            $checkin->budget_employee_object;
            $checkin->budget_employee2_object;
            $checkin->detailing_employee_object;
            $checkin->detailing_employee2_object;
            $checkin->production_employee_object;
            $checkin->production_employee2_object;


            $checkin->bv_customer_service_object;
            $checkin->billing_amount_approved_by_object;
            $checkin->extras_approved_by_object;
            $checkin->organization__object;
        }

        return $checkins;
    }

    public static function getUnique(int $id = null)
    {
        $job = Job::find($id);
        $job->job_activity;
        $job->job_type;
        $job->client;

        if ($job->client)
            $job->client->contacts;

        $job->main_expectation;
        $job->levels;
        $job->how_come;
        $job->agency;

        if ($job->agency)
            $job->agency->contacts;

        $job->attendance;
        $job->competition;
        $job->files;
        $job->status;
        $job->responsibles();
        $job->history();
        return $job;
    }

    public function job()
    {
        return $this->belongsTo(/*'App\Job', 'job_id'*/Job::class);
    }

    public function project_object()
    {
        return $this->hasOne(Task::class, "id", "project");
    }

    public function memorial_object()
    {
        return $this->hasOne(Task::class, "id", "memorial");
    }

    public function budget_object()
    {
        return $this->hasOne(Task::class, "id", "budget");
    }

    public function approval_employee()
    {
        return $this->hasOne(Employee::class, "id", "approval_employee_id");
    }

    public function accept_proposal_employee()
    {
        return $this->hasOne(Employee::class, "id", "accept_proposal_employee_id");
    }

    public function accept_production_employee()
    {
        return $this->hasOne(Employee::class, "id", "accept_production_employee_id");
    }

    public function board_approval_employee()
    {
        return $this->hasOne(Employee::class, "id", "board_approval_employee_id");
    }

    public function event_object()
    {
        return $this->hasOne(Event::class, "id", "event_id");
    }

    public function client_object()
    {
        return $this->hasOne(Client::class, "id", "client_id");
    }

    public function agency_object()
    {
        return $this->hasOne(Client::class, "id", "agency_id");
    }

    public function billing_client_object()
    {
        return $this->hasOne(Client::class, "id", "billing_client_id");
    }

    public function costumer_service_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "costumer_service_employee");
    }

    public function costumer_service_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "costumer_service_employee2");
    }

    public function production_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "production_employee");
    }

    public function production_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "production_employee");
    }
    
    public function production_manager_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "production_manager_employee");
    }

    public function production_manager_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "production_manager_employee2");
    }

    public function budget_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "budget_employee");
    }

    public function budget_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "budget_employee2");
    }

    public function detailing_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "detailing_employee");
    }

    public function detailing_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "detailing_employee2");
    }

    public function creation_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "creation_employee");
    }

    public function creation_employee2_object()
    {
        return $this->hasOne(Employee::class, "id", "creation_employee2");
    }

    public function bv_customer_service_object()
    {
        return $this->hasOne(Employee::class, "id", "bv_customer_service");
    }

    public function billing_amount_approved_by_object()
    {
        return $this->hasOne(Employee::class, "id", "billing_amount_approved_by");
    }

    public function extras_approved_by_object()
    {
        return $this->hasOne(Employee::class, "id", "extras_approved_by");
    }

    public function organization_object()
    {
        return $this->hasOne(Organization::class, "id", "organization_id");
    }


    /*
            $table->foreign('production_manager_employee')->references('id')->on('employee')->nullable();
            $table->foreign('production_manager_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('budget_employee')->references('id')->on('employee')->nullable();
            $table->foreign('budget_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('detailing_employee')->references('id')->on('employee')->nullable();
            $table->foreign('detailing_employee2')->references('id')->on('employee')->nullable();
            $table->foreign('production_employee')->references('id')->on('employee')->nullable();
            $table->foreign('production_employee2')->references('id')->on('employee')->nullable();
    */
}
