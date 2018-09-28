<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Agent extends Model implements NotifierInterface
{
    public $timestamps = false;

    protected $table = 'agent';

    protected $fillable = [
        'name', 'description'
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

    public static function automatic() {
        return Agent::all()->first();
    }

    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
