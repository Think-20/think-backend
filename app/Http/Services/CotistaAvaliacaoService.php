<?php

namespace App\Http\Services;

use App\Cotista;
use App\CotistaAudit;
use App\CotistaInconsistencia;
use App\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CotistaAvaliacaoService
{
    /**
     * @param array $data id, resultado, observacao, prazo_atualizacao_cadastral
     * @return Cotista
     */
    public static function registrar(array $data)
    {
        $data = CotistaService::normalizePayload($data);

        if (empty($data['id'])) {
            throw new InvalidArgumentException('ID do cotista e obrigatorio');
        }

        if (empty($data['resultado'])) {
            throw new InvalidArgumentException(
                'Campo resultado e obrigatorio (aprovado, solicitar_correcoes ou rejeitado)'
            );
        }

        $resultado = self::normalizeResultado($data['resultado']);
        if ($resultado === null || ! in_array($resultado, Cotista::avaliacaoResultadoValues(), true)) {
            throw new InvalidArgumentException(
                'Resultado invalido. Use aprovado, solicitar_correcoes ou rejeitado'
            );
        }

        return DB::transaction(function () use ($data, $resultado) {
            $cotista = CotistaService::findCotista($data['id']);
            CotistaPermissionService::assertCanAvaliar($cotista);
            $statusAntes = $cotista->status ?: Cotista::STATUS_PENDENTE;

            if ($resultado === Cotista::AVALIACAO_APROVADO) {
                return self::aprovar($cotista, $data, $statusAntes);
            }

            return self::registrarComObservacao($cotista, $data, $resultado, $statusAntes);
        });
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     * @param string $statusAntes
     * @return Cotista
     */
    private static function aprovar(Cotista $cotista, array $data, $statusAntes)
    {
        if (! array_key_exists('prazo_atualizacao_cadastral', $data) || $data['prazo_atualizacao_cadastral'] === null || $data['prazo_atualizacao_cadastral'] === '') {
            throw new InvalidArgumentException('prazo_atualizacao_cadastral e obrigatorio para aprovacao (meses)');
        }

        $meses = self::normalizePrazoMeses($data['prazo_atualizacao_cadastral']);
        $observacao = self::normalizeObservacao(isset($data['observacao']) ? $data['observacao'] : null, false);

        $cotista->prazo_atualizacao_cadastral = $meses;
        $cotista->status = Cotista::STATUS_APROVADO;
        $cotista->sla = Cotista::computeSlaDeadlineFromMonths($meses, date('Y-m-d'));
        $cotista->save();

        self::recordAvaliacaoAudit(
            $cotista,
            Cotista::STATUS_APROVADO,
            $statusAntes,
            [
                'descricao' => 'Cotista aprovado pelo aprovador',
                'resultado' => Cotista::AVALIACAO_APROVADO,
                'prazo_atualizacao_cadastral' => $meses,
                'sla' => $cotista->sla instanceof \DateTimeInterface ? $cotista->sla->format('Y-m-d') : $cotista->sla,
                'observacao' => $observacao,
            ]
        );

        CotistaMailService::notifyAprovado($cotista, [
            'observacao' => $observacao,
        ]);

        return $cotista->fresh(Cotista::apiRelations());
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     * @param string $resultado
     * @param string $statusAntes
     * @return Cotista
     */
    private static function registrarComObservacao(Cotista $cotista, array $data, $resultado, $statusAntes)
    {
        $exigeObservacao = $resultado === Cotista::AVALIACAO_SOLICITAR_CORRECOES;
        $observacao = self::normalizeObservacao(isset($data['observacao']) ? $data['observacao'] : null, $exigeObservacao);

        $novoStatus = $resultado === Cotista::AVALIACAO_REJEITADO
            ? Cotista::STATUS_REJEITADO
            : Cotista::STATUS_INCONSISTENTE;

        $cotista->prazo_atualizacao_cadastral = null;
        $cotista->status = $novoStatus;
        $cotista->save();

        if ($resultado === Cotista::AVALIACAO_SOLICITAR_CORRECOES) {
            CotistaInconsistencia::create([
                'cotista_id' => (int) $cotista->id,
                'campo_inconsistente' => CotistaInconsistencia::CAMPO_APROVADOR,
                'valor_serpro' => $observacao,
            ]);
            $cotista->unsetRelation('inconsistencias');
        }

        $descricao = $resultado === Cotista::AVALIACAO_REJEITADO
            ? 'Cotista rejeitado pelo aprovador'
            : 'Aprovador solicitou correcoes no cadastro (inconsistente)';

        self::recordAvaliacaoAudit(
            $cotista,
            $novoStatus,
            $statusAntes,
            [
                'descricao' => $descricao,
                'resultado' => $resultado,
                'observacao' => $observacao,
            ]
        );

        if ($resultado === Cotista::AVALIACAO_SOLICITAR_CORRECOES) {
            CotistaMailService::notifyPendencias($cotista, [
                'observacao' => $observacao,
            ]);
        }

        return $cotista->fresh(Cotista::apiRelations());
    }

    /**
     * @param mixed $raw
     * @return string|null
     */
    private static function normalizeResultado($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        $s = strtolower($s);
        $s = str_replace([' ', '-'], '_', $s);

        $aliases = [
            'recusado' => Cotista::AVALIACAO_REJEITADO,
            'rejeitado' => Cotista::AVALIACAO_REJEITADO,
            'solicitar_correcoes' => Cotista::AVALIACAO_SOLICITAR_CORRECOES,
            'inconsistente' => Cotista::AVALIACAO_SOLICITAR_CORRECOES,
            'inconsistencia_cadastral' => Cotista::AVALIACAO_SOLICITAR_CORRECOES,
            'aprovado' => Cotista::AVALIACAO_APROVADO,
        ];

        return isset($aliases[$s]) ? $aliases[$s] : $s;
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
     * @param Cotista $cotista
     * @param string $novoStatus
     * @param string $statusAntes
     * @param array $changes
     */
    private static function recordAvaliacaoAudit(Cotista $cotista, $novoStatus, $statusAntes, array $changes)
    {
        $user = User::logged();

        CotistaAudit::create([
            'cotista_id' => $cotista->id,
            'user_id' => $user ? (int) $user->id : null,
            'event' => CotistaAudit::EVENT_AVALIACAO_REGISTRADA,
            'old_status' => $statusAntes,
            'new_status' => $novoStatus,
            'changes' => $changes,
        ]);

        if ($novoStatus !== $statusAntes) {
            CotistaAudit::create([
                'cotista_id' => $cotista->id,
                'user_id' => $user ? (int) $user->id : null,
                'event' => CotistaAudit::EVENT_STATUS_ALTERADO,
                'old_status' => $statusAntes,
                'new_status' => $novoStatus,
                'changes' => [
                    'descricao' => 'Status alterado de ' . Cotista::statusLabel($statusAntes) . ' para ' . Cotista::statusLabel($novoStatus),
                    'motivo' => 'avaliacao_aprovador',
                ],
            ]);
        }
    }
}
