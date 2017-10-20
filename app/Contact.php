<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\Interfaces\Contactable;

class Contact extends Model
{
    public $timestamps = false;

    protected $table = 'contact';

    protected $fillable = [
        'name', 'email', 'department', 'cellphone'
    ];

    public static function manage(array $contactsDataArray, Contactable $contactable) {
        $oldContacts = $contactable->contacts;
        $contactIds = [];

        foreach($contactsDataArray as $contact) {
            //Exists, update
            if(isset($contact['id'])) {
                $contactIds[] = $contact['id'];
                Contact::edit($contact);
            } 
            //Create because not found
            else {
                Contact::insert($contact, $contactable);
            }
        }

        Contact::deleteOldIds($oldContacts, $contactIds, $contactable);
    }

    public static function deleteOldIds($oldContacts, array $contactIds, Contactable $contactable) {
        foreach($oldContacts as $contact) {
            if(!in_array($contact->id, $contactIds)) {
                $contactable->contacts()->detach($contact);
                $contact->delete();
            }
        }
    }

    public static function edit($data) {
        $contact = Contact::find($data['id']);
        $contact->update($data);
    }

    public static function insert(array $data, Contactable $contactable) {
        $contact = new Contact($data);
        $contact->save();
        $contactable->contacts()->save($contact);
        return $contact;
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
            'name' => $row[10], 
            'email' => $row[11], 
            'department' => $row[12], 
            'cellphone' => $row[13]
        ];
    }

    public function getCellphoneAttribute($value) {
        $phone = null;

        if(strlen($value) == 10) {
            $phone = mask($value, '(##) ####-####');
        } else if(strlen($value) == 11) {
            $phone = mask($value, '(##) ####-#####');
        }

        return $phone;
    }

    public function setCellphoneAttribute($value) {
        $this->attributes['cellphone'] = (int) preg_replace('/[^0-9]+/', '', $value);
    }
}
