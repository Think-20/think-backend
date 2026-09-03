<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;

class SerproApi
{
    const DEFAULT_QSA_URL = 'https://gateway.apiserpro.serpro.gov.br/consulta-cnpj-df/v2/qsa/';
    const DEFAULT_TOKEN_URL = 'https://gateway.apiserpro.serpro.gov.br/token';
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
            if (! self::shouldRefreshToken($e)) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . self::requestExceptionMessage($e));
            }

            // Token invalido/expirado ou sem autorizacao: gera novo via USERNAME/PASSWORD e tenta de novo.
            $token = self::refreshAccessToken();

            try {
                return self::requestQsa($cnpj, $token);
            } catch (RequestException $retryException) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . self::requestExceptionMessage($retryException));
            }
        }
    }

    /**
     * Obtem bearer token via POST /token (USERNAME + PASSWORD) e persiste em cache.
     *
     * @return string
     */
    public static function bearerSerpro()
    {
        return self::refreshAccessToken();
    }

    /**
     * Usa token em cache se ainda valido; senao gera novo com Consumer Key/Secret.
     *
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
     * Sempre gera um novo access_token via OAuth (igual ao Postman: USERNAME + PASSWORD).
     *
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
        $ttlMinutes = self::tokenTtlMinutes($data);

        // Laravel 5.6: Cache::put usa minutos (nao segundos).
        Cache::put(self::CACHE_KEY, $token, $ttlMinutes);

        return $token;
    }

    /**
     * @return array
     */
    private static function fetchAccessTokenFromSerpro()
    {
        $username = self::credentialValue(config('services.serpro.username'));
        $password = self::credentialValue(config('services.serpro.password'));

        if ($username === '' || $password === '') {
            throw new Exception(
                'Credenciais SERPRO nao configuradas. No .env do servidor PHP use SERPRO_USERNAME (Consumer Key) e SERPRO_PASSWORD (Consumer Secret) da Area do Cliente SERPRO — nao e o e-mail/senha de login da Think. Depois: php artisan config:clear'
            );
        }

        $client = new Client();

        try {
            $response = $client->request('POST', self::tokenUrl(), array_merge(self::requestOptions(), [
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

        $response = $client->request('GET', self::qsaUrl() . $cnpj, array_merge(self::requestOptions(), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => self::authorizationHeader($token),
            ],
        ]));

        return json_decode($response->getBody(), true);
    }

    /**
     * @return string
     */
    private static function qsaUrl()
    {
        $url = config('services.serpro.qsa_url', self::DEFAULT_QSA_URL);
        $url = is_string($url) && $url !== '' ? $url : self::DEFAULT_QSA_URL;

        return rtrim($url, '/') . '/';
    }

    /**
     * @return string
     */
    private static function tokenUrl()
    {
        $url = config('services.serpro.token_url', self::DEFAULT_TOKEN_URL);

        return is_string($url) && $url !== '' ? $url : self::DEFAULT_TOKEN_URL;
    }

    /**
     * Converte expires_in (segundos) para minutos do Cache do Laravel 5.6.
     * Renova um pouco antes do vencimento informado pela SERPRO.
     *
     * @param array $data
     * @return int
     */
    private static function tokenTtlMinutes(array $data)
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;

        if ($expiresIn < 1) {
            return 1;
        }

        $seconds = max(60, $expiresIn - 60);

        return max(1, (int) floor($seconds / 60));
    }

    /**
     * @param RequestException $e
     * @return bool
     */
    private static function shouldRefreshToken(RequestException $e)
    {
        if (! $e->hasResponse()) {
            return false;
        }

        $status = $e->getResponse()->getStatusCode();
        if ($status === 401 || $status === 403) {
            return true;
        }

        $body = (string) $e->getResponse()->getBody();

        return strpos($body, '900908') !== false
            || strpos($body, '900901') !== false
            || strpos($body, '900902') !== false;
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
     * @return array
     */
    private static function requestOptions()
    {
        $verify = config('services.serpro.ssl_verify');

        if ($verify === null || $verify === '') {
            $verify = config('app.env') === 'local' ? false : true;
        } else {
            $verify = filter_var($verify, FILTER_VALIDATE_BOOLEAN);
        }

        return ['verify' => $verify];
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function credentialValue($value)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '' || strtolower($text) === 'null') {
            return '';
        }

        return $text;
    }
}
