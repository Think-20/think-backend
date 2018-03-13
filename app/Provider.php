<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use DB;
use App\Interfaces\Contactable;
use App\Interfaces\HasBankAccount;

class Provider extends Model implements Contactable, HasBankAccount
{
    protected $table = 'provider';

    protected $fillable = [
        'name', 'fantasy_name', 'cnpj', 'ie', 'cpf', 'mainphone', 'secundaryphone', 'site', 'note',
        'rate', 'street', 'number', 'neighborhood', 'complement', 'city_id', 'cep', 'person_type_id',
        'employee_id'
    ];

    public static function list() {
        $providers = Provider::select()
        ->orderBy('fantasy_name', 'asc')
        ->get();

        foreach($providers as $provider) {
            $provider->employee;
            $provider->person_type;
        }

        return $providers;
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        Provider::checkData($data);
        
        try {
            $id = $data['id'];
            $provider = Provider::find($id);
            $provider->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $provider->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $provider->person_type_id = isset($data['person_type']['id']) ? $data['person_type']['id'] : null;

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manage($contacts, $provider);

            $accounts = isset($data['accounts']) ? $data['accounts'] : [];
            BankAccount::manage($accounts, $provider);

            $provider->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        Provider::checkData($data);
        
        try {
            $provider = new Provider($data);
            $provider->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $provider->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $provider->person_type_id = isset($data['person_type']['id']) ? $data['person_type']['id'] : null;
            $provider->save();

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manage($contacts, $provider);

            $accounts = isset($data['accounts']) ? $data['accounts'] : [];
            BankAccount::manage($accounts, $provider);

            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $provider = Provider::find($id);
            $provider->contacts()->detach();
            $provider->contacts()->delete();
            $provider->accounts()->detach();
            $provider->accounts()->delete();
            $provider->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $provider = Provider::find($id);
        
        if(is_null($provider)) {
            return null;
        }

        $provider->city;
        $provider->city->state;
        $provider->employee;
        $provider->person_type;
        $provider->contacts;
        foreach($provider->accounts as $account) {
            $account->bank_account_type;
            $account->bank;
        }
        return $provider;
    }

    public static function filter($query) {
        $providers = Provider::where('name', 'like', $query . '%')
            ->orWhere('fantasy_name', 'like', $query . '%')
            ->orWhere('cnpj', 'like', $query . '%')
            ->orWhere('cpf', 'like', $query . '%')
            ->orderBy('fantasy_name', 'asc')
            ->get();

        foreach($providers as $provider) {
            $provider->employee;
            $provider->person_type;
        }

        return $providers;
    }
    
    public static function checkData(array $data, $editMode = false) {
        if(!isset($data['state']['id'])) {
            throw new \Exception('Estado não informado!');
        }

        if(!isset($data['city']['id'])) {
            throw new \Exception('Cidade não informada!');
        }
    }

    public function getCnpjAttribute($value) {
        return mask(str_pad($value, 14, '0', STR_PAD_LEFT), '##.###.###/####-##');
    }

    public function getCpfAttribute($value) {
        return mask(str_pad($value, 11, '0', STR_PAD_LEFT), '###.###.###-##');
    }

    public function getIeAttribute($value) {
        if($value == null) {
            return null;
        }

        return mask(str_pad($value, 12, '0', STR_PAD_LEFT), '###.###.###.###');
    }

    public function getMainphoneAttribute($value) {
        $phone = null;

        if(strlen($value) == 10) {
            $phone = mask($value, '(##) ####-####');
        } else if(strlen($value) == 11) {
            $phone = mask($value, '(##) ####-#####');
        }

        return $phone;
    }

    public function getSecundaryphoneAttribute($value) {
        $phone = null;

        if(strlen($value) == 10) {
            $phone = mask($value, '(##) ####-####');
        } else if(strlen($value) == 11) {
            $phone = mask($value, '(##) ####-#####');
        }

        return $phone;
    }

    public function getCepAttribute($value) {
        return mask(str_pad($value, 8, '0', STR_PAD_LEFT), '#####-###');
    }
    
    public function setCnpjAttribute($value) {
        $this->attributes['cnpj'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setCpfAttribute($value) {
        $this->attributes['cpf'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setIeAttribute($value) {
        $this->attributes['ie'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }

    public function setMainphoneAttribute($value) {
        $this->attributes['mainphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }

    public function setSecundaryphoneAttribute($value) {
        $this->attributes['secundaryphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }

    public function setCepAttribute($value) {
        $this->attributes['cep'] = preg_replace('/[^0-9]+/', '', $value);
    }

    public function city() {
        return $this->belongsTo('App\City', 'city_id');
    }

    public function person_type() {
        return $this->belongsTo('App\PersonType', 'person_type_id');
    }
    
    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }  

    public function contacts() {
        return $this->belongsToMany('App\Contact', 'provider_contact', 'provider_id', 'contact_id');
    }

    public function accounts() {
        return $this->belongsToMany('App\BankAccount', 'provider_account', 'provider_id', 'account_id');
    }
}
