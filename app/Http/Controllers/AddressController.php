<?php

namespace App\Http\Controllers;

use App\City;
use App\State;

use Illuminate\Http\Request;

class AddressController extends Controller
{
    public static function allStates() {
        return State::all();
    }

    public static function states(string $stateName) {
        return State::where('name', 'LIKE', $stateName . '%')
            ->orderBy('name', 'asc')
            ->get();
    }

    public static function cities(string $stateId, string $cityName) {
        $state = State::where('code', 'LIKE', $stateId)
                    ->orWhere('id', 'LIKE', $stateId)
                    ->first();

        return City::where('stateId', '=', $state->id)
            ->where('name', 'LIKE', $cityName . '%')
            ->orderBy('name', 'asc')
            ->get();
    }
}
