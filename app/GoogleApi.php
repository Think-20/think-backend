<?php

namespace App;

class GoogleApi {
    const URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/';
    const KEY = 'AIzaSyBOpwQgja8rzkVDt7jA_T0VQvk2EoJWtlA';

    public static function getAutoPlace($latlng) {
        $response = json_decode(GoogleApi::execute(GoogleApi::URL . 'json?location=' . $latlng . '&rankby=distance&types=point_of_interest|establishment&key=' . GoogleApi::KEY));
        return isset($response->results[0]) ? $response->results[0]->name : 'Não detectado';
    }

    protected static function execute($url) {
        $conextOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ];

        return file_get_contents($url, false, stream_context_create($conextOptions));
    }

}