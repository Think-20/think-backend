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
        'enabled' => env('SERPRO_ENABLED', true),
        'username' => env('SERPRO_USERNAME'),
        'password' => env('SERPRO_PASSWORD'),
        'ssl_verify' => env('SERPRO_SSL_VERIFY'),
        'qsa_url' => env('SERPRO_QSA_URL', 'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df/v2/qsa/'),
        'token_url' => env('SERPRO_TOKEN_URL', 'https://gateway.apiserpro.serpro.gov.br/token'),
    ],

    /*
    | Portal Servicos Daycoval/Fromtis (SOAP).
    | BASE_URL exemplo: https://[host]/portal-servicos
    | Endpoint usado: {BASE_URL}/servicos/soap/cadastroCedenteAprovado
    */
    'daycoval' => [
        'enabled' => env('DAYCOVAL_ENABLED', false),
        'base_url' => env('DAYCOVAL_BASE_URL'),
        'username' => env('DAYCOVAL_USERNAME'),
        'password' => env('DAYCOVAL_PASSWORD'),
        'ssl_verify' => env('DAYCOVAL_SSL_VERIFY', false),
        'timeout' => env('DAYCOVAL_TIMEOUT', 60),
        'fail_on_error' => env('DAYCOVAL_FAIL_ON_ERROR', false),
        'default_porte' => env('DAYCOVAL_DEFAULT_PORTE', '9'),
        'default_ramo' => env('DAYCOVAL_DEFAULT_RAMO', 'SERVICOS'),
        'default_tipo_sociedade' => env('DAYCOVAL_DEFAULT_TIPO_SOCIEDADE', 'LTDA'),
        'default_class_risco' => env('DAYCOVAL_DEFAULT_CLASS_RISCO', '1'),
    ],

    /*
    | Vadu/CreditBox — restricoes apos SERPRO passar limpo.
    | Token master (VADU_TOKEN) -> GET JSONPegarToken -> token temporario -> POST Consulta/{cnpj}
    | Com restricao o cedente vai para cancelado e fica travado enquanto houver restricao.
    */
    'vadu' => [
        'enabled' => env('VADU_ENABLED', true),
        'token' => env('VADU_TOKEN'),
        'token_url' => env('VADU_TOKEN_URL', 'https://www.creditbox.com.br/CreditBox.dll/Autenticacao/JSONPegarToken'),
        'consulta_url' => env('VADU_CONSULTA_URL', 'https://www.vadu.com.br/vadu.dll/ServicoAnaliseOperacao/Consulta'),
        'token_ttl_minutes' => env('VADU_TOKEN_TTL_MINUTES', 50),
        'ssl_verify' => env('VADU_SSL_VERIFY', false),
        'timeout' => env('VADU_TIMEOUT', 60),
    ],

];
