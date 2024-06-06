<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\Briefing;

class Stand extends Model
{
    public $timestamps = false;

    protected $table = 'stand';

    protected $fillable = [
        'briefing_id', 'configuration_id', 'place', 'plan', 'regulation', 'column', 'street_number',
        'genre_id', 'reference', 'closed_area_percent', 'note', 'note_opened_area', 'note_closed_area',
        'area', 'budget'
    ];

    public static function edit(array $data) {
        $id = $data['id'];
        $stand = Stand::find($id);
        $oldStand = clone $stand;
        $stand->update(
            array_merge($data, [
                'configuration_id' => $data['configuration']['id'],
                'column' => $data['column']['id'],
                'genre_id' => $data['genre']['id']
            ])
        );
        $stand->saveStandItems($data);
        $stand->editFiles($data);
        return $stand;
    }

    public static function insert(Briefing $briefing, array $data) {
        Stand::checkData($data);

        $stand = new Stand(
            array_merge($data, [
                'briefing_id' => $briefing->id,
                'configuration_id' => $data['configuration']['id'],
                'column' => $data['column']['id'],
                'genre_id' => $data['genre']['id']
            ])
        );

        $stand->save();
        $stand->saveStandItems($data);
        $stand->saveFiles($data);
    }

    public function saveStandItems(array $data) {
        $this->items()->delete();

        $closedItemType = StandItemType::where('description', '=', 'Área fechada')->first();
        $openedItemType = StandItemType::where('description', '=', 'Área aberta')->first();

        $closedItems = $data['closed_items'];
        $openedItems = $data['opened_items'];

        foreach($closedItems as $closedItem) {
            $this->items()->save(new StandItem([
                'title' => $closedItem['title'],
                'description' => $closedItem['description'],
                'quantity' => $closedItem['quantity'],
                'stand_id' => $this->id,
                'stand_item_type_id' => $closedItemType->id
            ]));
        }

        foreach($openedItems as $openedItem) {
            $this->items()->save(new StandItem([
                'title' => $openedItem['title'],
                'description' => $openedItem['description'],
                'quantity' => $openedItem['quantity'],
                'stand_id' => $this->id,
                'stand_item_type_id' => $openedItemType->id
            ]));
        }
    }

    public function saveFiles($data) {
        $path = env('FILES_FOLDER') . '/stands/' . $this->id;
        $files = Stand::fileArrayFields();

        foreach($files as $file => $field) {
            if(!isset($data[$file])) {
                throw new \Exception('O arquivo ' . $field . ' não foi informado.');
            }
        }

        mkdir($path);

        foreach($files as $file => $field) {
            rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
        }
    }

    public function editFiles(Stand $oldStand, $data) {
        $updatedFiles = [];
        $path = env('FILES_FOLDER') . '/stands/' . $this->id;
        $files = Stand::fileArrayFields();

        foreach($files as $file => $field) {    
            if($oldStand->{$file} != $data[$file]) {
                $updatedFiles[] = $file;
            }   
        }

        try {
            foreach($updatedFiles as $file) {
                unlink($path . '/' . $oldStand->{$file});
                rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
            }
        } catch(\Exception $e) {}
    }

    public static function fileArrayFields() {
        return [
            'plan' => 'Planta do Evento/Local', 
            'regulation' => 'Regulamento',
        ];
    }

    public static function remove($id) {
        $stand = Stand::find($id);
        $oldStand = clone $stand;
        $stand->items()->delete();
        $stand->delete();

        try {
            $path = env('FILES_FOLDER') . '/stands/' . $oldStand->id;
            unlink($path . '/' . $oldStand->plan);
            unlink($path . '/' . $oldStand->regulation);
            rmdir($path);
        } catch(\Exception $e) {}
    }

    public static function list() {
        return Stand::orderBy('id', 'desc')->get();
    }

    public static function get(int $id) {
        $stand = Stand::find($id);
        $stand->briefing;
        $stand->configuration;
        $stand->genre;
        $stand->items;
        return $stand;
    }

    /*
    public static function filter($query) {
        return Stand::where('description', 'like', $query . '%')
            ->get();
    }
    */

    public static function checkData(array $data) {
        if(!isset($data['configuration']['id'])) {
            throw new \Exception('Configuração do stand não informado!');
        }
        if(!isset($data['genre']['id'])) {
            throw new \Exception('Gênero do stand não informado!');
        }
    }

    public function setClosedAreaPercentAttribute($value) {
        $this->attributes['closed_area_percent'] = (int) $value;
    }

    public function items() {
        return $this->hasMany('App\StandItem', 'stand_id');
    }

    public function briefing() {
        return $this->belongsTo('App\Briefing', 'briefing_id');
    }

    public function configuration() {
        return $this->belongsTo('App\StandConfiguration', 'configuration_id');
    }

    public function genre() {
        return $this->belongsTo('App\StandGenre', 'genre_id');
    }

    public function setAreaAttribute($value) {
        $this->attributes['area'] = (float) str_replace(',', '.', $value);
    }

    public function setBudgetAttribute($value) {
        $this->attributes['budget'] = (float) str_replace(',', '.', $value);
    }
}
