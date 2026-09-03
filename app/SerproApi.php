<?php

namespace App;

use App\Support\CurlHttp;
use Exception;
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
        } catch (Exception $e) {
            if (! self::shouldRetryWithAlternateToken($e)) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . $e->getMessage());
            }

            $token = self::resolveAlternateAccessToken($token);

            try {
                return self::requestQsa($cnpj, $token);
            } catch (Exception $retryException) {
                throw new Exception('Erro ao consultar QSA no SERPRO: ' . $retryException->getMessage());
            }
        }
    }

    /**
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
                'Credenciais SERPRO nao configuradas. Use SERPRO_USERNAME (Consumer Key) e SERPRO_PASSWORD (Consumer Secret). Depois: php artisan config:clear'
            );
        }

        $response = CurlHttp::request('POST', self::tokenUrl(), [
            'auth' => [$username, $password],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
            'verify' => self::sslVerify(),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception('Erro ao obter bearer token no SERPRO: HTTP ' . $response['status'] . ' ' . $response['body']);
        }

        $data = json_decode($response['body'], true);

        if (! is_array($data)) {
            throw new Exception('Resposta SERPRO invalida ao obter token.');
        }

        return $data;
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
        $response = CurlHttp::request('GET', self::qsaUrl() . $cnpj, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => self::authorizationHeader($token),
            ],
            'verify' => self::sslVerify(),
        ]);

        if (self::isAuthFailureStatus($response['status'], $response['body'])) {
            throw new SerproAuthException($response['body'], $response['status']);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception('HTTP ' . $response['status'] . ' ' . $response['body']);
        }

        return json_decode($response['body'], true);
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
     * @param Exception $e
     * @return bool
     */
    private static function shouldRetryWithAlternateToken(Exception $e)
    {
        if ($e instanceof SerproAuthException) {
            return true;
        }

        $message = $e->getMessage();

        return strpos($message, '900908') !== false
            || strpos($message, '900901') !== false
            || strpos($message, '900902') !== false;
    }

    /**
     * @param int $status
     * @param string $body
     * @return bool
     */
    private static function isAuthFailureStatus($status, $body)
    {
        if ($status === 401 || $status === 403) {
            return true;
        }

        return strpos($body, '900908') !== false
            || strpos($body, '900901') !== false
            || strpos($body, '900902') !== false;
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
     * @return bool
     */
    private static function sslVerify()
    {
        $verify = config('services.serpro.ssl_verify');

        if ($verify === null || $verify === '') {
            return config('app.env') !== 'local';
        }

        return filter_var($verify, FILTER_VALIDATE_BOOLEAN);
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
