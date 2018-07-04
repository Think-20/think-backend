<?php

namespace App;

class FileHelper {
    public static function checkIfExists($path) {
        if(!is_file($path . '/' . $file)) {
            throw new \Exception('O arquivo solicitado não existe.');
        }
    }
}