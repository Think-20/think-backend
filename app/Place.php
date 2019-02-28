<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class Place extends Model
{
    protected $table = 'place';

    protected $fillable = [
        'name', 'city_id', 'street', 'number', 'neighborhood', 'complement', 'cep'
    ];

    public static function filter(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $search = isset($data['search']) ? $data['search'] : null;
        $query = Place::with('city', 'city.state');

        if( ! is_null($search) ) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('name', 'asc');

        if($paginate) {
            $places = $query->paginate(20);
        } else {
            $places = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $places,
            'updatedInfo' => Place::updatedInfo()
        ];
    }

    public static function list(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $query = Place::with('city', 'city.state')->orderBy('name', 'asc');

        if($paginate) {
            $places = $query->paginate(20);
        } else {
            $places = $query->get();
        }       

        return [
            'pagination' => $places,
            'updatedInfo' => Place::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $place = Place::find($id);
            $place->fill($data);
            $place->checkIfDuplicate();
            $place->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $place->update();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $place = new Place($data);
            $place->checkIfDuplicate();
            $place->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $place->save();
            DB::commit();
            return $place;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function checkIfDuplicate() {
        $query = Place::where('name', '=', $this->name);
        if($this->id != null) {
            $query->where('id', '<>', $this->id);
        }
        $found = $query->get()->count() > 0 ? true : false;

        if(!$found) return true;

        throw new \Exception('Já existe um local cadastrado com o mesmo nome.');
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $place = Place::find($id);
            $place->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $place = Place::with('city', 'city.state')
        ->where('place.id', '=', $id)
        ->first();
                
        if(is_null($place)) {
            return null;
        }

        return $place;
    }

    public static function updatedInfo() {
        $lastData = Place::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => ''
        ];
    }

    public function city() {
        return $this->belongsTo('App\City', 'city_id');
    }

    public function setNameAttribute($value) {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    public function getCepAttribute($value) {
        return mask(str_pad($value, 8, '0', STR_PAD_LEFT), '#####-###');
    }
    
    public function setCepAttribute($value) {
        $this->attributes['cep'] = preg_replace('/[^0-9]+/', '', $value);
    }
    
}
