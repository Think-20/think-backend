<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use PHPExcel_IOFactory;
use PHPExcel_Settings;
use DB;
use DateTime;

use App\Validators\Validator;
use App\Interfaces\Contactable;

class Client extends Model implements Contactable
{
    protected $table = 'client';

    protected $fillable = [
        'name', 'fantasy_name', 'ie', 'cnpj', 'mainphone', 'secundaryphone', 'site', 'rate', 'note',
        'street', 'number', 'neighborhood', 'complement', 'cep', 'city_id', 
        'employee_id', 'client_type_id', 'client_status_id', 'comission_id', 'external'
    ];

    public function checkCnpj() {
        $duplicateClient = Client::where('cnpj', '=', preg_replace('/[^0-9]+/', '', $this->cnpj))
        ->where('id', '<>', $this->id)
        ->get();

        $duplicateName = Client::where('name', '=', $this->name)
        ->where('id', '<>', $this->id)
        ->get();

        if($duplicateClient->count() > 0 && $this->external == 0) {
            throw new \Exception('O CNPJ já está cadastrado.');
        }
        else if($duplicateName->count() > 0 && $this->external == 1) {
            throw new \Exception('Essa razão social já está cadastrada.');
        }

        return true;
    }

    public static function list() {
        $clients = Client::select()
        ->orderBy('fantasy_name', 'asc')
        ->paginate(20);

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->comission;
            $client->status;
        }
        
