<?php

namespace App\Http\Services;

use App\Cedente;
use App\CedenteRole;
use App\User;
use InvalidArgumentException;

/**
 * Regras de acesso por papel de cedente (cedente_role_employee).
 *
 * - preenchimento: cria/edita rascunho e inconsistente; nao avalia nem valida arquivo.
 * - avalista: visualiza qualquer status; avalia e valida arquivo; nao edita formulario.
 * - administrador: sem restricoes adicionais aqui.
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
     * @return bool
     */
    public static function isAvalista()
    {
        return self::currentRoleCode() === CedenteRole::CODE_AVALISTA;
    }

    /**
     * @return bool
     */
    public static function isAdministrador()
    {
        return self::currentRoleCode() === CedenteRole::CODE_ADMINISTRADOR;
    }

    /**
     * POST /cedente/save
     */
    public static function assertCanCreate()
    {
        if (self::isAvalista()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode criar cedentes'
            );
        }
    }

    /**
     * PUT /cedente/edit e PATCH /cedente/patch.
     *
     * Preenchimento: so edita rascunho ou inconsistente.
     * Avalista: nao edita formulario (usa /avaliacao e /arquivo/validacao).
     *
     * @param Cedente $cedente
     * @param array $data
     */
    public static function assertCanUpdate(Cedente $cedente, array $data)
    {
        if (self::isAvalista()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode editar os dados do cadastro. Use avaliacao e validacao de arquivos'
            );
        }

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

            if ($statusAtual === Cedente::STATUS_RASCUNHO
                && !in_array($requested, [Cedente::STATUS_RASCUNHO, Cedente::STATUS_PENDENTE], true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Em rascunho so e permitido manter rascunho ou enviar pendente'
                );
            }

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

        if (self::isAvalista()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode excluir cedentes');
        }
    }

    /**
     * PATCH /cedente/avaliacao
     *
     * @param Cedente|null $cedente
     */
    public static function assertCanAvaliar($cedente = null)
    {
        if (self::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode avaliar cedentes');
        }

        if (!self::isAvalista() && !self::isAdministrador()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Apenas avalista ou administrador podem avaliar cedentes'
            );
        }

        if (self::isAvalista() && $cedente instanceof Cedente) {
            $statusAtual = $cedente->status ?: Cedente::STATUS_PENDENTE;
            if ($statusAtual === Cedente::STATUS_RASCUNHO) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode avaliar cedentes em rascunho'
                );
            }
        }
    }

    /**
     * PATCH /cedente/arquivo/validacao
     *
     * @param Cedente|null $cedente
     */
    public static function assertCanValidarArquivo($cedente = null)
    {
        if (self::isPreenchimento()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode aprovar ou recusar arquivos'
            );
        }

        if (!self::isAvalista() && !self::isAdministrador()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Apenas avalista ou administrador podem validar arquivos'
            );
        }

        if (self::isAvalista() && $cedente instanceof Cedente) {
            $statusAtual = $cedente->status ?: Cedente::STATUS_PENDENTE;
            if ($statusAtual === Cedente::STATUS_RASCUNHO) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode validar arquivos de cedentes em rascunho'
                );
            }
        }
    }

    /**
     * Download ZIP dos arquivos do cedente — qualquer papel autenticado com acesso ao fundo.
     */
    public static function assertCanDownloadArquivos()
    {
        // Visualizacao permitida a todos os papeis de cedente (e usuarios sem papel).
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
            'recusado' => Cedente::STATUS_REJEITADO,
            'rejeitado' => Cedente::STATUS_REJEITADO,
        ];

        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s === '' ? null : $s;
    }
}
