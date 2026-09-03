<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteRestricao;
use App\VaduApi;
use Illuminate\Support\Facades\Log;

/**
 * Consulta Vadu e persiste restricoes em cedente_restricao.
 *
 * Regra atual:
 * - Socio com QualificacaoRepresentanteLegal E NomeRepresentanteLegal preenchidos
 *
 * Com qualquer restricao: status vira cancelado e fica travado (nao pode mudar status).
 */
class CedenteVaduService
{
    /**
     * @return bool
     */
    public static function isEnabled()
    {
        $enabled = config('services.vadu.enabled', false);
        if (is_string($enabled)) {
            return ! in_array(strtolower($enabled), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $enabled;
    }

    /**
     * Cedente com ao menos uma restricao Vadu fica permanentemente travado.
     *
     * @param Cedente $cedente
     * @return bool
     */
    public static function isLockedByRestricao(Cedente $cedente)
    {
        if (! $cedente->id) {
            return false;
        }

        return CedenteRestricao::where('cedente_id', (int) $cedente->id)
            ->where('fonte', 'vadu')
            ->exists();
    }

    /**
     * Consulta Vadu, substitui restricoes (fonte=vadu) e cancela o cedente se houver restricao.
     *
     * @return array{
     *   consulted: bool,
     *   restricoes: CedenteRestricao[],
     *   error_message: string|null,
     *   payload: array|null,
     *   status_alterado: bool,
     *   status_anterior: string|null,
     *   status_novo: string|null
     * }
     */
    public static function syncRestricoesOnPendente(Cedente $cedente)
    {
        $empty = [
            'consulted' => false,
            'restricoes' => [],
            'error_message' => null,
            'payload' => null,
            'status_alterado' => false,
            'status_anterior' => null,
            'status_novo' => null,
        ];

        if (! self::isEnabled()) {
            return $empty;
        }

        $documento = isset($cedente->documento) ? $cedente->documento : '';

        try {
            $payload = VaduApi::consultarCnpj($documento);
        } catch (\Exception $e) {
            Log::warning('CedenteVaduService: falha na consulta Vadu', [
                'cedente_id' => $cedente->id,
                'documento' => $documento,
                'message' => $e->getMessage(),
            ]);

            $empty['error_message'] = $e->getMessage();

            return $empty;
        }

        $items = self::extractRestricoes($payload);

        CedenteRestricao::where('cedente_id', (int) $cedente->id)
            ->where('fonte', 'vadu')
            ->delete();

        $saved = [];
        foreach ($items as $item) {
            $saved[] = CedenteRestricao::create([
                'cedente_id' => (int) $cedente->id,
                'campo_restrito' => $item['campo_restrito'],
                'socio_indice' => $item['socio_indice'],
                'socio_nome' => $item['socio_nome'],
                'qualificacao_representante_legal' => $item['qualificacao_representante_legal'],
                'nome_representante_legal' => $item['nome_representante_legal'],
                'codigo' => $item['codigo'],
                'descricao' => $item['descricao'],
                'valor_vadu' => $item['valor_vadu'],
                'fonte' => 'vadu',
            ]);
        }

        $cedente->unsetRelation('restricoes');

        $statusAnterior = $cedente->status ?: Cedente::STATUS_PENDENTE;
        $statusAlterado = false;
        $statusNovo = null;

        if (! empty($saved) && $statusAnterior !== Cedente::STATUS_CANCELADO) {
            $cedente->status = Cedente::STATUS_CANCELADO;
            $cedente->save();
            $statusAlterado = true;
            $statusNovo = Cedente::STATUS_CANCELADO;
        }

        return [
            'consulted' => true,
            'restricoes' => $saved,
            'error_message' => null,
            'payload' => $payload,
            'status_alterado' => $statusAlterado,
            'status_anterior' => $statusAlterado ? $statusAnterior : null,
            'status_novo' => $statusNovo,
        ];
    }

    /**
     * @param array $payload
     * @return array<int, array>
     */
    public static function extractRestricoes(array $payload)
    {
        return self::extractRestricoesFromSocios($payload);
    }

    /**
     * Problema quando QualificacaoRepresentanteLegal e NomeRepresentanteLegal estao preenchidos.
     *
     * @param array $payload
     * @return array<int, array>
     */
    public static function extractRestricoesFromSocios(array $payload)
    {
        $socios = self::collectSocios($payload);

        $out = [];
        foreach ($socios as $index => $socio) {
            if (! is_array($socio)) {
                continue;
            }

            $qualificacao = self::stringOrNull($socio, [
                'QualificacaoRepresentanteLegal',
                'qualificacaoRepresentanteLegal',
                'qualificacao_representante_legal',
            ]);
            $nomeRep = self::stringOrNull($socio, [
                'NomeRepresentanteLegal',
                'nomeRepresentanteLegal',
                'nome_representante_legal',
            ]);

            if ($qualificacao === null || $nomeRep === null) {
                continue;
            }

            $socioNome = self::stringOrNull($socio, ['Nome', 'nome'], 'Socio #' . $index);
            $campo = 'socios[' . $index . '].QualificacaoRepresentanteLegal';

            $out[] = [
                'campo_restrito' => $campo,
                'socio_indice' => (int) $index,
                'socio_nome' => $socioNome,
                'qualificacao_representante_legal' => $qualificacao,
                'nome_representante_legal' => $nomeRep,
                'codigo' => 'REPRESENTANTE_LEGAL',
                'descricao' => 'Socio com representante legal informado na Vadu: ' . $socioNome,
                'valor_vadu' => json_encode($socio, JSON_UNESCAPED_UNICODE),
            ];
        }

        return $out;
    }

    /**
     * Localiza listas de socios no payload (raiz ou aninhado: Consulta/Dados/etc.).
     *
     * @param array $payload
     * @return array<int, array>
     */
    private static function collectSocios(array $payload)
    {
        $found = [];
        self::walkForSocios($payload, $found);

        return $found;
    }

    /**
     * @param mixed $node
     * @param array<int, array> $found
     */
    private static function walkForSocios($node, array &$found)
    {
        if (! is_array($node)) {
            return;
        }

        foreach (['Socios', 'socios'] as $key) {
            if (! isset($node[$key]) || ! is_array($node[$key])) {
                continue;
            }

            $list = $node[$key];
            $looksLikeSocio = isset($list['Nome'])
                || isset($list['nome'])
                || isset($list['QualificacaoRepresentanteLegal'])
                || isset($list['NomeRepresentanteLegal']);

            if ($looksLikeSocio) {
                $found[] = $list;
            } else {
                foreach ($list as $item) {
                    if (is_array($item)) {
                        $found[] = $item;
                    }
                }
            }
        }

        foreach ($node as $key => $child) {
            if ($key === 'Socios' || $key === 'socios') {
                continue;
            }
            if (is_array($child)) {
                self::walkForSocios($child, $found);
            }
        }
    }

    /**
     * @param array $row
     * @param string[] $keys
     * @param string|null $default
     * @return string|null
     */
    private static function stringOrNull(array $row, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }
            $text = trim((string) $row[$key]);
            if ($text !== '') {
                return $text;
            }
        }

        return $default;
    }
}
