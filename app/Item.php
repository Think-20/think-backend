<?php

namespace App;

use DB;
use Exception;
use Request;
use Illuminate\Database\Eloquent\Model;

use App\Interfaces\Priceless;

class Item extends Model
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
            $item->item_type_id = isset($data['item_type']) ? ($data['item_type'] == true ? '1' : '0') : null;
            $item->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;
            $item->cost_category_id = isset($data['cost_category']['id']) ? $data['cost_category']['id'] : null;
            $item->update($data);

            DB::commit();
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();

        try {
            $item = new Item($data);
            $item->checkDuplicate();
            $item->item_type_id = isset($data['item_type']) ? ($data['item_type'] == true ? '1' : '0') : null;
            $item->item_category_id = isset($data['item_category']['id']) ? $data['item_category']['id'] : null;
            $item->cost_category_id = isset($data['cost_category']['id']) ? $data['cost_category']['id'] : null;
            $item->save();

            DB::commit();
            return $item;
        } catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function addPricing(array $data) {
        if(isset($data['id'])) {
            $pricing = Pricing::find($data['id']);
            $pricing2 = new Pricing($data);
            if($pricing->price === $pricing2->price) {
                return $pricing;
            }
        }

        $pricing = Pricing::insert($data, $this->id);
        return $pricing;
    }

    public function removePricing(int $pricingId) {
        $pricing = Pricing::find($pricingId);
        $pricing->delete();
    }

    public function addChildItem(array $data) {
        if(isset($data['id'])) {
            $childItem = ChildItem::edit($data);
            return $childItem;
        }

        $childItem = ChildItem::insert($data, $this->id);
        return $childItem;
    }

    public function removeChildItem(int $childItemId) {
        $childItem = ChildItem::find($childItemId);
        $childItem->delete();
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

    public static function list(array $data)
    {
        $items = Item::with('item_category', 'cost_category', 'item')->orderBy('name', 'asc')->get();

        return [
            'pagination' => $items,
            'updatedInfo' => Item::updatedInfo()
        ];
    }

    public static function updatedInfo()
    {
        return [];
    }

    public static function remove($id) {
        DB::beginTransaction();

        try {
            $item = Item::find($id);
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
        $item->item_type;
        $item->pricings = $item->pricings();
        $item->child_items;

        return $item;
    }

    public static function filter(array $params)
    {
        $paginate = isset($params['paginate']) ? $params['paginate'] : true;
        $search = isset($params['search']) ? $params['search'] : '';

        $items = Item::with('item_category', 'cost_category', 'item')
        ->where('name', 'like', $search . '%')
        ->orWhere('description', 'like', $search . '%');

        if ($paginate) {
            $paginate = $items->paginate(50);
            $page = $paginate->currentPage();
            $total = $paginate->total();

            return [
                'pagination' => $paginate,
                'updatedInfo' => Item::updatedInfo()
            ];
        } else {
            $result = $items->get();
            $total = $items->count();
            $page = 0;

            return [
                'pagination' => [
                    'data' => $result,
                    'total' => $total,
                    'page' => $page
                ],
                'updatedInfo' => Item::updatedInfo()
            ];
        }
    }

    public function child_items() {
        return $this->hasMany('App\ChildItem', 'parent_item_id')
        ->with('measure')
        ->with('item');
    }

    public function pricings() {
        $pricings = DB::select('select p.* from pricing p 
inner join (select max(date) as max_date, item_id, measure_id, provider_id 
from pricing group by item_id, measure_id, provider_id) p2 
on p.item_id = p2.item_id and p.date = p2.max_date where p.item_id = :item_id;', ['item_id' => $this->id]);

        $pricingArray = [];

        foreach($pricings as $data) {
            $pricing = new Pricing((array) $data);
            $pricing->measure;
            $pricing->provider;
            $pricingArray[] = $pricing;
        }

        return $pricingArray;
    }

    public function item_type() {
        return $this->belongsTo('App\ItemType', 'item_type_id');
    }

    public function item_category() {
        return $this->belongsTo('App\ItemCategory', 'item_category_id');
    }

    public function cost_category() {
        return $this->belongsTo('App\CostCategory', 'cost_category_id');
    }
}
