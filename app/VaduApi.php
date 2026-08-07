<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cliente Vadu/CreditBox.
 *
 * Fluxo (igual SERPRO):
 * 1) GET JSONPegarToken com Bearer master (VADU_TOKEN) → token temporario em "token"
 * 2) POST ServicoAnaliseOperacao/Consulta/{cnpj} com Bearer do token temporario
 * 3) Em 401/403, renova o token temporario e tenta de novo
 */
class VaduApi
{
    const CACHE_KEY = 'vadu_access_token';

    const DEFAULT_TOKEN_URL = 'https://www.creditbox.com.br/CreditBox.dll/Autenticacao/JSONPegarToken';
    const DEFAULT_CONSULTA_BASE = 'https://www.vadu.com.br/vadu.dll/ServicoAnaliseOperacao/Consulta';

    /**
     * Consulta CNPJ/CPF na Vadu e devolve o payload bruto.
     *
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
        } catch (RequestException $e) {
            if (! self::shouldRefreshToken($e)) {
                throw new Exception('Erro ao consultar Vadu: ' . self::requestExceptionMessage($e));
            }

            $token = self::refreshAccessToken();

            try {
                return self::requestConsulta($documento, $token);
            } catch (RequestException $retry) {
                throw new Exception('Erro ao consultar Vadu: ' . self::requestExceptionMessage($retry));
            }
        }
    }

    /**
     * Alias usado pelo CedenteVaduService.
     *
     * @param string $documento
     * @return array
     */
    public static function consultarRestricoes($documento)
    {
        return self::consultarCnpj($documento);
    }

    /**
     * GET CreditBox JSONPegarToken com Bearer master → grava token temporario.
     *
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

        $client = new Client();
        $url = self::tokenUrl();

        try {
            $response = $client->request('GET', $url, array_merge(self::requestOptions(), [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $master,
                ],
            ]));

            $data = json_decode((string) $response->getBody(), true);
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
        } catch (RequestException $e) {
            throw new Exception('Erro ao obter token temporario Vadu: ' . self::requestExceptionMessage($e));
        }
    }

    /**
     * @param string $documento
     * @param string $token
     * @return array
     */
    private static function requestConsulta($documento, $token)
    {
        $client = new Client();
        $url = rtrim(self::consultaBaseUrl(), '/') . '/' . $documento;

        $response = $client->request('POST', $url, array_merge(self::requestOptions(), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]));

        $data = json_decode((string) $response->getBody(), true);
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
     * @return array
     */
    private static function requestOptions()
    {
        $verify = config('services.vadu.ssl_verify', true);
        if (is_string($verify)) {
            $verify = ! in_array(strtolower($verify), ['false', '0', 'no', 'off'], true);
        }

        return [
            'http_errors' => true,
            'verify' => (bool) $verify,
            'timeout' => (int) config('services.vadu.timeout', 60),
        ];
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

        $code = $e->getResponse()->getStatusCode();

        return $code === 401 || $code === 403;
    }

    /**
     * @param RequestException $e
     * @return string
     */
    private static function requestExceptionMessage(RequestException $e)
    {
        if ($e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
            $snippet = mb_substr(trim(strip_tags($body)), 0, 400);

            return $e->getMessage() . ($snippet !== '' ? ' | ' . $snippet : '');
        }

        return $e->getMessage();
    }
}
