<?php

namespace App;

use Exception;
use Illuminate\Database\Eloquent\Model;

class ItemType extends Model
{
    public $timestamps = false;

    protected $table = 'item_type';

    protected $fillable = [
        'description'
    ];
}
