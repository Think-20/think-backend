<?php

namespace App;

use App\Support\CurlHttp;
use Exception;
use Illuminate\Support\Facades\Cache;

/**
 * Cliente Vadu/CreditBox.
 *
 * 1) GET JSONPegarToken com Bearer master (VADU_TOKEN) → token temporario
 * 2) POST Consulta/{cnpj} com Bearer do token temporario
 * 3) Em 401/403, renova o token e tenta de novo
 */
class VaduApi
{
    const CACHE_KEY = 'vadu_access_token';

    const DEFAULT_TOKEN_URL = 'https://www.creditbox.com.br/CreditBox.dll/Autenticacao/JSONPegarToken';
    const DEFAULT_CONSULTA_BASE = 'https://www.vadu.com.br/vadu.dll/ServicoAnaliseOperacao/Consulta';

    /**
     * @param string $documento
     * @return array
     */
    public static function consultarCnpj($documento)
    {
        $documento = preg_replace('/\D+/', '', (string) $documento);
        if ($documento === '' || (strlen($documento) !== 11 && strlen($documento) !== 14)) {
            throw new Exception('Documento invalido para consulta Vadu.');
        }

        if (! self::isConfigured()) {
            throw new Exception('VADU_TOKEN (token master) nao configurado.');
        }

        $token = self::resolveAccessToken();

        try {
            return self::requestConsulta($documento, $token);
        } catch (VaduAuthException $e) {
            $token = self::refreshAccessToken();

            try {
                return self::requestConsulta($documento, $token);
            } catch (Exception $retry) {
                throw new Exception('Erro ao consultar Vadu: ' . $retry->getMessage());
            }
        } catch (Exception $e) {
            throw new Exception('Erro ao consultar Vadu: ' . $e->getMessage());
        }
    }

    /**
     * @param string $documento
     * @return array
     */
    public static function consultarRestricoes($documento)
    {
        return self::consultarCnpj($documento);
    }

    /**
     * @return string
     */
    public static function pegarToken()
    {
        return self::refreshAccessToken();
    }

    /**
     * @return bool
     */
    public static function isConfigured()
    {
        $master = config('services.vadu.token');

        return is_string($master) && trim($master) !== '';
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

        $master = trim((string) config('services.vadu.token'));
        if ($master === '') {
            throw new Exception('VADU_TOKEN (token master) nao configurado.');
        }

        $response = CurlHttp::request('GET', self::tokenUrl(), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $master,
            ],
            'verify' => self::sslVerify(),
            'timeout' => (int) config('services.vadu.timeout', 60),
        ]);

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception(
                'Erro ao obter token temporario Vadu: HTTP ' . $response['status'] . ' ' . self::bodySnippet($response['body'])
            );
        }

        $data = json_decode($response['body'], true);
        if (! is_array($data)) {
            throw new Exception('Resposta invalida ao obter token Vadu (JSON esperado).');
        }

        $token = null;
        if (! empty($data['token']) && is_scalar($data['token'])) {
            $token = trim((string) $data['token']);
        } elseif (! empty($data['Token']) && is_scalar($data['Token'])) {
            $token = trim((string) $data['Token']);
        }

        if ($token === null || $token === '') {
            throw new Exception('Resposta Vadu sem campo token.');
        }

        $ttl = max(1, (int) config('services.vadu.token_ttl_minutes', 50));
        Cache::put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * @param string $documento
     * @param string $token
     * @return array
     */
    private static function requestConsulta($documento, $token)
    {
        $url = rtrim(self::consultaBaseUrl(), '/') . '/' . $documento;

        $response = CurlHttp::request('POST', $url, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'verify' => self::sslVerify(),
            'timeout' => (int) config('services.vadu.timeout', 60),
        ]);

        if ($response['status'] === 401 || $response['status'] === 403) {
            throw new VaduAuthException($response['body'], $response['status']);
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new Exception('HTTP ' . $response['status'] . ' ' . self::bodySnippet($response['body']));
        }

        $data = json_decode($response['body'], true);
        if (! is_array($data)) {
            throw new Exception('Resposta Vadu invalida na consulta CNPJ (JSON esperado).');
        }

        return $data;
    }

    /**
     * @return string
     */
    private static function tokenUrl()
    {
        $url = config('services.vadu.token_url', self::DEFAULT_TOKEN_URL);

        return is_string($url) && $url !== '' ? $url : self::DEFAULT_TOKEN_URL;
    }

    /**
     * @return string
     */
    private static function consultaBaseUrl()
    {
        $url = config('services.vadu.consulta_url', self::DEFAULT_CONSULTA_BASE);

        return is_string($url) && $url !== '' ? $url : self::DEFAULT_CONSULTA_BASE;
    }

    /**
     * @return bool
     */
    private static function sslVerify()
    {
        $verify = config('services.vadu.ssl_verify', true);
        if (is_string($verify)) {
            return ! in_array(strtolower($verify), ['false', '0', 'no', 'off'], true);
        }

        return (bool) $verify;
    }

    /**
     * @param string $body
     * @return string
     */
    private static function bodySnippet($body)
    {
        $snippet = trim(strip_tags((string) $body));
        if (function_exists('mb_substr')) {
            return mb_substr($snippet, 0, 400);
        }

        return substr($snippet, 0, 400);
    }
}
