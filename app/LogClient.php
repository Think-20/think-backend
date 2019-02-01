<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogClient extends Model
{
    public $timestamps = false;
    protected $table = 'log_client';

    protected $fillable = [
        'client_id', 'description', 'type'
    ];

    public static function insert(array $data) {
        /*
        [
            'client_id' => $clientId,
            'description' => $description,
            'type' => $type
        ]
        */
        $logClient = LogClient::create($data);
        $logClient->save();
    }

}
