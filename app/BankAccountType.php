<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BankAccountType extends Model
{
    protected $table = 'bank_account_type';

    protected $fillable = [
        'description'
    ];
}
