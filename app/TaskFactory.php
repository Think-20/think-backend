<?php

namespace App;

abstract class TaskFactory {
    public static function build($type): TaskInterface {
        if($type == 'Projeto' || $type == 'Modificação' || $type == 'Opção') {
            return new TaskCreation;
        } else if($type == 'Orçamento') {
            return new TaskBudget;
        } else if($type == 'Detalhamento') {
            return new TaskDetailing;
        }
    }
}