<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StandItem extends Model
{
    public $timestamps = false;

    protected $table = 'stand_item';

    protected $fillable = [
        'title', 'quantity', 'description', 'stand_item_type_id'
    ];

    public function type() {
        return $this->belongsTo('App\StandItemType', 'stand_item_type_id');
    }
}
