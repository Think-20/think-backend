<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use DateTime;
use DB;
use Exception;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Hash;

class Employee extends Model implements NotifierInterface
{
    use SoftDeletes;

    protected $table = 'employee';

    protected $fillable = [
        'name', 'payment', 'position_id', 'department_id', 'schedule_active'
    ];

    protected $hidden = [
        'payment'
    ];

    protected $dates = [
        'deleted_at', 'created_at', 'updated_at'
    ];

    public function getOficialId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getLogo(): string {
        return '/assets/images/users/' . $this->user;
    }

    public function getImageNameGeneration() {
        return $this->image . '_id_' . $this->id;
    }

    public function moveFile() {
        if($this->image == 'sem-foto.jpg' || $this->image == 'users/sem-foto.jpg') return;

        $browserFiles = [];
        $path = public_path('assets/images/users/');

        if(!is_dir($path)) {
            mkdir($path);
        }

        if(is_file(sys_get_temp_dir() . '/' .  $this->image)) {
            rename(sys_get_temp_dir() . '/' .  $this->image, $path . '/' . $this->getImageNameGeneration());
            $this->image = 'users/' . $this->getImageNameGeneration();
            $this->save();
        }
    }    

    public function removeFile() {
        if($this->image == 'sem-foto.jpg' || $this->image == 'users/sem-foto.jpg') return;

        $path = public_path('assets/images/');
        $file = $path . $this->image;

        if(is_file($file)) {
            unlink($file);
        }
    }    

    public static function list() {
        $deleted = isset($data['deleted']) ? $data['deleted'] : null;

        $query = Employee::with('user', 'position', 'department')
        ->orderBy('name', 'asc');

        if($deleted) {
            $query->withTrashed();
        }

        $employees = $query->paginate(20);

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
    }

    public static function canInsertClients(array $data = []) {
        $deleted = isset($data['deleted']) && $data['deleted'] === 'true' ? true : false;
        $insertClients = Functionality::where('description', '=', 'Cadastrar um cliente')->first();
        $insertMyClients = Functionality::where('description', '=', 'Cadastrar um cliente (atendimento)')->first();

        $employees = Employee::select('employee.id', 'employee.name', 'employee.position_id', 'employee.department_id')
        ->join('user', 'user.employee_id', '=', 'employee.id')
        ->join('user_functionality', 'user_functionality.user_id', '=', 'user.id')
        ->where('user_functionality.functionality_id', '=', $insertClients->id)
        ->orWhere('user_functionality.functionality_id', '=', $insertMyClients->id)
        ->orderBy('name', 'asc')
        ->distinct();

        if($deleted) {
            $employees->withTrashed();
        }

        return $employees->get();
    }

