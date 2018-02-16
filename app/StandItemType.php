<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StandItemType extends Model
{
    protected $table = 'stand_item_type';

    protected $fillable = [
        'description'
    ];
}
