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
            if (! self::shouldRetryWithAlternateToken($e)) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . self::requestExceptionMessage($e));
            }

            $token = self::resolveAlternateAccessToken($token);

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
     * Producao: OAuth via Consumer Key/Secret (USERNAME/PASSWORD).
     * Bearer estatico fica apenas como fallback.
     *
     * @return string
     */
    private static function resolveAccessToken()
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            return self::refreshAccessToken();
        } catch (Exception $e) {
            $fallback = self::configuredBearerToken();
            if ($fallback !== '') {
                return $fallback;
            }

            throw $e;
        }
    }

    /**
     * Em 401/403, tenta o outro meio de autenticacao disponivel.
     *
     * @param string $currentToken
     * @return string
     */
    private static function resolveAlternateAccessToken($currentToken)
    {
        try {
            $refreshed = self::refreshAccessToken();
            if ($refreshed !== $currentToken) {
                return $refreshed;
            }
        } catch (Exception $e) {
            // Tenta bearer estatico abaixo.
        }

        $bearer = self::configuredBearerToken();
        if ($bearer !== '' && $bearer !== $currentToken) {
            return $bearer;
        }

        throw new Exception('Nao foi possivel renovar o token SERPRO.');
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
        $username = config('services.serpro.username');
        $password = config('services.serpro.password');

        if (empty($username) || empty($password)) {
            throw new Exception('Credenciais SERPRO nao configuradas.');
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
     * @return string
     */
    private static function configuredBearerToken()
    {
        $token = config('services.serpro.bearer_token');

        return is_string($token) ? trim($token) : '';
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
    private static function shouldRetryWithAlternateToken(RequestException $e)
    {
        if (! $e->hasResponse()) {
            return false;
        }

        $status = $e->getResponse()->getStatusCode();
        if ($status === 401 || $status === 403) {
            return true;
        }

        $body = (string) $e->getResponse()->getBody();

        return strpos($body, '900908') !== false;
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
        $verify = config('services.serpro.ssl_verify');

        if ($verify === null || $verify === '') {
            $verify = config('app.env') === 'local' ? false : true;
        } else {
            $verify = filter_var($verify, FILTER_VALIDATE_BOOLEAN);
        }

        return ['verify' => $verify];
    }
}
