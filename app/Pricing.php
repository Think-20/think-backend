<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pricing extends Model
{
    public $timestamps = false;

    protected $table = 'pricing';

    protected $fillable = [
        'price', 'item_id', 'measure_id', 'provider_id', 'date', 'id'
    ];

    public static function edit($data) {
        $pricing = Pricing::find($data['id']);
        #$pricing->checkDuplicate();

        $pricing->price = isset($data['price']) ? $data['price'] : null;
        $pricing->provider_id = isset($data['provider']['id']) ? $data['provider']['id'] : null;
        $pricing->measure_id = isset($data['measure']['id']) ? $data['measure']['id'] : null;
        $pricing->update($data);
    }

    public static function insert(array $data, int $itemId) {
        $pricing = new Pricing($data);
        #$pricing->checkDuplicate();
        $pricing->item_id = $itemId;
        $pricing->provider_id = isset($data['provider']['id']) ? $data['provider']['id'] : null;
        $pricing->measure_id = isset($data['measure']['id']) ? $data['measure']['id'] : null;
        $pricing->save();
        return $pricing;
    }

/*
    public function checkDuplicate() {
        $duplicatePricing = Pricing::where('item_id', '=', $this->item_id)
            ->where('provider_id', '=', $this->provider_id)
            ->where('measure_id', '=', $this->measure_id)
        ->get();

        if($duplicatePricing->count() == 0) {
            return false;
        } else if($duplicatePricing->count() == 1 && $duplicatePricing->last()->id == $this->id) {
            return false;
        }
        
        throw new \Exception('O preço ' . $this->price . ' para o fornecedor ' . $this->provider->fantasy_name . ' com a medida ' . $this->measure->description . ' já foi cadastrado.');
    }
*/

    public function setPriceAttribute($value) {
        $this->attributes['price'] = (float) str_replace(',', '.', $value);
    }

    public function getPriceAttribute($value) {
        return str_replace('.', ',', $value);
    }

    public function item() {
        return $this->belongsTo('App\Item', 'item_id');
    }

    public function provider() {
        return $this->belongsTo('App\Provider', 'provider_id');
    }

    public function measure() {
        return $this->belongsTo('App\Measure', 'measure_id');
    }
}
