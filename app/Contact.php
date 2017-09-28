<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    public $timestamps = false;

    protected $table = 'contact';

    protected $fillable = [
        'name', 'email', 'department', 'cellphone', 'clientId'
    ];

    public static function insert(array $data) {
        $contact = new Contact($data);
        $contact->save();
    }

    public function getCellphoneAttribute($value) {
        $phone = null;

        if(strlen($value) == 10) {
            $phone = mask($value, '(##) ####-####');
        } else if(strlen($value) == 11) {
            $phone = mask($value, '(##) ####-#####');
        }

        return $phone;
    }

    public function setCellphoneAttribute($value) {
        $this->attributes['cellphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
}
