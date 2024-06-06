<?php

namespace App\Validators;
use Exception;

class Validator {
    private $name;
    private $value;
    private $fireException;
    private $errors = [];

    public function __construct(string $name, $value, $fireException) {
        $this->name = $name;
        $this->value = $value;
        $this->fireException = $fireException;
        return $this;
    }

    public static function field($name, $value, $fireException = true): Validator {
        return new Validator($name, $value, $fireException);
    }

    public function minLength(int $min): Validator {
        if(strlen($this->value) < $min) {
            if($this->fireException) {
                $this->addError('minLength');
                throw new Exception ('O número mínimo de caracteres para o campo ' . $this->name . ' é ' . $min . '. Valor encontrado: ' . strlen($this->value));
            }

            $this->addError('minLength');
        }

        $this->removeError('minLength');
        return $this;
    }

    public function maxLength(int $max): Validator {
        if(strlen($this->value) > $max) {
            if($this->fireException) {
                $this->addError('maxLength');
                throw new Exception ('O número máximo de caracteres para o campo ' . $this->name . ' é ' . $max . '. Valor encontrado: ' . strlen($this->value));
            }
            
            $this->addError('maxLength');
        }

        $this->removeError('maxLength');
        return $this;
    }

    public function required(): Validator {
        if($this->value === '') {
            if($this->fireException) {
                $this->addError('required');
                throw new Exception ('O campo' . $this->name . ' é obrigatório.');
            }
            
            $this->addError('required');
        }

        $this->removeError('required');
        return $this;
    }
    
    private function addError($key) {
        $this->errors[$key] = 'Error';
    }

    private function removeError($key) {
        if(isset($this->errors[$key])) {
            unset($this->errors[$key]);
        }
    }
}