<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobActivityShareDuration extends Model
{
    public $timestamps = false;

    protected $table = 'job_activity_share_duration';

    protected $fillable = [
        'from_id', 'to_id'
    ];    
    
    public function from() {
        return $this->belongsTo('App\JobActivity', 'from_id');
    }

    public function to() {
        return $this->belongsTo('App\JobActivity', 'to_id');
    }
}
