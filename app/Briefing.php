<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Briefing extends Model
{
    protected $table = 'briefing';

    protected $fillable = [
        'job_id', 'exhibitor_id', 'event', 'deadline', 'job_type_id', 'agency_id', 'attendance_id',
        'creation_id', 'area', 'budget', 'rate', 'competition_id', 'latest_mounts_file', 
        'colors_file', 'guide_file', 'presentation_id', 'special_presentation_id', 'approval_expectation_rate'
    ];

    public static function edit(array $data) {
        $id = $data['id'];
        $briefing = Briefing::find($id);
        $oldBriefing = clone $briefing;
        $briefing->update(
            array_merge($data, [
                'job_id' => $data['job']['id'],
                'exhibitor_id' => $data['exhibitor']['id'],
                'agency_id' => $data['agency']['id'],
                'attendance_id' => $data['attendance']['id'],
                'creation_id' => $data['creation']['id'],
                'competition_id' => $data['competition']['id'],
                'presentation_id' => $data['presentation']['id'],
                'special_presentation_id' => $data['special_presentation']['id']
            ])
        );
        $briefing->editFiles($oldBriefing, $data);
        $briefing->editChild($data);
        return $briefing;
    }

    public static function insert(array $data) {
        Briefing::checkData($data);

        $briefing = new Briefing(
            array_merge($data, [
                'job_id' => $data['job']['id'],
                'exhibitor_id' => $data['exhibitor']['id'],
                'job_type_id' => $data['job_type']['id'],
                'agency_id' => $data['agency']['id'],
                'attendance_id' => $data['attendance']['id'],
                'creation_id' => $data['creation']['id'],
                'competition_id' => $data['competition']['id'],
                'presentation_id' => $data['presentation']['id'],
                'special_presentation_id' => $data['special_presentation']['id']
            ])
        );

        $briefing->save();
        $briefing->saveFiles($data);
        $briefing->saveChild($data);
    }

    public function saveFiles($data) {
        $path = resource_path('assets/files/briefings/') . $this->id;
        $files = Briefing::fileArrayFields();

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

    public function editFiles(Briefing $oldBriefing, $data) {
        $updatedFiles = [];
        $path = resource_path('assets/files/briefings/') . $this->id;
        $files = Briefing::fileArrayFields();

        foreach($files as $file => $field) {    
            if($oldBriefing->{$file} != $data[$file]) {
                $updatedFiles[] = $file;
            }   
        }

        foreach($updatedFiles as $file) {
            unlink($path . '/' . $oldBriefing->{$file});
            rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
        }
    }

    public static function fileArrayFields() {
        return [
            'latest_mounts_file' => 'Referências', 
            'colors_file' => 'Cores e Materiais Sugeridos',
            'guide_file' => 'Guide/Key Visual/Logos/Imagens/Produtos',
        ];
    }
 
    public function saveChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::insert($this, $data['stand']);
        }
    }
 
    public function editChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::edit($data['stand']);
        }
    }
 
    public static function getBriefingChild(Briefing $briefing) {
        if($briefing->job_type->description === 'Stand') {
            $stand = $briefing->stand;
            $stand->briefing;
            $stand->configuration;
            $stand->genre;
            $stand->column = $stand->column == 0 ? ['id' => 0] : ['id' => 1];
        }
    }

    public static function downloadFile($id, $type, $file) {
        $content = '';
        $mime = '';
        $briefing = Briefing::find($id);

        if(is_null($briefing)) {
            throw new \Exception('O briefing solicitado não existe.');
        }

        switch($type) {
            case 'briefing': {
                $path = resource_path('assets/files/briefings/') . $briefing->id;

                if(!array_key_exists($file, Briefing::fileArrayFields())) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }

                $path .= '/' . $briefing->{$file};
                
                $content = file_get_contents($path);
                $mime = mime_content_type($path);
                break;
            }
            case 'stand': {
                $path = resource_path('assets/files/stands/') . $briefing->stand->id;

                if(!array_key_exists($file, Stand::fileArrayFields())) {
                    throw new \Exception('O arquivo solicitado não existe.');
                }
                
                $path .= '/' . $briefing->stand->{$file};
                
                $fp = fopen($path, 'r');
                $content = fread($fp, filesize($path));
                $content = addslashes($content);
                fclose($fp);
                $mime = mime_content_type($path);
                break;
            } 
            default: {
                throw new \Exception('O tipo de arquivo solicitado não existe. ' . $type);
            }
        }

        return $path;
    }

    public static function remove($id) {
        $briefing = Briefing::find($id);
        if($briefing->stand != null) $briefing->stand->delete();
        $briefing->delete();
    }

    public static function list() {
        $briefings = Briefing::orderBy('id', 'desc')->get();

        foreach($briefings as $briefing) {
            $briefing->job_type;
            $briefing->attendance;
            $briefing->exhibitor;
        }

        return $briefings;
    }

    public static function get(int $id) {
        $briefing = Briefing::find($id);
        $briefing->job;
        $briefing->job_type;
        $briefing->exhibitor;
        $briefing->agency;
        $briefing->attendance;
        $briefing->creation;
        $briefing->competition;
        $briefing->presentation;
        $briefing->special_presentation;

        Briefing::getBriefingChild($briefing);

        return $briefing;
    }

    public static function filter($query) {
       $briefings = Briefing::where('event', 'like', $query . '%')
        ->get();

        foreach($briefings as $briefing) {
            $briefing->job_type;
            $briefing->attendance;
            $briefing->exhibitor;
        }

        return $briefings;
    }

    public static function checkData(array $data) {
        if(!isset($data['job']['id'])) {
            throw new \Exception('Job do briefing não informado!');
        }

        if(!isset($data['exhibitor']['id'])) {
            throw new \Exception('Expositor do briefing não informado!');
        }

        if(!isset($data['job_type']['id'])) {
            throw new \Exception('Tipo de job do briefing não informado!');
        }

        if(!isset($data['agency']['id'])) {
            throw new \Exception('Agência do briefing não informado!');
        }

        if(!isset($data['attendance']['id'])) {
            throw new \Exception('Atendimento do briefing não informado!');
        }
        if(!isset($data['creation']['id'])) {
            throw new \Exception('Criação do briefing não informada!');
        }

        if(!isset($data['competition']['id'])) {
            throw new \Exception('Concorrência do briefing não informada!');
        }

        if(!isset($data['presentation']['id'])) {
            throw new \Exception('Apresentação do briefing não informada!');
        }

        if(!isset($data['special_presentation']['id'])) {
            throw new \Exception('Apresentação especial do briefing não informada!');
        }
    }

    public function setAreaAttribute($value) {
        $this->attributes['area'] = (float) str_replace(',', '.', $value);
    }

    public function setBudgetAttribute($value) {
        $this->attributes['budget'] = (float) str_replace(',', '.', $value);
    }

    public function stand() {
        return $this->hasOne('App\Stand', 'briefing_id');
    }

    public function job() {
        return $this->belongsTo('App\Job', 'job_id');
    }

    public function exhibitor() {
        return $this->belongsTo('App\Client', 'exhibitor_id');
    }

    public function job_type() {
        return $this->belongsTo('App\JobType', 'job_type_id');
    }

    public function agency() {
        return $this->belongsTo('App\Client', 'agency_id');
    }

    public function attendance() {
        return $this->belongsTo('App\Employee', 'attendance_id');
    }

    public function creation() {
        return $this->belongsTo('App\Employee', 'creation_id');
    }

    public function competition() {
        return $this->belongsTo('App\BriefingCompetition', 'competition_id');
    }

    public function presentation() {
        return $this->belongsTo('App\BriefingPresentation', 'presentation_id');
    }

    public function special_presentation() {
        return $this->belongsTo('App\BriefingSpecialPresentation', 'special_presentation_id');
    }
}
