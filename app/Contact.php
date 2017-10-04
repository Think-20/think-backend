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
        $contactable->contacts()->save($contact);
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
