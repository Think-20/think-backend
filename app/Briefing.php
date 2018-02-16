<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;

class Briefing extends Model
{
    protected $table = 'briefing';

    protected $fillable = [
        'code',
        'job_id', 'exhibitor_id', 'event', 'deadline', 'job_type_id', 'agency_id', 'attendance_id',
        'creation_id', 'area', 'budget', 'rate', 'competition_id', 'latest_mounts_file', 
        'colors_file', 'guide_file', 'presentation_id', 'special_presentation_id', 'approval_expectation_rate'
    ];

    protected $dates = [
        'created_at', 'updated_at'
    ];

    public static function loadForm() {
        return [
            'jobs' => Job::all(),
            'job_types' => JobType::all(),
            'attendances' => Employee::canInsertClients(),
            'creations' => Employee::whereHas('department', function($query) {
                $query->where('description', '=', 'Criação');
            })->get(),
            'competitions' => BriefingCompetition::all(),
            'presentations' => BriefingPresentation::all(),
            'special_presentations' => BriefingSpecialPresentation::all()
        ];
    }

    public static function edit(array $data) {
        Briefing::checkData($data, true);

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
        $briefing->editChild($data);
        return $briefing;
    }

    public static function insert(array $data) {
        Briefing::checkData($data);
        $code = Briefing::generateCode();

        $briefing = new Briefing(
            array_merge($data, [
                'code' => $code,
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
        $briefing->saveChild($data);

        return $briefing;
    }

    public static function downloadFile($id, $type, $file) {
        $content = '';
        $mime = '';
        $briefing = Briefing::find($id);
        $user = User::logged();
        $department = $user->employee->department->description;

        if(!in_array($department, ['Administradores', 'Criação', 'Diretoria', 'Atendimento', 'Produção'])) {
            throw new \Exception('Somente o departamento de criação, atendimento e produção podem visualizar.');
        }

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
        $oldBriefing = clone $briefing;
        $briefing->deleteChild();
        $briefing->delete();
        
        try {
            $path = resource_path('assets/files/briefings/') . $oldBriefing->id;
            unlink($path . '/' . $oldBriefing->latest_mounts_file);
            unlink($path . '/' . $oldBriefing->colors_file);
            unlink($path . '/' . $oldBriefing->guide_file);
            rmdir($path);
        } catch(\Exception $e) {}
    }

    public static function list() {
        $briefings = Briefing::orderBy('id', 'desc')->paginate(50);

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
        ->paginate(50);

        foreach($briefings as $briefing) {
            $briefing->job_type;
            $briefing->attendance;
            $briefing->exhibitor;
        }

        return $briefings;
    }

    
    #My Briefing#
    public static function editMyBriefing(array $data) {
        Briefing::checkData($data, true);

        $id = $data['id'];
        $briefing = Briefing::find($id);

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para editar esse briefing.');
        }

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
        $briefing->editChild($data);
        return $briefing;
    }

    public static function downloadFileMyBriefing($id, $type, $file) {
        $content = '';
        $mime = '';
        $briefing = Briefing::find($id);
        $user = User::logged();

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para fazer downloads desse briefing.');
        }

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

    public static function removeMyBriefing($id) {
        $briefing = Briefing::find($id);

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para remover esse briefing.');
        }

        $oldBriefing = clone $briefing;
        $briefing->deleteChild();
        $briefing->delete();
        
        try {
            $path = resource_path('assets/files/briefings/') . $oldBriefing->id;
            unlink($path . '/' . $oldBriefing->latest_mounts_file);
            unlink($path . '/' . $oldBriefing->colors_file);
            unlink($path . '/' . $oldBriefing->guide_file);
            rmdir($path);
        } catch(\Exception $e) {}
    }

    public static function listMyBriefing() {
        $briefings = Briefing::orderBy('id', 'desc')
         ->where('attendance_id', '=', User::logged()->employee->id)
         ->paginate(50);

        foreach($briefings as $briefing) {
            $briefing->job_type;
            $briefing->attendance;
            $briefing->exhibitor;
        }

        return $briefings;
    }

    public static function getMyBriefing(int $id) {
        $briefing = Briefing::find($id);

        if($briefing->attendance_id != User::logged()->employee->id) {
            throw new \Exception('Você não tem permissão para visualizar esse briefing.');
        }

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

    public static function filterMyBriefing($query) {
       $briefings = Briefing::where('event', 'like', $query . '%')
        ->where('attendance_id', '=', User::logged()->employee->id)
        ->paginate(50);

        foreach($briefings as $briefing) {
            $briefing->job_type;
            $briefing->attendance;
            $briefing->exhibitor;
        }

        return $briefings;
    }

    public static function generateCode() {
        $result = DB::table('briefing')
        ->select(DB::raw('(MAX(code) + 1) as code'))
        ->where(DB::raw('YEAR(CURRENT_DATE())'), '=', DB::raw('YEAR(created_at)'))
        ->first();

        if($result->code == null) {
            return 1;
        }

        return $result->code; 
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

        try {
            foreach($updatedFiles as $file) {
                unlink($path . '/' . $oldBriefing->{$file});
                rename(sys_get_temp_dir() . '/' .  $data[$file], $path . '/' . $data[$file]);
            }
        } catch(\Exception $e) {}
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
 
    public function saveFilesChild($data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->saveFiles($data['stand']);
        }
    }
 
    public function editChild($data) {
        if($this->job_type->description === 'Stand') {
            Stand::edit($data['stand']);
        }
    }
 
    public function editFilesChild($oldChild, $data) {
        if($this->job_type->description === 'Stand') {
            $this->stand->editFiles($oldChild, $data['stand']);
        }
    }
 
    public function deleteChild() {
        if($this->job_type->description === 'Stand') {
            Stand::remove($this->stand->id);
        }
    }
 
    public static function getBriefingChild(Briefing $briefing) {
        if($briefing->job_type->description === 'Stand') {
            $stand = $briefing->stand;
            $stand->briefing;
            $stand->configuration;
            $stand->genre;
            $stand->column = $stand->column == 0 ? ['id' => 0] : ['id' => 1];
            $items = $stand->items;
            foreach($items as $item) {
                $item->type;
            }

            return $stand;
        }
    }

    public static function checkData(array $data, $editMode = false) {
        if(!isset($data['job']['id'])) {
            throw new \Exception('Job do briefing não informado!');
        }

        if(!isset($data['exhibitor']['id'])) {
            throw new \Exception('Expositor do briefing não cadastrado!');
        }

        if(!isset($data['job_type']['id']) && !$editMode) {
            throw new \Exception('Tipo de job do briefing não informado!');
        }

        if(!isset($data['agency']['id'])) {
            throw new \Exception('Agência do briefing não cadastrada!');
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
