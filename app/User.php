<?php

namespace App;

use DateTime;
use Hash;
use Request;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public $timestamps = false;

    protected $table = 'user';

    protected $fillable = [
        'email', 'password', 'employee_id', 'lastAccess'
    ];

    protected $hidden = [
        'password'
    ];

    public static function auth(string $email, string $password) {
        $foundUser = User::where('email', '=', $email)->first();
        
        if(is_null($foundUser) || !Hash::check($password, $foundUser->password)) {
            return null;
        }

        $foundUser->functionalities;
        $foundUser->employee;
        $foundUser->employee->department;
        $foundUser->employee->position;

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

    public static function logged() {
        $userId = (int) Request::header('User');
        return User::find($userId);
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }

    public function functionalities() {
        return $this->belongsToMany('App\Functionality', 'user_functionality', 'user_id', 'functionality_id');
    }
}
