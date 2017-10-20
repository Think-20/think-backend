<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    public $timestamps = false;

    protected $table = 'city';

    protected $fillable = [
        'name', 'state_id'
    ];

    public static function byName($stateName, $cityName) {
        $state = State::where('name', '=', $stateName)
        ->orWhere('code', '=', $stateName)
        ->get();

        if($state->count() == 0) {
            throw new \Exception('O estado informado ' . $stateName . ' não existe.');
        }

        $city = City::where('name', '=', $cityName)
        ->where('state_id', '=', $state->first()->id)
        ->get();
        
        if($city->count() == 0) {
            throw new \Exception('A cidade informada ' . $cityName . ' não existe.');
        }

        return $city->first();
    }

    public function state() {
        return $this->belongsTo('App\State', 'state_id');
    }
}
