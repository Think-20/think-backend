<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organization';

    protected $fillable = [
        'name',
        'city',
        'address',
        'address_number',
        'site',
        'client_id'
    ];

    public static function list()
    {
        $organizations = Organization::get();

        foreach ($organizations as $organization) {
            $organization->client_object;
        }

        return $organizations;
    }

    public static function getUnique(int $id = null)
    {
        $organization = Organization::find($id);

        
        if (!$organization) {
            return null;
        }

        $organization->client_object;

        return $organization;
    }

    public function client_object()
    {
        return $this->hasOne(Client::class, "id", "client_id");
    }
}
