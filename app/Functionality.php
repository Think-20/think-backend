<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class Functionality extends Model
{
    protected $table = 'functionality';

    protected $fillable = [
        'url', 'description'
    ];

    public static function filter(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $search = isset($data['search']) ? $data['search'] : null;
        $query = Functionality::select();

        if( ! is_null($search) ) {
            $query->where('url', 'LIKE', '%' . $search . '%');
            $query->orWhere('description', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('description', 'asc');

        if($paginate) {
            $functionalities = $query->paginate(20);
        } else {
            $functionalities = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $functionalities,
            'updatedInfo' => Functionality::updatedInfo()
        ];
    }

    public static function list(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $query = Functionality::orderBy('description', 'asc');

        if($paginate) {
            $functionalities = $query->paginate(20);
        } else {
            $functionalities = $query->get();
        }       

        return [
            'pagination' => $functionalities,
            'updatedInfo' => Functionality::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $functionality = Functionality::find($id);
            $functionality->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $functionality = new Functionality($data);
            $functionality->save();
            DB::commit();
            return $functionality;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $functionality = Functionality::find($id);
            $functionality->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $functionality = Functionality::where('functionality.id', '=', $id)
        ->first();
                
        if(is_null($functionality)) {
            return null;
        }

        return $functionality;
    }

    public static function updatedInfo() {
        $lastData = Functionality::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => ''
        ];
    }
    
}
