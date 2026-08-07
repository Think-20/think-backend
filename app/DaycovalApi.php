<?php

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Cliente SOAP do Portal Servicos (Fromtis/Daycoval).
 * Servico focado: cadastroCedenteAprovado.
 */
class DaycovalApi
{
    const NS = 'http://soap.consulta.servicos.portal.fidc.fromtis.com.br/';

    /**
     * Envia cadastro de cedente ja aprovado (sem fluxo de aprovacao no custodiante).
     *
     * @param string $cedenteXmlFragment XML interno do(s) <cedente>...</cedente>
     * @return array{success: bool, tipo_retorno: string|null, descricao: string|null, erros: array, raw: string}
     */
    public static function cadastroCedenteAprovado($cedenteXmlFragment)
    {
        $body = '<soap:cadastroCedenteAprovado>'
            . '<cadastroCedentes>'
            . $cedenteXmlFragment
            . '</cadastroCedentes>'
            . '</soap:cadastroCedenteAprovado>';

        return self::request(self::endpointCadastroCedenteAprovado(), $body);
    }

    /**
     * @param string $url
     * @param string $bodyInnerXml
     * @return array
     */
    private static function request($url, $bodyInnerXml)
    {
        $username = config('services.daycoval.username');
        $password = config('services.daycoval.password');

        if (! is_string($username) || $username === '' || ! is_string($password) || $password === '') {
            throw new Exception('Credenciais Daycoval nao configuradas (DAYCOVAL_USERNAME / DAYCOVAL_PASSWORD).');
        }

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:soap="' . self::NS . '">'
            . '<soapenv:Header/>'
            . '<soapenv:Body>'
            . $bodyInnerXml
            . '</soapenv:Body>'
            . '</soapenv:Envelope>';

        $client = new Client();

        try {
            $response = $client->request('POST', $url, array_merge(self::requestOptions(), [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '',
                    'username' => $username,
                    'password' => $password,
                ],
                'body' => $envelope,
            ]));

            $raw = (string) $response->getBody();

            return self::parseCadastroResponse($raw);
        } catch (RequestException $e) {
            $raw = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            Log::warning('DaycovalApi: falha HTTP no SOAP', [
                'url' => $url,
                'message' => $e->getMessage(),
                'body' => $raw !== '' ? mb_substr($raw, 0, 2000) : null,
            ]);

            throw new Exception('Erro ao chamar Daycoval: ' . self::requestExceptionMessage($e));
        }
    }

    /**
     * @param string $raw
     * @return array{success: bool, tipo_retorno: string|null, descricao: string|null, erros: array, raw: string, nome_cedente: string|null, cnpj_cedente: string|null}
     */
    private static function parseCadastroResponse($raw)
    {
        $tipo = self::xpathText($raw, 'tipoRetorno');
        $descricao = self::xpathText($raw, 'descricaoRetorno');
        $erros = self::xpathAllTexts($raw, 'descricao');

        // Em errosValidacao a tag pode ser Descricao/descricao; evita pegar descricaoRetorno de novo.
        $erros = array_values(array_filter($erros, function ($item) use ($descricao) {
            return $item !== '' && $item !== $descricao;
        }));

        $success = is_string($tipo) && strtoupper($tipo) === 'SUCESSO';

        return [
            'success' => $success,
            'tipo_retorno' => $tipo,
            'descricao' => $descricao,
            'erros' => $erros,
            'nome_cedente' => self::xpathText($raw, 'nomeCedente'),
            'cnpj_cedente' => self::xpathText($raw, 'cnpjCedente'),
            'raw' => $raw,
        ];
    }

    /**
     * @param string $xml
     * @param string $localName
     * @return string|null
     */
    private static function xpathText($xml, $localName)
    {
        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            if (preg_match('/<' . preg_quote($localName, '/') . '[^>]*>([^<]*)</i', $xml, $m)) {
                return trim(html_entity_decode($m[1], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            }

            return null;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name()="' . $localName . '"]');
        if (! $nodes || $nodes->length < 1) {
            return null;
        }

        return trim((string) $nodes->item(0)->textContent);
    }

    /**
     * @param string $xml
     * @param string $localName
     * @return string[]
     */
    private static function xpathAllTexts($xml, $localName)
    {
        if (! is_string($xml) || $xml === '') {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*[local-name()="errosValidacao"]//*[local-name()="' . $localName . '"]'
            . '|//*[local-name()="errosValidacao"]//*[local-name()="Descricao"]');

        $out = [];
        if ($nodes) {
            foreach ($nodes as $node) {
                $text = trim((string) $node->textContent);
                if ($text !== '') {
                    $out[] = $text;
                }
            }
        }

        return $out;
    }

    /**
     * @return string
     */
    private static function endpointCadastroCedenteAprovado()
    {
        $base = config('services.daycoval.base_url');
        if (! is_string($base) || $base === '') {
            throw new Exception('DAYCOVAL_BASE_URL nao configurada.');
        }

        return rtrim($base, '/') . '/servicos/soap/cadastroCedenteAprovado';
    }

    /**
     * @return array
     */
    private static function requestOptions()
    {
        $verify = config('services.daycoval.ssl_verify', true);
        if (is_string($verify)) {
            $verify = ! in_array(strtolower($verify), ['false', '0', 'no', 'off'], true);
        }

        return [
            'http_errors' => true,
            'verify' => (bool) $verify,
            'timeout' => (int) config('services.daycoval.timeout', 60),
        ];
    }

    /**
     * @param RequestException $e
     * @return string
     */
    private static function requestExceptionMessage(RequestException $e)
    {
        if ($e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
            $snippet = mb_substr(trim(strip_tags($body)), 0, 300);

            return $e->getMessage() . ($snippet !== '' ? ' | ' . $snippet : '');
        }

        return $e->getMessage();
    }
}
