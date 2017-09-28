<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CostCategory extends Model
{
    public $timestamps = false;

    protected $table = 'cost_category';

    protected $fillable = [
        'description'
    ];

    public static function edit(array $data) {
        $id = $data['id'];
        $costCategory = CostCategory::find($id);
        return $costCategory->update($data);
    }

    public static function insert(array $data) {
        $costCategory = new CostCategory($data);
        $costCategory->save();
    }

    public static function remove($id) {
        $costCategory = CostCategory::find($id);
        $costCategory->delete();
    }

    public static function list() {
        return CostCategory::orderBy('description', 'asc')->get();
    }

    public static function get(int $id) {
        $costCategory = CostCategory::find($id);
        return $costCategory;
    }

    public static function filter($query) {
        return CostCategory::where('description', 'like', $query . '%')
            ->get();
    }
}
