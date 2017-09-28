<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = 'client';

    protected $fillable = [
        'name', 'fantasyName', 'cnpj', 'mainphone', 'secundaryphone', 'site', 'rate', 'note',
        'street', 'number', 'neighborhood', 'complement', 'cep', 'cityId', 
        'employeeId', 'clientTypeId', 'clientStatusId'
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
        $id = $data['id'];
        $client = Client::find($id);
        $client->cityId = isset($data['city']['id']) ? $data['city']['id'] : null;
        $client->employeeId = isset($data['employee']['id']) ? $data['employee']['id'] : null;
        $client->clientTypeId = isset($data['clientType']['id']) ? $data['clientType']['id'] : null;
        $client->clientStatusId = isset($data['clientStatus']['id']) ? $data['clientStatus']['id'] : null;

        #Deleta contatos antigos
        $client->contacts()->delete();

        $contacts = isset($data['contacts']) ? $data['contacts'] : [];

        foreach($contacts as $contact) {
            Contact::insert(array_merge($contact, [
                'clientId' => $client->id
            ]));
        }

        return $client->update($data);
    }

    public static function insert(array $data) {
        $client = new Client($data);
        $client->cityId = isset($data['city']['id']) ? $data['city']['id'] : null;
        $client->employeeId = isset($data['employee']['id']) ? $data['employee']['id'] : null;
        $client->clientTypeId = isset($data['clientType']['id']) ? $data['clientType']['id'] : null;
        $client->clientStatusId = isset($data['clientStatus']['id']) ? $data['clientStatus']['id'] : null;
        $client->save();

        $contacts = isset($data['contacts']) ? $data['contacts'] : [];

        foreach($contacts as $contact) {
            Contact::insert(array_merge($contact, [
                'clientId' => $client->id
            ]));
        }
    }

    public static function remove($id) {
        $client = Client::find($id);
        $client->contacts()->delete();
        $client->delete();
    }

    public static function get(int $id) {
        $client = Client::find($id);
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
            ->orWhere('fantasyName', 'like', $query . '%')
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
        $clients = Client::where('employeeId', '=', User::logged()->employee->id)
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
        $id = $data['id'];
        $client = Client::find($id);

        if($client->employeeId != User::logged()->employee->id) {
            throw new \Exception('Não é possível editar um cliente que não foi cadastrado por você.');
        }

        $client->cityId = isset($data['city']['id']) ? $data['city']['id'] : null;
        $client->employeeId = isset($data['employee']['id']) ? $data['employee']['id'] : null;
        $client->clientTypeId = isset($data['clientType']['id']) ? $data['clientType']['id'] : null;
        $client->clientStatusId = isset($data['clientStatus']['id']) ? $data['clientStatus']['id'] : null;

        #Deleta contatos antigos
        $client->contacts()->delete();

        $contacts = isset($data['contacts']) ? $data['contacts'] : [];

        foreach($contacts as $contact) {
            Contact::insert(array_merge($contact, [
                'clientId' => $client->id
            ]));
        }

        return $client->update($data);
    }

    public static function removeMyClient($id) {
        $client = Client::find($id);

        if($client->employeeId != User::logged()->employee->id) {
            throw new \Exception('Não é possível remover um cliente que não foi cadastrado por você.');
        }

        $client->contacts()->delete();
        $client->delete();
    }

    public static function getMyClient(int $id) {
        $client = Client::find($id);

        if($client->employeeId != User::logged()->employee->id) {
            throw new \Exception('Não é possível visualizar um cliente que não foi cadastrado por você.');
        }

        $client->city;
        $client->city->state;
        $client->employee;
        return $client;
    }

    public static function filterMyClient($query) {
        $clients = Client::where('name', 'like', $query . '%')
            ->where('employeeId', '=', User::logged()->employee->id)
            ->orWhere('fantasyName', 'like', $query . '%')
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
        return $this->belongsTo('App\City', 'cityId');
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employeeId');
    }

    public function type() {
        return $this->belongsTo('App\ClientType', 'clientTypeId');
    }

    public function status() {
        return $this->belongsTo('App\ClientStatus', 'clientStatusId');
    }

    public function contacts() {
        return $this->hasMany('App\Contact', 'clientId');
    }
}
