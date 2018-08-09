<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientComission extends Model
{
    public $timestamps = false;

    protected $table = 'client_comission';

    protected $fillable = [
        'description'
    ];

    public static function byDescription($description) {
        $clientComission = ClientComission::where('description', '=', $description)->get();

        if($clientComission->count() == 0) {
            throw new \Exception('A comissão de tipo ' . $description . ' não existe.');
        }

        return $clientComission->first();
    }
}
