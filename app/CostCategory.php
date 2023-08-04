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

    public static function edit(array $data)
    {
        $id = $data['id'];
        $costCategory = CostCategory::find($id);
        return $costCategory->update($data);
    }

    public static function insert(array $data)
    {
        $costCategory = new CostCategory($data);
        $costCategory->save();
    }

    public static function remove($id)
    {
        $costCategory = CostCategory::find($id);
        $costCategory->delete();
    }

    public static function list(array $data)
    {
        $costCategories = CostCategory::orderBy('description', 'asc')->get();

        return [
            'pagination' => $costCategories,
            'updatedInfo' => CostCategory::updatedInfo()
        ];
    }

    public static function updatedInfo()
    {
        return [];
    }

    public static function get(int $id)
    {
        $costCategory = CostCategory::find($id);
        return $costCategory;
    }

    public static function filter(array $params)
    {
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;
        $description = isset($params['search']) ? $params['search'] : '';

        $costCategories = CostCategory::where('description', 'like', $description . '%');

        if ($paginate) {
            $paginate = $costCategories->paginate(50);
            $page = $paginate->currentPage();
            $total = $paginate->total();

            return [
                'pagination' => $paginate,
                'updatedInfo' => CostCategory::updatedInfo()
            ];
        } else {
            $result = $costCategories->get();
            $total = $costCategories->count();
            $page = 0;

            return [
                'pagination' => [
                    'data' => $result,
                    'total' => $total,
                    'page' => $page
                ],
                'updatedInfo' => CostCategory::updatedInfo()
            ];
        }
    }
}
