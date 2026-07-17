<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'serpro' => [
        'username' => env('SERPRO_USERNAME'),
        'password' => env('SERPRO_PASSWORD'),
        'ssl_verify' => env('SERPRO_SSL_VERIFY'),
        'qsa_url' => env('SERPRO_QSA_URL', 'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df/v2/qsa/'),
        'token_url' => env('SERPRO_TOKEN_URL', 'https://gateway.apiserpro.serpro.gov.br/token'),
    ],

];
