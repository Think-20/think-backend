<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;

interface TaskInterface {
    public function getResponsibleList(): Collection;
    public function getMaxCapability();
}