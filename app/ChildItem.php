<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ChildItem extends Model
{
    public $timestamps = false;

    protected $table = 'child_item';

    protected $fillable = [
        'parent_item_id', 'child_item_id', 'measure_id', 'quantity'
    ];

    public static function edit($data) {
        $childItem = ChildItem::find($data['id']);
        $childItem->update($data);
        return $childItem;
    }

    public static function insert(array $data, int $itemId) {
        $childItem = new ChildItem($data);
        $childItem->parent_item_id = $itemId;
        $childItem->child_item_id = isset($data['item']['id']) ? $data['item']['id'] : null;
        $childItem->measure_id = isset($data['measure']['id']) ? $data['measure']['id'] : null;
        $childItem->save();
        return $childItem;
    }

    public function item() {
        return $this->belongsTo('App\Item', 'child_item_id');
    }

    public function measure() {
        return $this->belongsTo('App\Measure', 'measure_id');
    }

    public function setQuantityAttribute($value) {
        $this->attributes['quantity'] = (float) str_replace(',','.',$value);
    }

    public function getQuantityAttribute($value) {
        return str_replace('.',',', $value);
    }
}
