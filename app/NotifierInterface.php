<?php

namespace App;

interface NotifierInterface {
    public function getOficialId(): int;
    public function getName(): string;
    public function getLogo(): string;
 }