        return [
            'pagination' => $clients,
            'updatedInfo' => Client::updatedInfo()
        ];
    }

    public static function edit(array $data) {
        DB::beginTransaction();
        Client::checkData($data);
        
        try {
            $id = $data['id'];
            $client = Client::find($id);
            $client->logIfAttendanceChanges($data);
            $client->checkCnpj();
            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->comission_id = isset($data['comission']['id']) ? $data['comission']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manage($contacts, $client);

            $client->update($data);

            $message = 'Cadastro do cliente ' . $client->fantasy_name . ' alterado';
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], [], 'Alteração de cliente', $client->id);

            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function logContactChanges(array $data) {
        LogClient::insert($data);
    }

    public function logIfAttendanceChanges(array $data) {
        $employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : '';

        if($employee_id != '' && $employee_id != $this->employee_id) {
            $description = 'O atendimento foi alterado de ' . $this->employee->name;
            $description .= ' para ' . Employee::find($employee_id)->name;

            LogClient::insert([
                'client_id' => $this->id,
                'description' => $description,
                'type' => 'Alteração de atendimento'
            ]);
        }
    }

    public static function insert(array $data) {
        DB::beginTransaction();
        Client::checkData($data);
        
        try {
            $client = new Client($data);
            $client->checkCnpj();
            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->comission_id = isset($data['comission']['id']) ? $data['comission']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;
            $client->save();

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manage($contacts, $client);

            $message = 'Novo cliente ' . $client->fantasy_name . ' cadastrado';
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], [], 'Cadastro de cliente', $client->id);

            DB::commit();

            return $client;
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function remove($id) {
        DB::beginTransaction();
        
        try {
            $client = Client::find($id);
            
            $message = 'Cliente ' . $client->fantasy_name . ' removido';
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], [], 'Deleção de cliente', $client->id);

            $contacts = $client->contacts;
            $client->contacts()->detach();
            foreach($contacts as $contact) {
                $contact->delete();
            }
            $client->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function get(int $id) {
        $client = Client::with('contacts')
        ->with('city', 'city.state', 'employee', 'type', 'comission', 'status', 'logs')
        ->where('client.id', '=', $id)->first();  
                
        if(is_null($client)) {
            return null;
        }

        foreach($client->jobs as $job) {
            $job->job_activity;
            $job->attendance;
            $job->status;
            $job->responsibles();
        }

        return $client;
    }

    public static function filter(array $data) {
        $search = isset($data['search']) ? $data['search'] : null;
        $attendanceId = isset($data['attendance']['id']) ? $data['attendance']['id'] : null;
        $clientStatusId = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;
        $clientTypeId = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
        $rate = isset($data['rate']) ? $data['rate'] : null;

        $query = Client::select();

        if( ! is_null($clientTypeId) ) {
            $query->where('client_type_id', '=', $clientTypeId);
        }

        if( ! is_null($clientStatusId) ) {
            $query->where('client_status_id', '=', $clientStatusId);
        }

        if( ! is_null($rate) ) {
            $query->where('rate', '=', $rate);
        }

        if( ! is_null($search) ) {
            $query->where(function($query2) use ($search) {
                $query2->orWhere('name', 'like', $search . '%');
                $query2->orWhere('fantasy_name', 'like', $search . '%');
                $query2->orWhere('cnpj', 'like', $search . '%');
            });
        }

        if( ! is_null($attendanceId) ) {
            $query->where('employee_id', '=', $attendanceId);
        }

        $query->orderBy('fantasy_name', 'asc');
        $clients = $query->paginate(20);

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->comission;
            $client->status;
        }

        return [
            'pagination' => $clients,
            'updatedInfo' => Client::updatedInfo()
        ];
    }

    public static function updatedInfo() {
        $lastData = Client::orderBy('updated_at', 'desc')->limit(1)->first();

        if($lastData == null) {
            return [];
        }

        return [
            'date' => (new DateTime($lastData->updated_at))->format('d/m/Y'),
            'employee' => $lastData->employee->name
        ];
    }

    public static function byName($name) {
        $client = Client::where('fantasy_name', '=', $name)->get();

        if($client->count() == 0) {
            throw new \Exception('O cliente de nome ' . $name . ' não existe.');
        } 

        return $client->first();
    }

    public static function searchClient($array, $pos) {
        for($i = $pos; $i > 0; $i--) {
            if(isset($array[$i]) && $array[$i][0] != '') {
                return $array[$i][0];
            }
        }
    }

    public static function import(UploadedFile $uploadedFile) {
        $informations = [];
        PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
        $phpExcel = PHPExcel_IOFactory::load($uploadedFile->getPathname());
        $dataSheet = $phpExcel->getSheet(0)->toArray();
        $client = null;
        unset($dataSheet[0]);

        DB::beginTransaction();
        foreach($dataSheet as $key => $row) {
            $message = null;

            try {
                if(!empty($row[0])) {
                    $dataClient = Client::extractFromArray($row);
                    $client = Client::insert($dataClient);

                    $dataContact = Contact::extractFromArray($row);
                    $contacts = array_merge($client->contacts->toArray(), [$dataContact]);
                    Contact::manage($contacts, $client);
                    $message = 'Cliente ' . $row[0] . ' cadastrado com sucesso.';
                    
                    $message1 = 'Cliente ' . $client->fantasy_name . ' cadastrado';
                    Notification::createAndNotify(User::logged()->employee, [
                        'message' => $message1
                    ], [], 'Cadastro de cliente', $client->id);
                } else {
                    $dataContact = Contact::extractFromArray($row);
                    $name = Client::searchClient($dataSheet, $key);
                    $client = Client::byName($name);
                    $contacts = array_merge($client->contacts->toArray(), [$dataContact]);
                    Contact::manage($contacts, $client);
                    $message = 'Contato ' . $contacts[0]['name'] . ' cadastrado com sucesso.';
                }
                $informations[] = [
                    'message' => $message,
                    'status' => true
                ];
            } catch(\Exception $e) {
                DB::rollBack();
                $informations[] = [
                    'message' => 'Erro ao cadastrar o cliente ' . $row[0] . ': ' . $e->getMessage(),
                    'status' => false
                ];
            }
            DB::commit();
        }

        return $informations;
    }

    public static function extractFromArray($row) {
        /*
            Importação de cliente

            0 => "Nome"    1 => "Razão Social"    2 => "Site"    3 => "Tipo"    4 => "Status"    
            5 => "Score"    6 => "Cnpj"    7 => "Inscrição Estadual"    
            8 => "Telefone Principal"    9 => "Telefone Secundario"    10 => "Contato"    
            11 => "E-mail"    12 => "Departamento"    13 => "Celular"    14 => "Observação"    
            15 => "CEP"    16 => "Logradouro"    17 => "Numero"    18 => "Complemento"    19 => "Estado"    
            20 => "Cidade"    21 => "Bairro"
        */
        return [
            'fantasy_name' => $row[0],
            'name' => $row[1],
            'site' => $row[2],
            'client_type' => ['id' => ClientType::byDescription($row[3])->id],
            'client_status' => ['id' => ClientStatus::byDescription($row[4])->id],
            'employee' => ['id' => User::logged()->employee->id],
            'score' => 0, //$row[5],
            'cnpj' => $row[6],
            'ie' => $row[7],
            'mainphone' => $row[8],
            'secundaryphone' => $row[9],
            'note' => $row[14],
            'cep' => $row[15],
            'street' => $row[16],
            'number' => $row[17],
            'complement' => $row[18],
            'city' => ['id' => City::byName($row[19], $row[20])->id],
            'neighborhood' => $row[21],
        ];
    }
    
    public static function checkData(array $data, $editMode = false) {
        if(!isset($data['state']['id'])) {
            throw new \Exception('Estado não informado!');
        }

        if(!isset($data['city']['id'])) {
            throw new \Exception('Cidade não informada!');
        }
    }

    # My clients #
    public static function listMyClient() {
        $clients = Client::where('employee_id', '=', User::logged()->employee->id)
        ->orderBy('fantasy_name', 'asc')
        ->paginate(20);

        foreach($clients as $client) {
            $client->employee;
            $client->type;
            $client->comission;
            $client->status;
        }
        
        return [
            'pagination' => $clients,
            'updatedInfo' => Client::updatedInfo()
        ];
    }

    public static function editMyClient(array $data) {
        DB::beginTransaction();
        Client::checkData($data);
        
        try {
            $id = $data['id'];
            $client = Client::find($id);
            $client->checkCnpj();

            if($client->employee_id != User::logged()->employee->id) {
                throw new \Exception('Não é possível editar um cliente que não foi cadastrado por você.');
            }

            $client->city_id = isset($data['city']['id']) ? $data['city']['id'] : null;
            $client->employee_id = isset($data['employee']['id']) ? $data['employee']['id'] : null;
            $client->client_type_id = isset($data['client_type']['id']) ? $data['client_type']['id'] : null;
            $client->comission_id = isset($data['comission']['id']) ? $data['comission']['id'] : null;
            $client->client_status_id = isset($data['client_status']['id']) ? $data['client_status']['id'] : null;

            $contacts = isset($data['contacts']) ? $data['contacts'] : [];
            Contact::manage($contacts, $client);

            $client->update($data);
            
            $message = 'Cliente ' . $client->fantasy_name . ' alterado';
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], [], 'Alteração de cliente', $client->id);

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
            
            $message = 'Cliente ' . $client->fantasy_name . ' removido';
            Notification::createAndNotify(User::logged()->employee, [
                'message' => $message
            ], [], 'Deleção de cliente', $client->id);

            if($client->employee_id != User::logged()->employee->id) {
                throw new \Exception('Não é possível remover um cliente que não foi cadastrado por você.');
            }

            $contacts = $client->contacts;
            $client->contacts()->detach();
            foreach($contacts as $contact) {
                $contact->delete();
            }
            $client->delete();
            DB::commit();
        } catch(\Exception $e) {
            DB::rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public static function getMyClient(int $id) {
        $client = Client::with('contacts')
        ->with('city', 'city.state', 'employee', 'type', 'comission', 'status', 'logs')
        ->where('client.id', '=', $id)->first();  
                
        if(is_null($client)) {
            return null;
        }

        if($client->employee_id != User::logged()->employee->id) {
            throw new \Exception('Não é possível visualizar um cliente que não foi cadastrado por você.');
        }

        foreach($client->jobs as $job) {
            $job->job_activity;
            $job->attendance;
            $job->status;
            $job->responsibles();
        }

        return $client;
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

    public function setFantasyNameAttribute($value) {
        Validator::field('nome', $value)
            ->required()
            ->minLength(3)
            ->maxLength(50);

        $this->attributes['fantasy_name'] = ucwords(mb_strtolower($value));
    }
    
    public function setNameAttribute($value) {
        Validator::field('razão social', $value)
            ->required()
            ->minLength(3)
            ->maxLength(100);

        $this->attributes['name'] = $value;
    }
    
    public function setIeAttribute($value) {
        $this->attributes['ie'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setCnpjAttribute($value) {
        Validator::field('CNPJ', $value)
            ->minLength(18)
            ->maxLength(18);

        $this->attributes['cnpj'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setMainphoneAttribute($value) {
        Validator::field('telefone principal', $value)
            ->required()
            ->minLength(10);

        $this->attributes['mainphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setSecundaryphoneAttribute($value) {
        if($value != '') {
            Validator::field('telefone secundário', $value)
                ->minLength(10);
        }

        $this->attributes['secundaryphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setSiteAttribute($value) {
        if($value != '') {
            Validator::field('site', $value)
                ->minLength(7);
        }

        $this->attributes['site'] = $value;
    }

    public function setStreetAttribute($value) {
        Validator::field('logradouro', $value)
            ->required()
            ->minLength(3)
            ->maxLength(50);

        $this->attributes['street'] = $value;
    }
    
    public function setNumberAttribute($value) {
        Validator::field('número', $value)
            ->required()
            ->maxLength(11);

        $this->attributes['number'] = $value;
    }
    
    public function setNeighborhoodAttribute($value) {
        Validator::field('bairro', $value)
            ->required()
            ->minLength(3)
            ->maxLength(30);

        $this->attributes['neighborhood'] = $value;
    }
    
    public function setComplementAttribute($value) {
        Validator::field('complemento', $value)
            ->maxLength(255);

        $this->attributes['complement'] = $value;
    }
    
    public function setCepAttribute($value) {
        Validator::field('CEP', $value)
            ->required()
            ->minLength(9)
            ->maxLength(9);

        $this->attributes['cep'] = preg_replace('/[^0-9]+/', '', $value);
    }
    
    public function setEmployeeIdAttribute($value) {
        Validator::field('funcionário', $value)
            ->required();

        $this->attributes['employee_id'] = $value;
    }
    
    public function setClientTypeIdAttribute($value) {
        Validator::field('tipo', $value)
            ->required();

        $this->attributes['client_type_id'] = $value;
    }
    
    public function setClientStatusIdAttribute($value) {
        Validator::field('status', $value)
            ->required();

        $this->attributes['client_status_id'] = $value;
    }

    public function jobs() {
        return $this->hasMany('App\Job','client_id')->union($this->hasMany('App\Job','agency_id'));
        
    }

    public function city() {
        return $this->belongsTo('App\City', 'city_id');
    }

    public function employee() {
        return $this->belongsTo('App\Employee', 'employee_id')->withTrashed();
    }

    public function type() {
        return $this->belongsTo('App\ClientType', 'client_type_id');
    }

    public function logs() {
        return $this->hasMany('App\LogClient', 'client_id');
    }

    public function comission() {
        return $this->belongsTo('App\ClientComission', 'comission_id');
    }

    public function status() {
        return $this->belongsTo('App\ClientStatus', 'client_status_id');
    }

    public function contacts() {
        return $this->belongsToMany('App\Contact', 'client_contact', 'client_id', 'contact_id');
    }
}
