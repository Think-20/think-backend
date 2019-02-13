<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class Event extends Model
{
    protected $table = 'event';

    protected $fillable = [
        'description', 'place_id', 'edition', 'note', 'ini_date', 'fin_date', 'ini_hour', 'fin_hour'
    ];

    public static function filter(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $search = isset($data['search']) ? $data['search'] : null;
        $query = Event::with('place');

        if( ! is_null($search) ) {
            $query->where('description', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('description', 'asc');

        if($paginate) {
            $events = $query->paginate(20);
        } else {
            $events = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $events,
            'updatedInfo' => Event::updatedInfo()
        ];
    }

    public static function list(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $query = Event::with('place')->orderBy('description', 'asc');

        if($paginate) {
            $events = $query->paginate(20);
        } else {
            $events = $query->get();
        }       

        return [
            'pagination' => $events,
            'updatedInfo' => Event::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $event = Event::find($id);
            $event->fill($data);
            $event->checkIfDuplicate();
            $event->place_id = isset($data['place']['id']) ? $data['place']['id'] : null;
            $event->update();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $event = new Event($data);
            $event->checkIfDuplicate();
            $event->place_id = isset($data['place']['id']) ? $data['place']['id'] : null;
            $event->save();
            DB::commit();
            return $event;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function checkIfDuplicate() {
        $query = Event::where('description', '=', $this->description)
        ->where('edition', '=', $this->edition);
        if($this->id != null) {
            $query->where('id', '<>', $this->id);
        }
        $found = $query->count() > 0 ? true : false;

        if(!$found) return true;

        throw new \Exception('Já existe um evento com essa descrição e edição cadastrado.');
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $event = Event::find($id);
            $event->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $event = Event::with('place')
        ->where('event.id', '=', $id)
        ->first();
                
        if(is_null($event)) {
            return null;
        }

        return $event;
    }

    public static function updatedInfo() {
        $lastData = Event::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => ''
        ];
    }

    public function setIniDateAttribute($value) {
        $this->attributes['ini_date'] = substr($value, 0, 10);
    }

    public function setFinDateAttribute($value) {
        $this->attributes['fin_date'] = substr($value, 0, 10);
    }

    public function place() {
        return $this->belongsTo('App\Place', 'place_id');
    }
}
