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

    public static function list() {
        return ItemCategory::orderBy('description', 'asc')->get();
    }

    public static function remove($id) {
        $itemCategory = ItemCategory::find($id);
        $itemCategory->delete();
    }

    public static function get(int $id) {
        $itemCategory = ItemCategory::find($id);
        $itemCategory->itemCategory;
        return $itemCategory;
    }

    public static function filter($query) {
        return ItemCategory::where('description', 'like', $query . '%')
            ->get();
    }

    public function itemCategory() {
        return $this->belongsTo('App\ItemCategory', 'item_category_id');
    }
}
