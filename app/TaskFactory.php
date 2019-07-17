<?php

namespace App;

abstract class TaskFactory {
    public static function build($type): TaskInterface {
        if($type == 'Projeto' || $type == 'Modificação' || $type == 'Opção' || $type == 'Outsider'  || $type == 'Continuação') {
            return new TaskCreation;
        } else if($type == 'Orçamento') {
            return new TaskBudget;
        } else if($type == 'Detalhamento') {
            return new TaskDetailing;
        } else if($type == 'Memorial descritivo') {
            return new TaskMemorial;
        } else if($type == 'Projeto externo') {
            return new TaskExternalProject;
        } else if($type == 'Modificação de orçamento') {
            return new TaskBudgetModify;
        } else {
            return new TaskOthers;
        }
    }
}