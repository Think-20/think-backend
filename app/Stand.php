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
        'genre_id', 'reference', 'closed_area_percent', 'note'
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
        $stand->editFiles($oldStand, $data);
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
        $stand->saveFiles($data);
    }

    public function saveFiles($data) {
        $path = resource_path('assets/files/stands/') . $this->id;
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
        $path = resource_path('assets/files/stands/') . $this->id;
        $files = Stand::fileArrayFields();

        foreach($files as $file => $field) {    
            if($oldStand->{$file} != $data[$file]) {
                $updatedFiles[] = $file;
            }   
        }

        foreach($updatedFiles as $file) {
            unlink($path . '/' . $oldStand->{$file});
            rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
        }
    }

    public static function fileArrayFields() {
        return [
            'plan' => 'Planta do Evento/Local', 
            'regulation' => 'Regulamento',
        ];
    }

    public static function remove($id) {
        $stand = Stand::find($id);
        $stand->delete();
    }

    public static function list() {
        return Stand::orderBy('id', 'desc')->get();
    }

    public static function get(int $id) {
        $stand = Stand::find($id);
        $stand->briefing;
        $stand->configuration;
        $stand->genre;
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

    public function briefing() {
        return $this->belongsTo('App\Briefing', 'briefing_id');
    }

    public function configuration() {
        return $this->belongsTo('App\StandConfiguration', 'configuration_id');
    }

    public function genre() {
        return $this->belongsTo('App\StandGenre', 'genre_id');
    }
}
