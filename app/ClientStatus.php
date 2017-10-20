<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ClientStatus extends Model
{
    public $timestamps = false;

    protected $table = 'client_status';

    protected $fillable = [
        'description'
    ];
    
    public static function byDescription($description) {
        $clientStatus = ClientStatus::where('description', '=', $description)->get();

        if($clientStatus->count() == 0) {
            throw new \Exception('O cliente de status ' . $description . ' não existe.');
        }

        return $clientStatus->first();
    }
}
