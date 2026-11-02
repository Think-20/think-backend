<?php

namespace App\Http\Services;

use App\Cotista;
use InvalidArgumentException;

/**
 * Mesmos papeis do modulo de cedente (cedente_role_employee).
 * Cotista nao tem fundo: a lista e unica.
 *
 * - 1 preenchimento: cria/edita rascunho e inconsistente; nao avalia nem valida arquivo.
 * - 2 avalista (aprovador): visualiza; avalia e valida arquivo; nao edita formulario.
 * - 3 administrador: sem restricao de papel.
 */
class CotistaPermissionService
{
    const MSG_SEM_PERMISSAO = 'Voce nao tem permissao para realizar esta acao';

    public static function assertCanCreate()
    {
        if (CedentePermissionService::isAvalista()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode criar cotistas'
            );
        }
    }

    /**
     * @param Cotista $cotista
     * @param array $data
     */
    public static function assertCanUpdate(Cotista $cotista, array $data)
    {
        if (CedentePermissionService::isAvalista()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode editar os dados do cadastro. Use avaliacao e validacao de arquivos'
            );
        }

        if (! CedentePermissionService::isPreenchimento()) {
            return;
        }

        $statusAtual = $cotista->status ?: Cotista::STATUS_PENDENTE;

        if ($statusAtual === Cotista::STATUS_INCONSISTENCIA_JURIDICA) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Inconsistencia juridica aguarda decisao do aprovador; preenchimento nao altera este cadastro'
            );
        }

        $editaveis = [
            Cotista::STATUS_RASCUNHO,
            Cotista::STATUS_INCONSISTENTE,
        ];

        if (! in_array($statusAtual, $editaveis, true)) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento so pode alterar cotistas em rascunho ou inconsistencia cadastral (status atual: ' . $statusAtual . ')'
            );
        }

        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $requested = self::normalizeStatus($data['status']);
            $permitidos = [
                Cotista::STATUS_RASCUNHO,
                Cotista::STATUS_PENDENTE,
                Cotista::STATUS_INCONSISTENTE,
            ];

            if ($requested === null || ! in_array($requested, $permitidos, true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento so pode enviar status rascunho, pendente ou inconsistente'
                );
            }

            if ($statusAtual === Cotista::STATUS_RASCUNHO
                && ! in_array($requested, [Cotista::STATUS_RASCUNHO, Cotista::STATUS_PENDENTE], true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Em rascunho so e permitido manter rascunho ou enviar pendente'
                );
            }

            if ($statusAtual === Cotista::STATUS_INCONSISTENTE
                && ! in_array($requested, [Cotista::STATUS_PENDENTE, Cotista::STATUS_INCONSISTENTE], true)) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Em inconsistencia cadastral so e permitido enviar pendente ou inconsistente'
                );
            }
        }
    }

    /**
     * @param Cotista|null $cotista
     */
    public static function assertCanDelete($cotista = null)
    {
        if (CedentePermissionService::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode excluir cotistas');
        }

        if (CedentePermissionService::isAvalista()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode excluir cotistas');
        }
    }

    /**
     * @param Cotista|null $cotista
     */
    public static function assertCanAvaliar($cotista = null)
    {
        if (CedentePermissionService::isPreenchimento()) {
            throw new InvalidArgumentException(self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode avaliar cotistas');
        }

        if (! CedentePermissionService::isAvalista() && ! CedentePermissionService::isAdministrador()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Apenas avalista ou administrador podem avaliar cotistas'
            );
        }

        if (CedentePermissionService::isAvalista() && $cotista instanceof Cotista) {
            $statusAtual = $cotista->status ?: Cotista::STATUS_PENDENTE;
            if ($statusAtual === Cotista::STATUS_RASCUNHO) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode avaliar cotistas em rascunho'
                );
            }
        }
    }

    /**
     * @param Cotista|null $cotista
     */
    public static function assertCanValidarArquivo($cotista = null)
    {
        if (CedentePermissionService::isPreenchimento()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Usuario de preenchimento nao pode aprovar ou recusar arquivos'
            );
        }

        if (! CedentePermissionService::isAvalista() && ! CedentePermissionService::isAdministrador()) {
            throw new InvalidArgumentException(
                self::MSG_SEM_PERMISSAO . '. Apenas avalista ou administrador podem validar arquivos'
            );
        }

        if (CedentePermissionService::isAvalista() && $cotista instanceof Cotista) {
            $statusAtual = $cotista->status ?: Cotista::STATUS_PENDENTE;
            if ($statusAtual === Cotista::STATUS_RASCUNHO) {
                throw new InvalidArgumentException(
                    self::MSG_SEM_PERMISSAO . '. Usuario avalista nao pode validar arquivos de cotistas em rascunho'
                );
            }
        }
    }

    public static function assertCanDownloadArquivos()
    {
    }

    public static function assertCanView()
    {
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
            'emavaliacao' => Cotista::STATUS_EM_AVALIACAO,
            'em_avaliacao' => Cotista::STATUS_EM_AVALIACAO,
            'inconsistencia' => Cotista::STATUS_INCONSISTENTE,
            'inconsistencias' => Cotista::STATUS_INCONSISTENTE,
            'inconsistencia_cadastral' => Cotista::STATUS_INCONSISTENTE,
            'inconsistencia_juridica' => Cotista::STATUS_INCONSISTENCIA_JURIDICA,
            'recusado' => Cotista::STATUS_REJEITADO,
            'rejeitado' => Cotista::STATUS_REJEITADO,
        ];

        if (isset($aliases[$s])) {
            return $aliases[$s];
        }

        return $s === '' ? null : $s;
    }
}
