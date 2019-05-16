<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DateTime;
use DB;

class Event extends Model
{
    protected $table = 'event';

    protected static $files = [
        'plan', 'manual', 'regulation'
    ];

    protected $fillable = [
        'name', 'place_id', 'edition', 'note', 'ini_date', 'fin_date', 'ini_hour', 'fin_hour', 
        'employee_id', 'phone', 'organizer', 'email', 'site', 'plan', 'manual', 'regulation',
        'ini_date_mounting', 'fin_date_mounting', 'ini_hour_mounting', 'fin_hour_mounting',
        'ini_date_unmounting', 'fin_date_unmounting', 'ini_hour_unmounting', 'fin_hour_unmounting',
    ];

    public static function filter(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $search = isset($data['search']) ? $data['search'] : null;
        $query = Event::with('place', 'employee');

        if( ! is_null($search) ) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('name', 'asc');

        if($paginate) {
            $events = $query->paginate(20);
        } else {
            $events = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $events,
            'updatedInfo' => Event::updatedInfo()
        ];
    }

    public static function list(array $data) {
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $query = Event::with('place', 'employee')->orderBy('name', 'asc');

        if($paginate) {
            $events = $query->paginate(20);
        } else {
            $events = $query->get();
        }       

        return [
            'pagination' => $events,
            'updatedInfo' => Event::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $event = Event::find($id);
            $oldEvent = Event::find($id);
            $event->fill($data);
            $event->editFiles($oldEvent);
            $event->checkIfDuplicate();
            $event->place_id = isset($data['place']['id']) ? $data['place']['id'] : null;
            $event->update();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $event = new Event($data);
            $event->checkIfDuplicate();
            $event->place_id = isset($data['place']['id']) ? $data['place']['id'] : null;
            $event->employee_id = User::logged()->employee_id;
            $event->saveFiles();
            $event->save();
            DB::commit();
            return $event;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $event = Event::find($id);
            $event->delete();
            $event->deleteFiles();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $event = Event::with('place', 'employee', 'jobs')
        ->where('event.id', '=', $id)
        ->first();
                
        if(is_null($event)) {
            return null;
        }

        return $event;
    }

    public static function updatedInfo() {
        $lastData = Event::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => ''
        ];
    }

    public function checkIfDuplicate() {
        $query = Event::where('name', '=', $this->name)
        ->where('edition', '=', $this->edition);
        if($this->id != null) {
            $query->where('id', '<>', $this->id);
        }
        $found = $query->count() > 0 ? true : false;

        if(!$found) return true;

        throw new \Exception('Já existe um evento com essa descrição e edição cadastrado.');
    }

    public static function downloadFile($id, $type, $file) {
        $event = Event::find($id);
        $user = User::logged();

        if(is_null($event)) {
            throw new \Exception('O evento solicitado não existe.');
        }

        $path = env('FILES_FOLDER') . '/events/' . $event->id . '/' . $file;
        FileHelper::checkIfExists($path);
        return $path;
    }
    
    public function saveFiles() {
        $path = env('FILES_FOLDER') . '/events/' . $this->id;

        if(!is_dir($path)) {
            mkdir($path);
        }

        foreach(Event::$files as $fileType) {
            $hash = sha1($this->{$fileType} . time());
            rename(sys_get_temp_dir() . '/' .  $this->{$fileType}, $path . '/' . $hash);
            $this->{$fileType} = $hash;
            $this->save();
        }
    }

    public function editFiles(Event $oldEvent) {
        $path = env('FILES_FOLDER') . '/events/' . $oldEvent->id;
        $originalArray = [];
        $changesArray = [];

        $originalArray = array_filter($oldEvent->toArray(), function($var) {
            return in_array($var, Event::$files);
        }, ARRAY_FILTER_USE_KEY);

        $changesArray = array_filter($this->toArray(), function($var) {
            return in_array($var, Event::$files);
        }, ARRAY_FILTER_USE_KEY);

        $diffArray = array_diff($changesArray, $originalArray);   

        if(!is_dir($path)) {
            mkdir($path);
        }     

        foreach($diffArray as $key => $file) {
            $hash = sha1($file . time());
            rename(sys_get_temp_dir() . '/' .  $file, $path . '/' . $hash);
            $this->{$key} = $hash;
            $this->save();

            try {
                unlink($path . '/' . $originalArray[$key]);
            } catch(\Exception $e) {}
        }
    }

    public function deleteFiles() {
        $path = env('FILES_FOLDER') . '/events/' . $this->id;
        foreach(Event::$files as $file) {
            try {
                unlink($path . '/' . $this->{$file});
            } catch(\Exception $e) {}
        } 
    }

    public function setNameAttribute($value) {
        $this->attributes['name'] = ucwords(strtolower($value));
    }

    public function setEditionAttribute($value) {
        $this->attributes['edition'] = ucwords(strtolower($value));
    }

    public function setOrganizerAttribute($value) {
        $this->attributes['organizer'] = ucwords(strtolower($value));
    }

    public function setIniDateAttribute($value) {
        $this->attributes['ini_date'] = substr($value, 0, 10);
    }

    public function setFinDateAttribute($value) {
        $this->attributes['fin_date'] = substr($value, 0, 10);
    }

    public function setIniDateMountingAttribute($value) {
        $this->attributes['ini_date_mounting'] = substr($value, 0, 10);
    }

    public function setFinDateMountingAttribute($value) {
        $this->attributes['fin_date_mounting'] = substr($value, 0, 10);
    }

    public function setIniDateUnmountingAttribute($value) {
        $this->attributes['ini_date_unmounting'] = substr($value, 0, 10);
    }

    public function setFinDateUnmountingAttribute($value) {
        $this->attributes['fin_date_unmounting'] = substr($value, 0, 10);
    }

    public function getPhoneAttribute($value) {
        $phone = null;

        if(strlen($value) == 10) {
            $phone = mask($value, '(##) ####-####');
        } else if(strlen($value) == 11) {
            $phone = mask($value, '(##) ####-#####');
        }

        return $phone;
    }

    public function setPhoneAttribute($value) {
        $this->attributes['phone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }

    public function jobs() {
        return $this->hasMany('App\Job', 'event_id')
        ->with('client', 'agency', 'creation', 'attendance', 'status');
    }

    public function place() {
        return $this->belongsTo('App\Place', 'place_id');
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }
}
