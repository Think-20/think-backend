<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payment';

    protected $guarded = ['id'];

    public static function list()
    {
        $payments = Payment::get();

        foreach ($payments as $payment) {
            $payment->checkin_object;
        }

        return $payments;
    }


    public static function getUnique(int $id = null)
    {
        $payment = Payment::find($id);

        $payment->checkin_object;


        return $payment;
    }

    public function checkin_object()
    {
        return $this->hasOne(Checkin::class, "id", "checkin_id");
    }
}
