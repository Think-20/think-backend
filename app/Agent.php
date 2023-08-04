<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model implements NotifierInterface
{
    use SoftDeletes;
    
    public $timestamps = false;

    protected $table = 'agent';

    protected $fillable = [
        'name', 'description'
    ];

    protected $dates = [
        'deleted_at'
    ];


    public function getOficialId(): int {
        return 0;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLogo(): string {
        return '';
    }

    public static function automatic(): Agent {
        return Agent::all()->first();
    }

    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
