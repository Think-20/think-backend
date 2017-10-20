<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientType extends Model
{
    public $timestamps = false;

    protected $table = 'client_type';

    protected $fillable = [
        'description'
    ];

    public static function byDescription($description) {
        $clientType = ClientType::where('description', '=', $description)->get();

        if($clientType->count() == 0) {
            throw new \Exception('O cliente de tipo ' . $description . ' não existe.');
        }

        return $clientType->first();
    }
}
