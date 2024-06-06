<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\Interfaces\HasBankAccount;

class BankAccount extends Model
{
    public $timestamps = false;

    protected $table = 'bank_account';

    protected $fillable = [
        'favored', 'agency', 'account_number', 'bank_account_type_id', 'bank_id'
    ];

    public static function manage(array $accountsDataArray, HasBankAccount $hasBankAccount) {
        $oldAccounts = $hasBankAccount->accounts;
        $accountIds = [];

        foreach($accountsDataArray as $account) {
            //Exists, update
            if(isset($account['id'])) {
                $accountIds[] = $account['id'];
                BankAccount::edit($account);
            } 
            //Create because not found
            else {
                BankAccount::insert($account, $hasBankAccount);
            }
        }

        BankAccount::deleteOldIds($oldAccounts, $accountIds, $hasBankAccount);
    }

    public static function deleteOldIds($oldAccounts, array $accountIds, HasBankAccount $hasBankAccount) {
        foreach($oldAccounts as $account) {
            if(!in_array($account->id, $accountIds)) {
                $hasBankAccount->accounts()->detach($account);
                $account->delete();
            }
        }
    }

    public static function insert(array $data, HasBankAccount $hasBankAccount) {
        $account = new BankAccount($data);
        $account->bank_account_type_id = isset($data['bank_account_type']) ? $data['bank_account_type']['id'] : null;
        $account->bank_id = isset($data['bank']) ? $data['bank']['id'] : null;
        $hasBankAccount->accounts()->save($account);
    }

    public static function edit(array $data) {
        $account = BankAccount::find($data['id']);
        $account->bank_account_type_id = isset($data['bank_account_type']) ? $data['bank_account_type']['id'] : null;
        $account->bank_id = isset($data['bank']) ? $data['bank']['id'] : null;
        $account->update($data);
    }

    public function bank_account_type() {
        return $this->belongsTo('App\BankAccountType', 'bank_account_type_id');
    }

    public function bank() {
        return $this->belongsTo('App\Bank', 'bank_id');
    }
}
