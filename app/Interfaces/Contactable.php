<?php 

namespace App\Interfaces;

interface Contactable {
    public function contacts();
    public function logContactChanges(array $data);
}