<?php

namespace App;

class NotificationSpecial 
{
    public $message;
    public $user_id;

    protected function __construct(int $user_id, string $message) 
    {
        $this->message = $message;
        $this->user_id = $user_id;
    }

    public static function createArray(int $user_id, string $message = ''): array 
    {
        return [new NotificationSpecial($user_id, $message)];
    }

    public static function createMulti(...$data): array
    {
        $array = [];
        $message = '';

        foreach($data as $notification) {
            //Recupera a última mensagem, caso a atual esteja vazia
            $message = $notification['message'] == '' ? $message : $notification['message'];
            $array[] = new NotificationSpecial($notification['user_id'], $message);
        }  

        return $array;
    }
}
