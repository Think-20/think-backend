<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'person';

    protected $guarded = ['id'];

    public static function list()
    {
        $persons = Person::get();

        foreach ($persons as $person) {
            $person->bank_account_object;
        }

        return $persons;
    }


    public static function getUnique(int $id = null)
    {
        $person = Person::find($id);

        if (!$checkin) {
            return null;
        }

        $person->bank_account_object;

        return $person;
    }

    public function checkin_object()
    {
        return $this->hasOne(Checkin::class, "id", "checkin_id");
    }

    public function bank_account_object()
    {
        return $this->hasOne(BankAccount::class, "id", "bank_account_id");
    }
}
