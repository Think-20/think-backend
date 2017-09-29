<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use DB;

class Client extends Model
{
    protected $table = 'client';

    protected $fillable = [
        'name', 'fantasy_name', 'cnpj', 'mainphone', 'secundaryphone', 'site', 'rate', 'note',
        'street', 'number', 'neighborhood', 'complement', 'cep', 'city_id', 
        'employee_id', 'client_type_id', 'client_status_id'
    ];

    public static function list() {
        $clients = Client::select()
        ->orderBy('name', 'asc')
        ->get();

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->status;
        }

        return $clients;
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $client = Client::find($id);
            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manageClient($contacts, $client);

            $client->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        
        try {
            $client = new Client($data);
            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;
            $client->save();

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manageClient($contacts, $client);

            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $client = Client::find($id);
            $client->contacts()->detach();
            $client->contacts()->delete();
            $client->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $client = Client::find($id);
        
        if(is_null($client)) {
            return null;
        }

        $client->city;
        $client->city->state;
        $client->employee;
        $client->type;
        $client->status;
        $client->contacts;
        return $client;
    }

    public static function filter($query) {
        $clients = Client::where('name', 'like', $query . '%')
            ->orWhere('fantasy_name', 'like', $query . '%')
            ->orWhere('cnpj', 'like', $query . '%')
            ->get();

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->status;
        }

        return $clients;
    }

    # My clients #

    public static function listMyClient() {
        $clients = Client::where('employee_id', '=', User::logged()->employee->id)
        ->orderBy('name', 'asc')
        ->get();

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->status;
        }

        return $clients;
    }

    public static function editMyClient(array $data) {
        DB::beginTransaction();
        
        try {
            $id = $data['id'];
            $client = Client::find($id);

            if($client->employee_id != User::logged()->employee->id) {
                throw new \Exception('Não é possível editar um cliente que não foi cadastrado por você.');
            }

            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manageClient($contacts, $client);

            $client->update($data);
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function removeMyClient($id) {
        DB::beginTransaction();
        
        try {
            $client = Client::find($id);

            if($client->employee_id != User::logged()->employee->id) {
                throw new \Exception('Não é possível remover um cliente que não foi cadastrado por você.');
            }

            $client->contacts()->detach();
            $client->contacts()->delete();
            $client->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function getMyClient(int $id) {
        $client = Client::find($id);
        
        if(is_null($client)) {
            return null;
        }

        if($client->employee_id != User::logged()->employee->id) {
            throw new \Exception('Não é possível visualizar um cliente que não foi cadastrado por você.');
        }

        $client->city;
        $client->city->state;
        $client->employee;
        return $client;
    }

    public static function filterMyClient($query) {
        $clients = Client::where('name', 'like', $query . '%')
            ->where('employee_id', '=', User::logged()->employee->id)
            ->orWhere('fantasy_name', 'like', $query . '%')
            ->orWhere('cnpj', 'like', $query . '%')
            ->get();

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->status;
        }

        return $clients;
    }

    public function getCnpjAttribute($value) {
        return mask(str_pad($value, 14, '0', STR_PAD_LEFT), '##.###.###/####-##');
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

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id');
    }

    public function type() {
        return $this->belongsTo('App\ClientType', 'client_type_id');
    }

    public function status() {
        return $this->belongsTo('App\ClientStatus', 'client_status_id');
    }

    public function contacts() {
        return $this->belongsToMany('App\Contact', 'client_contact', 'client_id', 'contact_id');
    }
}
