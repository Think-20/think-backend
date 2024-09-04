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
            $extra->checkin_object;
            $extra->requester_object;
            $extra->budget_object;
        }

        return $extras;
    }


    public static function getUnique(int $id = null)
    {
        $extra = Extra::find($id);

        $extra->checkin_object;
        $extra->requester_object;
        $extra->budget_object;

        return $extra;
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
}
