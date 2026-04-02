<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transaction';

    protected $fillable = [
        'job_id', 'transaction_type', 'description', 'observation', 'status',
        'creation_date', 'receipt_date', 'due_date', 'realized_date', 'billing_date',
        'category_id', 'bank_account_id', 'payment_method', 'num_installments',
        'total_value', 'period', 'pix_key', 'bank', 'agency', 'checking_account',
        'ticket_file_directory'
    ];

    protected $casts = [
        'creation_date' => 'datetime',
        'receipt_date' => 'datetime',
        'due_date' => 'datetime',
        'realized_date' => 'datetime',
        'billing_date' => 'datetime',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function installments()
    {
        return $this->hasMany(Installment::class)->orderBy('order');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag', 'transaction_id', 'tag_id');
    }
}
