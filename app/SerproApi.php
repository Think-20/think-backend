<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;

class SerproApi
{
    const QSA_URL = 'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df-trial/v2/qsa/';
    const TOKEN_URL = 'https://gateway.apiserpro.serpro.gov.br/token';
    const CACHE_KEY = 'serpro_access_token';

    public static function serproQsa($cnpj)
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (empty($cnpj)) {
            throw new Exception('CNPJ invalido.');
        }

        $token = self::resolveAccessToken();

        try {
            return self::requestQsa($cnpj, $token);
        } catch (RequestException $e) {
            if (! self::isUnauthorized($e)) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . self::requestExceptionMessage($e));
            }

            $token = self::refreshAccessToken();

            try {
                return self::requestQsa($cnpj, $token);
            } catch (RequestException $retryException) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . self::requestExceptionMessage($retryException));
            }
        }
    }

    /**
     * Obtem bearer token via POST /token e persiste em cache.
     *
     * @return string
     */
    public static function bearerSerpro()
    {
        return self::refreshAccessToken();
    }

    /**
     * @return string
     */
    private static function resolveAccessToken()
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        return self::refreshAccessToken();
    }

    /**
     * @return string
     */
    private static function refreshAccessToken()
    {
        Cache::forget(self::CACHE_KEY);

        $data = self::fetchAccessTokenFromSerpro();

        if (empty($data['access_token'])) {
            throw new Exception('Resposta SERPRO sem access_token.');
        }

        $token = trim((string) $data['access_token']);
        $ttl = self::tokenTtlSeconds($data);

        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * @return array
     */
    private static function fetchAccessTokenFromSerpro()
    {
        $username = env('SERPRO_USERNAME');
        $password = env('SERPRO_PASSWORD');

        if (empty($username) || empty($password)) {
            throw new Exception('Credenciais SERPRO nao configuradas.');
        }

        $client = new Client();

        try {
            $response = $client->request('POST', self::TOKEN_URL, array_merge(self::requestOptions(), [
                'auth' => [$username, $password],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]));

            $data = json_decode($response->getBody(), true);

            if (! is_array($data)) {
                throw new Exception('Resposta SERPRO invalida ao obter token.');
            }

            return $data;
        } catch (RequestException $e) {
            throw new Exception('Erro ao obter bearer token no SERPRO: ' . self::requestExceptionMessage($e));
        }
    }

    /**
     * @param string $cnpj
     * @param string $token
     * @return array|null
     */
    private static function requestQsa($cnpj, $token)
    {
        $client = new Client();

        $response = $client->request('GET', self::QSA_URL . $cnpj, array_merge(self::requestOptions(), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => self::authorizationHeader($token),
            ],
        ]));

        return json_decode($response->getBody(), true);
    }

    /**
     * @param array $data
     * @return int
     */
    private static function tokenTtlSeconds(array $data)
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;

        if ($expiresIn < 1) {
            return 60;
        }

        // Renova um pouco antes do vencimento informado pelo SERPRO.
        return max(60, $expiresIn - 60);
    }

    /**
     * @param RequestException $e
     * @return bool
     */
    private static function isUnauthorized(RequestException $e)
    {
        return $e->hasResponse() && $e->getResponse()->getStatusCode() === 401;
    }

    /**
     * @param RequestException $e
     * @return string
     */
    private static function requestExceptionMessage(RequestException $e)
    {
        return $e->hasResponse()
            ? (string) $e->getResponse()->getBody()
            : $e->getMessage();
    }

    /**
     * Monta o header Authorization sem duplicar o prefixo Bearer.
     *
     * @param string $token
     * @return string
     */
    private static function authorizationHeader($token)
    {
        $token = trim($token);

        if (preg_match('/^Bearer\s+/i', $token)) {
            return $token;
        }

        return 'Bearer ' . $token;
    }

    /**
     * Opcoes comuns do Guzzle (verificacao SSL).
     *
     * @return array
     */
    private static function requestOptions()
    {
        $verify = env('SERPRO_SSL_VERIFY');

        if ($verify === null || $verify === '') {
            $verify = env('APP_ENV') === 'local' ? false : true;
        } else {
            $verify = filter_var($verify, FILTER_VALIDATE_BOOLEAN);
        }

        return ['verify' => $verify];
    }
}
