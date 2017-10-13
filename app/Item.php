<?php

namespace App;

use DB;
use Exception;
use Request;
use Illuminate\Database\Eloquent\Model;

use App\Interfaces\Priceless;

class Item extends Model implements Priceless
{
    public $timestamps = false;

    protected $table = 'item';

    protected $fillable = [
        'name', 'description', 'image', 'item_category_id', 'cost_category_id'
    ];

    public static function edit(array $data) {
        DB::beginTransaction();

        try {
            $id = $data['id'];
            $item = Item::find($id);
            $item->checkDuplicate();
            $item->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;
            $item->cost_category_id = isset($data['cost_category']['id']) ? $data['cost_category']['id'] : null;
            $item->update($data);

            $pricings = isset($data['pricings']) ? $data['pricings'] : [];
            Pricing::manage($pricings, $item);

            DB::commit();
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();

        try {
            #dd(Request::file('image'));
            $item = new Item($data);
            $item->checkDuplicate();
            $item->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;
            $item->cost_category_id = isset($data['cost_category']['id']) ? $data['cost_category']['id'] : null;
            $item->save();

            $pricings = isset($data['pricings']) ? $data['pricings'] : [];
            Pricing::manage($pricings, $item);

            DB::commit();
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function checkDuplicate() {
        $duplicateItems = Item::where('name', '=', $this->name)->get();
        if($duplicateItems->count() == 0) {
            return false;
        } else if($duplicateItems->count() == 1 && $duplicateItems->last()->id == $this->id) {
            return false;
        }

        throw new \Exception('O item ' . $this->name . ' já foi cadastrado.');
    }

    public static function list() {
        $items = Item::orderBy('name', 'asc')->get();

        foreach($items as $item) {
            $item->item_category;
            $item->cost_category;
            $item->pricings;
        }

        return $items;
    }

    public static function remove($id) {
        DB::beginTransaction();

        try {
            $item = Item::find($id);
            $item->pricings()->delete();
            $item->delete();

            DB::commit();
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $item = Item::find($id);
        $item->item_category;
        $item->cost_category;

        foreach($item->pricings as $pricing) {
            $pricing->measure;
            $pricing->provider;
        }

        return $item;
    }

    public static function filter($query) {
        $items = Item::where('name', 'like', $query . '%')
            ->orWhere('description', 'like', $query . '%')
            ->get();

        foreach($items as $item) {
            $item->item_category;
            $item->cost_category;
            $item->pricings;
        }

        return $items;
    }

    public function pricings() {
        return $this->hasMany('App\Pricing', 'item_id');
    }

    public function item_category() {
        return $this->belongsTo('App\ItemCategory', 'item_category_id');
    }

    public function cost_category() {
        return $this->belongsTo('App\CostCategory', 'cost_category_id');
    }
}
