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

    public static function createMulti(array $data): array
    {
        $array = [];
        $message = '';

        foreach($data as $notification) {
            //Recupera a última mensagem, caso a atual esteja vazia
            $message = $data['message'] == '' ? $message : $data['message'];
            $array[] = new NotificationSpecial($data['user_id'], $message);
        }  

        return $array;
    }
}
