<?php

namespace App;

use Illuminate\Database\Eloquent\Collection;
use DateTime;

interface TaskInterface {
    public function getResponsibleList(): Collection;
    public function getMaxCapability();
    public function reachedLimit(DateTime $date): bool;
    public function generateNewSuggestDate(): DateTime;
}