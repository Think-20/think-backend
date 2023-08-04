<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class Display extends Model
{
    protected $table = 'display';

    protected $fillable = [
        'url', 'description'
    ];

    public static function filter(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $search = isset($data['search']) ? $data['search'] : null;
        $query = Display::select();

        if( ! is_null($search) ) {
            $query->where('url', 'LIKE', '%' . $search . '%');
            $query->orWhere('description', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('description', 'asc');

        if($paginate) {
            $displays = $query->paginate(20);
        } else {
            $displays = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $displays,
            'updatedInfo' => Display::updatedInfo()
        ];
    }

    public static function list(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $query = Display::orderBy('description', 'asc');

        if($paginate) {
            $displays = $query->paginate(20);
        } else {
            $displays = $query->get();
        }       

        return [
            'pagination' => $displays,
            'updatedInfo' => Display::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $display = Display::find($id);
            $display->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $display = new Display($data);
            $display->save();
            DB::commit();
            return $display;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $display = Display::find($id);
            $display->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $display = Display::where('display.id', '=', $id)
        ->first();
                
        if(is_null($display)) {
            return null;
        }

        return $display;
    }

    public static function updatedInfo() {
        $lastData = Display::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => ''
        ];
    }
    
}
