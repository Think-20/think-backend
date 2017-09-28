<?php

namespace App;

use Exception;
use Request;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public $timestamps = false;

    protected $table = 'item';

    protected $fillable = [
        'name', 'description', 'price', 'itemCategoryId', 'costCategoryId'
    ];

    public static function edit(array $data) {
        $id = $data['id'];
        $item = Item::find($id);
        $item->itemCategoryId = isset($data['itemCategory']['id']) ? $data['itemCategory']['id'] : null;
        $item->costCategoryId = isset($data['costCategory']['id']) ? $data['costCategory']['id'] : null;
        return $item->update($data);
    }

    public static function insert(array $data) {
        #dd(Request::file('image'));
        $item = new Item($data);
        $item->itemCategoryId = isset($data['itemCategory']['id']) ? $data['itemCategory']['id'] : null;
        $item->costCategoryId = isset($data['costCategory']['id']) ? $data['costCategory']['id'] : null;
        $item->save();
    }

    public static function list() {
        return Item::orderBy('name', 'asc')->get();
    }

    public static function remove($id) {
        $item = Item::find($id);
        $item->delete();
    }

    public static function get(int $id) {
        $item = Item::find($id);
        $item->itemCategory;
        $item->costCategory;
        return $item;
    }

    public static function filter($query) {
        return Item::where('description', 'like', $query . '%')
            ->get();
    }

    public function setPriceAttribute($value) {
        $this->attributes['price'] = (float) str_replace(',', '.', $value);
    }

    public function itemCategory() {
        return $this->belongsTo('App\ItemCategory', 'itemCategoryId');
    }

    public function costCategory() {
        return $this->belongsTo('App\CostCategory', 'costCategoryId');
    }
}
