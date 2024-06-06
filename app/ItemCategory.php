<?php

namespace App;

use Exception;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    public $timestamps = false;

    protected $table = 'item_category';

    protected $fillable = [
        'description', 'item_category_id'
    ];

    public static function edit(array $data) {
        $id = $data['id'];
        $itemCategory = ItemCategory::find($id);
        $itemCategory->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;

        if($itemCategory->id == $itemCategory->item_category_id) {
            throw new Exception('Não é possível cadastrar uma categoria sendo a própria subcategoria.');
        }

        return $itemCategory->update($data);
    }

    public static function insert(array $data) {
        $itemCategory = new ItemCategory($data);
        $itemCategory->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;
        $itemCategory->save();
    }

    public static function list(array $data)
    {
        $itemCategories = ItemCategory::with('item_category')->orderBy('description', 'asc')->get();

        return [
            'pagination' => $itemCategories,
            'updatedInfo' => ItemCategory::updatedInfo()
        ];
    }

    public static function updatedInfo()
    {
        return [];
    }

    public static function itemsGroupByCategory() {
        $categories = ItemCategory::with('item_categories', 'items')
        ->whereNull('item_category_id')
        ->orderBy('description', 'asc')->get();

        return $categories;
    }

    public static function remove($id) {
        $itemCategory = ItemCategory::find($id);
        $itemCategory->delete();
    }

    public static function get(int $id) {
        $itemCategory = ItemCategory::find($id);
        $itemCategory->item_category;
        return $itemCategory;
    }

    public static function filter(array $params)
    {
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;
        $description = isset($params['search']) ? $params['search'] : '';

        $itemCategories = ItemCategory::with('item_category')
        ->where('description', 'like', $description . '%');

        if ($paginate) {
            $paginate = $itemCategories->paginate(50);
            $page = $paginate->currentPage();
            $total = $paginate->total();

            return [
                'pagination' => $paginate,
                'updatedInfo' => ItemCategory::updatedInfo()
            ];
        } else {
            $result = $itemCategories->get();
            $total = $itemCategories->count();
            $page = 0;

            return [
                'pagination' => [
                    'data' => $result,
                    'total' => $total,
                    'page' => $page
                ],
                'updatedInfo' => ItemCategory::updatedInfo()
            ];
        }
    }

    public function item_category() {
        return $this->belongsTo('App\ItemCategory', 'item_category_id');
    }

    public function item_categories() {
        return $this->hasMany('App\ItemCategory', 'item_category_id')
        ->with('item_categories', 'items');
    }

    public function items() {
        return $this->hasMany('App\Item', 'item_category_id');
    }
}
