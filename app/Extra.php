<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;

class Extra extends Model
{
    protected $table = 'extra';

    protected $guarded = ['id'];

    public static function list()
    {
        $extras = Extra::get();

        foreach ($extras as $extra) {
            $extra->items;
            $extra->job_object;
            $extra->requester_object;
            $extra->budget_object;
            $extra->billing_employee_object;
            
        }

        return $extras; 
    }

    public static function listJob($job_id)
    {
        $extras = Extra::where('job_id', $job_id)->first();

        foreach ($extras as $extra) {
            $extra->items;
            $extra->job_object;
            $extra->requester_object;
            $extra->budget_object;
            $extra->billing_employee_object;
            
        }

        return $extras;
    }


    public static function getUnique(int $id = null)
    {
        $extra = Extra::find($id);

        if (!$extra) {
            return null;
        }

        $extra->items;
        $extra->job_object;
        $extra->requester_object;
        $extra->budget_object;
        $extra->billing_employee_object;
        
        

        return $extra;
    }

    public static function getByHash (int $id = null, string $hash)
    {
        //Verifica se o id e hash recebido e valido e tem um checkin correspondente
        $checkin = Checkin::where('id', '=', $id)
        ->where('hash', '=', $hash)
        ->first();

        //caso esteja vazio, quer dizer que n foi encontrado, logo sai da funcao e retorna um erro
        if (!$checkin) {
            return false;
        }

        $extras = Extra::where("checkin_id","=",$id)->get();

        if (!$extras) {
            return null;
        }

        foreach ($extras as $extra) {
            $extra->items;
            $extra->job_object;
            $extra->requester_object;
            $extra->budget_object;
            $extra->billing_employee_object;
            
        }

        return $extras;
    }

    public function job_object()
    {
        return $this->hasOne(Job::class, "id", "job_id");
    }

    public function items()
    {
        return $this->hasMany('App\ExtraItem', 'extra_id')->with('requester_object','budget_object','billing_employee_object')->orderBy('created_at', 'desc');
    }

}
