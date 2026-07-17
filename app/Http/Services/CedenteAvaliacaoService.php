<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteAudit;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CedenteAvaliacaoService
{
    /**
     * Registra avaliação do analista (aprovar, solicitar correções ou rejeitar).
     *
     * @param array $data fund_id, id (cedente), resultado, observacao, limite_aprovado, prazo_atualizacao_cadastral
     * @return Cedente
     */
    public static function registrar(array $data)
    {
        $data = CedenteService::normalizePayload($data);
        CedentePermissionService::assertCanAvaliar();

        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID do cedente e obrigatorio');
        }

        if (empty($data['resultado'])) {
            throw new InvalidArgumentException('Campo resultado e obrigatorio (aprovado, solicitar_correcoes ou rejeitado)');
        }

        $resultado = is_string($data['resultado']) ? trim($data['resultado']) : trim((string) $data['resultado']);
        if (! in_array($resultado, Cedente::avaliacaoResultadoValues(), true)) {
            throw new InvalidArgumentException('Resultado invalido. Use aprovado, solicitar_correcoes ou rejeitado');
        }

        $fundId = CedenteService::resolveFundId($data);

        return DB::transaction(function () use ($data, $fundId, $resultado) {
            $cedente = CedenteService::findCedenteForFund($data['id'], $fundId);
            $statusAntes = $cedente->status ?: Cedente::STATUS_PENDENTE;

            if ($resultado === Cedente::AVALIACAO_APROVADO) {
                return self::aprovar($cedente, $data, $statusAntes);
            }

            return self::registrarComObservacaoObrigatoria($cedente, $data, $resultado, $statusAntes);
        });
    }

    /**
     * @param Cedente $cedente
     * @param array $data
     * @param string $statusAntes
     * @return Cedente
     */
    private static function aprovar(Cedente $cedente, array $data, $statusAntes)
    {
        if (! array_key_exists('limite_aprovado', $data) || $data['limite_aprovado'] === null || $data['limite_aprovado'] === '') {
            throw new InvalidArgumentException('limite_aprovado e obrigatorio para aprovacao');
        }

        if (! array_key_exists('prazo_atualizacao_cadastral', $data) || $data['prazo_atualizacao_cadastral'] === null || $data['prazo_atualizacao_cadastral'] === '') {
            throw new InvalidArgumentException('prazo_atualizacao_cadastral e obrigatorio para aprovacao (meses)');
        }

        $limite = self::normalizeLimiteAprovado($data['limite_aprovado']);
        $meses = self::normalizePrazoMeses($data['prazo_atualizacao_cadastral']);
        $observacao = self::normalizeObservacao(isset($data['observacao']) ? $data['observacao'] : null, false);

        $cedente->limite_aprovado = $limite;
        $cedente->prazo_atualizacao_cadastral = $meses;
        $cedente->observacao = $observacao;
        $cedente->status = Cedente::STATUS_APROVADO;
        $cedente->sla = Cedente::computeSlaDeadlineFromMonths($meses, date('Y-m-d'));
        $cedente->save();

        self::recordAvaliacaoAudit(
            $cedente,
            Cedente::STATUS_APROVADO,
            $statusAntes,
            [
                'descricao' => 'Cedente aprovado pelo analista',
                'resultado' => Cedente::AVALIACAO_APROVADO,
                'limite_aprovado' => $limite,
                'prazo_atualizacao_cadastral' => $meses,
                'sla' => $cedente->sla ? $cedente->sla->format('Y-m-d') : null,
                'observacao' => $observacao,
            ]
        );

        return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
    }

    /**
     * @param Cedente $cedente
     * @param array $data
     * @param string $resultado
     * @param string $statusAntes
     * @return Cedente
     */
    private static function registrarComObservacaoObrigatoria(Cedente $cedente, array $data, $resultado, $statusAntes)
    {
        $observacao = self::normalizeObservacao(isset($data['observacao']) ? $data['observacao'] : null, true);

        // "solicitar_correcoes" continua sendo o resultado da avaliacao,
        // mas no workflow o cedente volta para a coluna de inconsistentes.
        $novoStatus = $resultado === Cedente::AVALIACAO_REJEITADO
            ? Cedente::STATUS_REJEITADO
            : Cedente::STATUS_INCONSISTENTE;

        $cedente->observacao = $observacao;
        $cedente->limite_aprovado = null;
        $cedente->prazo_atualizacao_cadastral = null;
        $cedente->status = $novoStatus;
        $cedente->save();

        $descricao = $resultado === Cedente::AVALIACAO_REJEITADO
            ? 'Cedente rejeitado pelo analista'
            : 'Analista solicitou correcoes no cadastro';

        self::recordAvaliacaoAudit(
            $cedente,
            $novoStatus,
            $statusAntes,
            [
                'descricao' => $descricao,
                'resultado' => $resultado,
                'observacao' => $observacao,
            ]
        );

        return $cedente->fresh(['address', 'pessoasVinculadas.address', 'contasDesembolso', 'cedenteFiles', 'inconsistencias']);
    }

    /**
     * @param mixed $raw
     * @param bool $required
     * @return string|null
     */
    private static function normalizeObservacao($raw, $required = false)
    {
        if ($raw === null || $raw === '') {
            if ($required) {
                throw new InvalidArgumentException('observacao e obrigatoria para este resultado da avaliacao');
            }

            return null;
        }

        $text = is_string($raw) ? trim($raw) : trim((string) $raw);
        if ($text === '') {
            if ($required) {
                throw new InvalidArgumentException('observacao e obrigatoria para este resultado da avaliacao');
            }

            return null;
        }

        return $text;
    }

    /**
     * @param mixed $raw
     * @return string
     */
    private static function normalizeLimiteAprovado($raw)
    {
        if ($raw === null || $raw === '') {
            throw new InvalidArgumentException('limite_aprovado invalido');
        }

        $normalized = str_replace(['.', ' '], '', (string) $raw);
        $normalized = str_replace(',', '.', $normalized);

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException('limite_aprovado deve ser numerico (ate 12 digitos antes da virgula e 2 apos)');
        }

        if (! preg_match('/^\d{1,12}(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('limite_aprovado deve ter no maximo 12 digitos antes da virgula e 2 apos');
        }

        $parts = explode('.', $normalized);
        $intPart = $parts[0];
        if (strlen($intPart) > 12) {
            throw new InvalidArgumentException('limite_aprovado deve ter no maximo 12 digitos antes da virgula');
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    /**
     * @param mixed $raw
     * @return int
     */
    private static function normalizePrazoMeses($raw)
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            throw new InvalidArgumentException('prazo_atualizacao_cadastral deve ser um numero inteiro de meses (minimo 1)');
        }

        $meses = (int) $raw;
        if ($meses < 1) {
            throw new InvalidArgumentException('prazo_atualizacao_cadastral deve ser pelo menos 1 mes');
        }

        if ($meses > 600) {
            throw new InvalidArgumentException('prazo_atualizacao_cadastral excede o limite permitido');
        }

        return $meses;
    }

    /**
     * @param Cedente $cedente
     * @param string $novoStatus
     * @param string $statusAntes
     * @param array $changes
     */
    private static function recordAvaliacaoAudit(Cedente $cedente, $novoStatus, $statusAntes, array $changes)
    {
        $user = User::logged();

        CedenteAudit::create([
            'cedente_id' => $cedente->id,
            'user_id' => $user ? (int) $user->id : null,
            'event' => CedenteAudit::EVENT_AVALIACAO_REGISTRADA,
            'old_status' => $statusAntes,
            'new_status' => $novoStatus,
            'changes' => $changes,
        ]);

        if ($novoStatus !== $statusAntes) {
            CedenteAudit::create([
                'cedente_id' => $cedente->id,
                'user_id' => $user ? (int) $user->id : null,
                'event' => CedenteAudit::EVENT_STATUS_ALTERADO,
                'old_status' => $statusAntes,
                'new_status' => $novoStatus,
                'changes' => [
                    'descricao' => 'Status alterado de ' . $statusAntes . ' para ' . $novoStatus,
                    'motivo' => 'avaliacao_analista',
                ],
            ]);
        }
    }
}
