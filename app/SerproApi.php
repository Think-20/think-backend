<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SerproApi
{
    const QSA_URL = 'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df-trial/v2/qsa/';
    const TOKEN_URL = 'https://gateway.apiserpro.serpro.gov.br/token';

    public static function serproQsa($cnpj)
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (empty($cnpj)) {
            throw new Exception('CNPJ invalido.');
        }

        $token = env('SERPRO_BEARER_TOKEN');

        if (empty($token)) {
            throw new Exception('Token SERPRO nao configurado.');
        }

        $client = new Client();

        try {
            $response = $client->request('GET', self::QSA_URL . $cnpj, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $message = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();

            throw new Exception('Erro ao consultar QSA no SERPRO: ' . $message);
        }
    }

    public static function bearerSerpro()
    {
        $username = env('SERPRO_USERNAME');
        $password = env('SERPRO_PASSWORD');

        if (empty($username) || empty($password)) {
            throw new Exception('Credenciais SERPRO nao configuradas.');
        }

        $client = new Client();

        try {
            $response = $client->request('POST', self::TOKEN_URL, [
                'auth' => [$username, $password],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (empty($data['access_token'])) {
                throw new Exception('Resposta SERPRO sem access_token.');
            }

            return $data['access_token'];
        } catch (RequestException $e) {
            $message = $e->hasResponse()
                ? (string) $e->getResponse()->getBody()
                : $e->getMessage();

            throw new Exception('Erro ao obter bearer token no SERPRO: ' . $message);
        }
    }
}