    public static function filter(array $data) {
        $search = isset($data['search']) ? $data['search'] : null;
        $deleted = isset($data['deleted']) ? $data['deleted'] : null;
        $paginate = isset($data['paginate']) ? $data['paginate'] : true;
        $departmentId = isset($data['department']['id']) ? $data['department']['id'] : null;
        $positionId = isset($data['position']['id']) ? $data['position']['id'] : null;
        $query = Employee::with('user', 'position', 'department');

        if( ! is_null($search) ) {
            $query->where('name', 'LIKE', '%' . $search . '%');
        }

        if( ! is_null($departmentId) ) {
            $query->where('department_id', '=', $departmentId);
        }

        if( ! is_null($positionId) ) {
            $query->where('position_id', '=', $positionId);
        }

        $query->orderBy('name', 'asc');

        if($deleted) {
            $query->withTrashed();
        }

        if($paginate) {
            $employees = $query->paginate(20);
        } else {
            $employees = [ 'data' => $query->get() ];
        }

        return [
            'pagination' => $employees,
            'updatedInfo' => Employee::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $image = isset($data['image']) ? $data['image'] : null;
            $employee = Employee::withTrashed()->find($id);
            $employee->makeVisible('payment');
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            
            if($employee->image != $employee->getImageNameGeneration()) {
                $employee->moveFile();
            }
            
            $employee->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function myEdit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $image = isset($data['image']) ? $data['image'] : null;
            $name = isset($data['name']) ? $data['name'] : null;

            $employee = Employee::withTrashed()->find($id);
            $employee->checkUser();            
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            
            if($employee->image != $employee->getImageNameGeneration()) {
                $employee->moveFile();
            }
            
            $employee->update([
                'name' => $name
            ]);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function checkUser() {
        if($this->user->id != User::logged()->id) {
            throw new \Exception('Desculpe, você não pode ler ou editar informações de outro usuário.');
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $email = self::generateEmail($data['name']);
            $user = User::where('email', $email)->first();
            if($user){
                throw new Exception('Já existe um usuário com esse nome.');
            }
            $employee = new Employee($data);
            $employee->department_id = isset($data['department']['id']) ? $data['department']['id'] : null;
            $employee->position_id = isset($data['position']['id']) ? $data['position']['id'] : null;
            $employee->updated_by = User::logged()->id;
            $employee->image = isset($data['image']) ? $data['image'] : 'sem-foto.jpg';
            $employee->save();
            $employee->moveFile();
            DB::commit();
            
            self::createUser($data, $employee->id);
            return $employee;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function createUser($data, $employeeId) {

        // Verifica se o nome existe
        if (!isset($data['name']) || !$employeeId) {
            return null;
        }
        $email = self::generateEmail($data['name']);
        $user = new User();
        $user->email = $email;
        $user->password = Hash::make($email);
        $user->employee_id = $employeeId;
        $user->save();
    }

    public static function generateEmail($name){
        // Divide o nome em palavras
        $words = explode(' ', $name);
    
        // Converte todas as palavras para minúsculas e as une com um ponto
        $email = implode('.', array_map('strtolower', $words));
    
        // Adiciona o domínio do email
        $email .= '@thinkideias.com.br';
    }

    public static function toggleDeleted($id) {
        DB::beginTransaction();
        
        try {
            $employee = Employee::withTrashed()->find($id);

            if($employee->trashed()) {
                $employee->restore();
            } else {
                $employee->delete();
            }
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    /*
    public static function remove($id) {
        DB::beginTransaction();
        throw new \Exception('Essa função está temporariamente desabilitada.');
        
        try {
            $employee = Employee::find($id);
            $employee->user->notifications()->delete();
            $employee->user->scheduleBlocks()->delete();
            $employee->user->notificationRules()->delete();
            $employee->user->functionalities()->detach();
            $employee->user->displays()->detach();
            $employee->user->delete();
            $employee->delete();
            $employee->removeFile();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }
    */

    public static function get(int $id) {
        $employee = Employee::with([
            'user', 'user.functionalities', 'user.displays', 'position', 'department', 'updatedBy'
        ])
        ->where('employee.id', '=', $id)
        ->withTrashed()
        ->first();
                
        if(is_null($employee)) {
            return null;
        }

        $employee->makeVisible('payment');
        return $employee;
    }

    public static function myGet(int $id) {
        $employee = Employee::with('user', 'position', 'department', 'updatedBy')
        ->where('employee.id', '=', $id)
        ->withTrashed()
        ->first();

        $employee->checkUser(); 
                
        if(is_null($employee)) {
            return null;
        }

        $employee->makeVisible('payment');
        return $employee;
    }

    public function setNameAttribute($value) {
        $this->attributes['name'] = ucwords(mb_strtolower($value));
    }

    public static function updatedInfo() {
        $lastData = Employee::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => $lastData->updatedBy->name
        ];
    }

    public function position() {
        return $this->belongsTo('App\Position', 'position_id');
    }

    public function user() {
        return $this->hasOne('App\User', 'employee_id');
    }

    public function updatedBy() {
        return $this->belongsTo('App\Employee', 'updated_by')->withTrashed();
    }

    public function department() {
        return $this->belongsTo('App\Department', 'department_id');
    }
    
    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
