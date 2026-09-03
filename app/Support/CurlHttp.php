<?php

namespace App\Support;

use Exception;

/**
 * HTTP via cURL (extensao PHP ou binario CLI).
 *
 * Sem a extensao php-curl o Guzzle cai no StreamHandler (fopen) e HTTPS
 * falha com "SSL context creation failure". Este cliente evita isso.
 */
class CurlHttp
{
    /**
     * @param string $method
     * @param string $url
     * @param array $options headers, body, form_params, auth [user, pass], verify, timeout
     * @return array{status:int,body:string}
     */
    public static function request($method, $url, array $options = [])
    {
        $method = strtoupper((string) $method);
        $headers = isset($options['headers']) && is_array($options['headers'])
            ? $options['headers']
            : [];
        $verify = array_key_exists('verify', $options) ? (bool) $options['verify'] : true;
        $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 60;
        $auth = isset($options['auth']) && is_array($options['auth']) ? $options['auth'] : null;

        $body = null;
        if (isset($options['form_params']) && is_array($options['form_params'])) {
            $body = http_build_query($options['form_params']);
            if (! self::hasHeader($headers, 'Content-Type')) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            }
        } elseif (array_key_exists('body', $options) && $options['body'] !== null) {
            $body = (string) $options['body'];
        }

        if (function_exists('curl_init')) {
            return self::viaPhpCurl($method, $url, $headers, $body, $auth, $verify, $timeout);
        }

        return self::viaCurlCli($method, $url, $headers, $body, $auth, $verify, $timeout);
    }

    /**
     * @param array $headers
     * @param string $name
     * @return bool
     */
    private static function hasHeader(array $headers, $name)
    {
        $name = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status:int,body:string}
     */
    private static function viaPhpCurl($method, $url, array $headers, $body, $auth, $verify, $timeout)
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new Exception('Falha ao iniciar cURL (curl_init).');
        }

        $headerLines = [];
        foreach ($headers as $key => $value) {
            $headerLines[] = $key . ': ' . $value;
        }

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];

        if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        if ($auth !== null && isset($auth[0], $auth[1])) {
            $opts[CURLOPT_USERPWD] = $auth[0] . ':' . $auth[1];
            $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }

        curl_setopt_array($ch, $opts);

        $responseBody = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            throw new Exception('Erro cURL ao chamar ' . $url . ': [' . $errno . '] ' . $error);
        }

        return [
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }

    /**
     * Fallback: binario `curl` no PATH (pacote apt), quando php-curl nao existe.
     *
     * @return array{status:int,body:string}
     */
    private static function viaCurlCli($method, $url, array $headers, $body, $auth, $verify, $timeout)
    {
        $bin = trim((string) shell_exec('command -v curl 2>/dev/null'));
        if ($bin === '') {
            throw new Exception(
                'Extensao PHP curl ausente e binario curl nao encontrado. '
                . 'Instale php-curl (docker-php-ext-install curl) para HTTPS funcionar.'
            );
        }

        $cmd = [
            escapeshellarg($bin),
            '-sS',
            '-X', escapeshellarg($method),
            '--max-time', escapeshellarg((string) $timeout),
            '-w', escapeshellarg("\n__HTTP_STATUS__:%{http_code}"),
        ];

        if (! $verify) {
            $cmd[] = '-k';
        }

        foreach ($headers as $key => $value) {
            $cmd[] = '-H';
            $cmd[] = escapeshellarg($key . ': ' . $value);
        }

        if ($auth !== null && isset($auth[0], $auth[1])) {
            $cmd[] = '-u';
            $cmd[] = escapeshellarg($auth[0] . ':' . $auth[1]);
        }

        if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
            $cmd[] = '--data-binary';
            $cmd[] = escapeshellarg($body);
        }

        $cmd[] = escapeshellarg($url);

        $output = [];
        $exitCode = 0;
        exec(implode(' ', $cmd) . ' 2>&1', $output, $exitCode);
        $raw = implode("\n", $output);

        if ($exitCode !== 0) {
            throw new Exception('Erro curl CLI ao chamar ' . $url . ': ' . $raw);
        }

        if (! preg_match('/\n__HTTP_STATUS__:(\d+)\s*$/', $raw, $matches)) {
            throw new Exception('Resposta curl CLI sem status HTTP: ' . $raw);
        }

        $status = (int) $matches[1];
        $responseBody = preg_replace('/\n__HTTP_STATUS__:\d+\s*$/', '', $raw);

        return [
            'status' => $status,
            'body' => (string) $responseBody,
        ];
    }
}
