<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExtraItem extends Model
{
    protected $table = 'extra_item';

    protected $guarded = ['id'];

    public static function list()
    {
        $extras = ExtraItem::get();

        foreach ($extras as $extra) {
            //$extra->checkin_object;
            $extra->requester_object;
            $extra->budget_object;
            $extra->billing_employee_object;
        }

        return $extras;
    }


    public static function getUnique(int $id = null)
    {
        $extra = ExtraItem::find($id);

        if (!$extra) {
            return null;
        }

        //$extra->checkin_object;
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

        $extras = ExtraItem::where("checkin_id","=",$id)->get();

        if (!$extras) {
            return null;
        }

        foreach ($extras as $extra) {
            //$extra->checkin_object;
            $extra->requester_object;
            $extra->budget_object;
            $extra->billing_employee_object;
        }

        return $extras;
    }

    public function checkin_object()
    {
        return $this->hasOne(Checkin::class, "id", "checkin_id");
    }

    public function requester_object()
    {
        return $this->hasOne(Person::class, "id", "requester");
    }

    public function budget_object()
    {
        return $this->hasOne(Employee::class, "id", "budget");
    }

    public function billing_employee_object()
    {
        return $this->hasOne(Employee::class, "id", "billing_employee_id");
    }

    public function extra_object()
    {
        return $this->hasOne(Extra::class, "id", "extra_id");
    }

}