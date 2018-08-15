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

    public static function automatic() {
        return Agent::where('description', '=', 'Sistema')->first();
    }

    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
