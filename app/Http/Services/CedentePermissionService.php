<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteRole;
use App\User;
use InvalidArgumentException;

/**
 * Regras de acesso por papel de cedente (cedente_role_employee).
 * Prioridade atual: papel preenchimento.
 * Avalista/administrador ainda nao tem restricoes adicionais aqui.
 */
class CedentePermissionService
{
    const MSG_SEM_PERMISSAO = 'Voce nao tem permissao para realizar esta acao';

    /**
     * @return int|null
     */
    public static function currentEmployeeId()
    {
        $logged = User::logged();
        if (!$logged || !$logged->employee) {
            return null;
        }

        return (int) $logged->employee->id;
    }

    /**
     * @return string|null preenchimento|avalista|administrador|null
     */
    public static function currentRoleCode()
    {
        return CedenteRole::codeForEmployee(self::currentEmployeeId());
    }

    /**
     * @return bool
     */
    public static function isPreenchimento()
    {
        return self::currentRoleCode() === CedenteRole::CODE_PREENCHIMENTO;
    }

    /**
     * POST /cedente/save — preenchimento pode criar (fica rascunho ou pendente pela regra de negocio).
     */
    public static function assertCanCreate()
    {
        // Sem papel / avalista / admin: sem bloqueio nesta fase.
        // Preenchimento: permitido.
        if (self::isPreenchimento()) {
            return;
        }
    }

    /**
     * PUT /cedente/edit e PATCH /cedente/patch.
     *
     * Preenchimento so edita rascunho ou inconsistente.
     * Status solicitado (se enviado) so pode ser rascunho, pendente ou inconsistente.
     *
     * @param Cedente $cedente
     * @param array $data
     */
    public static function assertCanUpdate(Cedente $cedente, array $data)
    {
        if (!self::isPreenchimento()) {
            return;
        }

        $statusAtual = $cedente->status ?: Cedente::STATUS_PENDENTE;
        $editaveis = [
            Cedente::STATUS_RASCUNHO,
            Cedente::STATUS_INCONSISTENTE,
        ];

        if (!in_array($statusAtual, $editaveis, true)) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento so pode alterar cedentes em rascunho ou inconsistente (status atual: ' . $statusAtual . ')'
            );
        }

        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $requested = self::normalizeStatus($data['status']);
            $permitidos = [
                Cedente::STATUS_RASCUNHO,
                Cedente::STATUS_PENDENTE,
                Cedente::STATUS_INCONSISTENTE,
            ];

            if ($requested === null || !in_array($requested, $permitidos, true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento so pode enviar status rascunho, pendente ou inconsistente'
                );
            }

            // De rascunho so pode permanecer rascunho ou ir para pendente.
            if ($statusAtual === Cedente::STATUS_RASCUNHO
                && !in_array($requested, [Cedente::STATUS_RASCUNHO, Cedente::STATUS_PENDENTE], true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Em rascunho so e permitido manter rascunho ou enviar pendente'
                );
            }

            // De inconsistente: preferivel pendente; inconsistente tambem permitido se ainda houver pendencias.
            if ($statusAtual === Cedente::STATUS_INCONSISTENTE
                && !in_array($requested, [Cedente::STATUS_PENDENTE, Cedente::STATUS_INCONSISTENTE], true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Em inconsistente so e permitido enviar pendente ou inconsistente'
                );
            }
        }
    }

    /**
     * DELETE /cedente/remove/{id}
     */
    public static function assertCanDelete()
    {
        if (self::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode excluir cedentes');
        }
    }

    /**
     * PATCH /cedente/avaliacao
     */
    public static function assertCanAvaliar()
    {
        if (self::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode avaliar cedentes');
        }
    }

    /**
     * PATCH /cedente/arquivo/validacao
     */
    public static function assertCanValidarArquivo()
    {
        if (self::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode validar arquivos');
        }
    }

    /**
     * @param mixed $raw
     * @return string|null
     */
    private static function normalizeStatus($raw)
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = is_string($raw) ? trim($raw) : trim((string) $raw);
        $s = strtolower($s);
        $s = str_replace([' ', '-'], '_', $s);

        $aliases = [
            'emavaliacao' => Cedente::STATUS_EM_AVALIACAO,
            'em_avaliacao' => Cedente::STATUS_EM_AVALIACAO,
            'inconsistencia' => Cedente::STATUS_INCONSISTENTE,
            'inconsistencias' => Cedente::STATUS_INCONSISTENTE,
        ];

        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s === '' ? null : $s;
    }
}
