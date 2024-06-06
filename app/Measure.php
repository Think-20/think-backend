<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Measure extends Model
{
    public $timestamps = false;

    protected $table = 'measure';

    protected $fillable = [
        'description'
    ];

    public static function list() {
        return Measure::all();
    }

    public static function filter($query) {
        return Measure::where('description', 'like', $query . '%')->get();
    }

    public static function manage(array $measuresDataArray, Measureable $measureable) {
        $oldMeasures = $measureable->measures;
        $measureIds = [];

        foreach($measuresDataArray as $measure) {
            //Exists, update
            if(isset($measure['id'])) {
                $measureIds[] = $measure['id'];
                Measure::edit($measure);
            } 
            //Create because not found
            else {
                Measure::insert($measure, $measureable);
            }
        }

        Measure::deleteOldIds($oldMeasures, $measureIds, $measureable);
    }

    public static function deleteOldIds($oldMeasures, array $measureIds, Measureable $measureable) {
        foreach($oldMeasures as $measure) {
            if(!in_array($measure->id, $measureIds)) {
                $measureable->measures()->detach($measure);
                $measure->delete();
            }
        }
    }

    public static function edit($data) {
        $measure = Measure::find($data['id']);
        $measure->update($data);
    }

    public static function insert(array $data, Measureable $measureable) {
        $measure = new Measure($data);
        $measureable->measures()->save($measure);
    }
}
