<?php

namespace App\Http\Services;

use App\Cotista;
use App\CotistaInconsistencia;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * E-mails do fluxo de cotista (aprovacao, pendencias e mudanca de status).
 */
class CotistaMailService
{
    const DEFAULT_PORTAL_PATH = '/cotistas/{id}';

    /**
     * @return bool
     */
    public static function isStatusEmailEnabled()
    {
        $value = env('COTISTA_STATUS_EMAIL_ENABLED', env('CEDENTE_STATUS_EMAIL_ENABLED', false));
        if (is_string($value)) {
            return ! in_array(strtolower($value), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $value;
    }

    /**
     * @param int|null $cotistaId
     * @return string
     */
    public static function portalUrl($cotistaId = null)
    {
        $base = MailService::frontendBaseUrl();
        $path = env('COTISTA_EMAIL_PORTAL_PATH', self::DEFAULT_PORTAL_PATH);
        $path = is_string($path) && trim($path) !== '' ? trim($path) : self::DEFAULT_PORTAL_PATH;

        $id = $cotistaId !== null ? (string) (int) $cotistaId : '';
        if (strpos($path, '{id}') !== false) {
            $path = str_replace('{id}', $id !== '' ? $id : '0', $path);
        } elseif ($id !== '') {
            $path = rtrim($path, '/') . '/' . $id;
        }

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        return $base . $path;
    }

    /**
     * @param Cotista|int $cotista
     * @param array $options
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyAprovado($cotista, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'COTISTA_STATUS_EMAIL_ENABLED=false',
            ];
        }

        $cotista = self::resolveCotista($cotista);
        $to = self::resolveRecipientEmail($cotista, $options);
        if ($to === null) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cotista_sem_email',
            ];
        }

        $nome = isset($options['to_name']) ? $options['to_name'] : ($cotista->nome_completo ?: 'Cotista');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';
        $portalUrl = isset($options['portal_url']) ? trim((string) $options['portal_url']) : self::portalUrl($cotista->id);
        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $details = self::buildDetailsTable([
            'Documento' => $cotista->documento,
            'Validade cadastral' => self::formatSla($cotista->sla),
            'Observacao' => $observacao,
        ]);

        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Ola, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                O cadastro de cotista foi <strong style="color: #1b7a3d;">aprovado</strong>.
            </p>
            ' . $details;

        $subject = isset($options['subject']) && trim((string) $options['subject']) !== ''
            ? (string) $options['subject']
            : 'Cadastro de cotista aprovado';

        return self::dispatch($cotista, [
            'to' => $to,
            'to_name' => $nome,
            'subject' => $subject,
            'body' => MailService::renderHtmlLayout(
                'Cadastro aprovado',
                $inner,
                $portalUrl,
                'Acessar o cadastro',
                '1b7a3d'
            ),
        ], $options, Cotista::STATUS_APROVADO, Cotista::STATUS_APROVADO);
    }

    /**
     * @param Cotista|int $cotista
     * @param array $options
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyPendencias($cotista, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'COTISTA_STATUS_EMAIL_ENABLED=false',
            ];
        }

        $cotista = self::resolveCotista($cotista);
        $to = self::resolveRecipientEmail($cotista, $options);
        if ($to === null) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cotista_sem_email',
            ];
        }

        $nome = isset($options['to_name']) ? $options['to_name'] : ($cotista->nome_completo ?: 'Cotista');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';
        $portalUrl = isset($options['portal_url']) ? trim((string) $options['portal_url']) : self::portalUrl($cotista->id);
        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $inconsistencias = isset($options['inconsistencias']) && is_array($options['inconsistencias'])
            ? $options['inconsistencias']
            : self::inconsistenciasFromCotista($cotista);

        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Ola, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                O cadastro de cotista precisa de <strong style="color: #b36b00;">ajustes</strong> antes de seguir.
            </p>
            ' . self::buildDetailsTable([
                'Documento' => $cotista->documento,
                'Orientacao do analista' => $observacao,
            ]) . self::buildInconsistenciasList($inconsistencias);

        $subject = isset($options['subject']) && trim((string) $options['subject']) !== ''
            ? (string) $options['subject']
            : 'Cadastro de cotista — pendencias para correcao';

        return self::dispatch($cotista, [
            'to' => $to,
            'to_name' => $nome,
            'subject' => $subject,
            'body' => MailService::renderHtmlLayout(
                'Pendencias no cadastro',
                $inner,
                $portalUrl,
                'Acessar o cadastro',
                'b36b00'
            ),
        ], $options, Cotista::STATUS_INCONSISTENTE, Cotista::STATUS_INCONSISTENTE);
    }

    /**
     * @param Cotista|int $cotista
     * @param string|null $oldStatus
     * @param string $newStatus
     * @param array $options
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyStatusChange($cotista, $oldStatus, $newStatus, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'COTISTA_STATUS_EMAIL_ENABLED=false',
            ];
        }

        if ($oldStatus !== null && $oldStatus === $newStatus) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'status_igual',
            ];
        }

        if ($newStatus === Cotista::STATUS_APROVADO) {
            return self::notifyAprovado($cotista, $options);
        }

        if ($newStatus === Cotista::STATUS_INCONSISTENTE) {
            return self::notifyPendencias($cotista, $options);
        }

        $cotista = self::resolveCotista($cotista);
        $to = self::resolveRecipientEmail($cotista, $options);
        if ($to === null) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cotista_sem_email',
            ];
        }

        $labels = Cotista::statusLabels();
        $oldLabel = ($oldStatus !== null && isset($labels[$oldStatus])) ? $labels[$oldStatus] : ($oldStatus ?: '-');
        $newLabel = isset($labels[$newStatus]) ? $labels[$newStatus] : $newStatus;
        $nome = isset($options['to_name']) ? $options['to_name'] : ($cotista->nome_completo ?: 'Cotista');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';
        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');

        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Ola, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                O status do cadastro de cotista foi atualizado.
            </p>
            ' . self::buildDetailsTable([
                'Status anterior' => $oldLabel,
                'Status atual' => $newLabel,
                'Documento' => $cotista->documento,
                'Observacao' => $observacao,
            ]);

        $subject = isset($options['subject']) && trim((string) $options['subject']) !== ''
            ? (string) $options['subject']
            : 'Cadastro de cotista — status atualizado para ' . $newLabel;

        return self::dispatch($cotista, [
            'to' => $to,
            'to_name' => $nome,
            'subject' => $subject,
            'body' => MailService::renderHtmlLayout(
                'Atualizacao de cadastro',
                $inner,
                self::portalUrl($cotista->id),
                'Acessar o cadastro'
            ),
        ], $options, $oldStatus, $newStatus);
    }

    /**
     * @param Cotista $cotista
     * @return array<int, array{campo: string, valor: string}>
     */
    private static function inconsistenciasFromCotista(Cotista $cotista)
    {
        $rows = [];
        $items = CotistaInconsistencia::where('cotista_id', (int) $cotista->id)->orderBy('id', 'asc')->get();

        foreach ($items as $item) {
            $campo = isset($item->campo_inconsistente) ? (string) $item->campo_inconsistente : '';
            $valor = isset($item->valor_serpro) ? (string) $item->valor_serpro : '';
            if ($campo === '' && $valor === '') {
                continue;
            }
            $rows[] = [
                'campo' => $campo,
                'valor' => $valor,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, string> $rows
     * @return string
     */
    private static function buildDetailsTable(array $rows)
    {
        $lines = '';
        foreach ($rows as $label => $value) {
            $value = is_string($value) ? trim($value) : (is_numeric($value) ? (string) $value : '');
            if ($value === '') {
                continue;
            }
            $lines .= '
                <tr>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #eeeeee; color: #666666; font-size: 13px; width: 40%; text-align: left;">'
                        . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') .
                    '</td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid #eeeeee; color: #222222; font-size: 14px; text-align: left;">'
                        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') .
                    '</td>
                </tr>';
        }

        if ($lines === '') {
            return '';
        }

        return '
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px; border-collapse: collapse; background: #fafafa; border: 1px solid #eeeeee;">
                ' . $lines . '
            </table>';
    }

    /**
     * @param array $items
     * @return string
     */
    private static function buildInconsistenciasList(array $items)
    {
        if (empty($items)) {
            return '';
        }

        $lis = '';
        $count = 0;
        foreach ($items as $item) {
            if ($count >= 10) {
                break;
            }
            if (is_array($item)) {
                $campo = isset($item['campo']) ? trim((string) $item['campo']) : '';
                $valor = isset($item['valor']) ? trim((string) $item['valor']) : '';
            } elseif (is_object($item)) {
                $campo = isset($item->campo_inconsistente) ? trim((string) $item->campo_inconsistente) : '';
                $valor = isset($item->valor_serpro) ? trim((string) $item->valor_serpro) : '';
            } else {
                continue;
            }

            if ($campo === '' && $valor === '') {
                continue;
            }

            $label = $campo !== '' ? $campo : 'Pendencia';
            $text = $valor !== '' ? $label . ': ' . $valor : $label;
            $lis .= '<li style="margin: 0 0 8px; color: #333333; font-size: 14px; text-align: left;">'
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
                . '</li>';
            $count++;
        }

        if ($lis === '') {
            return '';
        }

        return '
            <p style="margin: 16px 0 8px; font-size: 14px; font-weight: bold; color: #333333; text-align: left;">Pendencias:</p>
            <ul style="margin: 0 0 8px; padding-left: 20px; text-align: left;">
                ' . $lis . '
            </ul>';
    }

    /**
     * @param mixed $sla
     * @return string
     */
    private static function formatSla($sla)
    {
        if ($sla === null || $sla === '') {
            return '';
        }
        if ($sla instanceof \DateTimeInterface) {
            return $sla->format('d/m/Y');
        }

        return trim((string) $sla);
    }

    /**
     * @param Cotista|int $cotista
     * @return Cotista
     */
    private static function resolveCotista($cotista)
    {
        if (! $cotista instanceof Cotista) {
            $cotista = Cotista::find((int) $cotista);
        }

        if (! $cotista) {
            throw new InvalidArgumentException('Cotista nao encontrado para notificacao de status');
        }

        return $cotista;
    }

    /**
     * @param Cotista $cotista
     * @param array $options
     * @return string|null
     */
    private static function resolveRecipientEmail(Cotista $cotista, array $options)
    {
        $to = isset($options['to']) ? $options['to'] : $cotista->email;
        $to = is_string($to) ? trim($to) : '';

        return $to !== '' ? $to : null;
    }

    /**
     * @param Cotista $cotista
     * @param array $params
     * @param array $options
     * @param string|null $oldStatus
     * @param string $newStatus
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    private static function dispatch(Cotista $cotista, array $params, array $options, $oldStatus, $newStatus)
    {
        foreach (['from', 'from_name', 'reply_to', 'cc', 'bcc'] as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        try {
            return MailService::send($params);
        } catch (\Exception $e) {
            Log::warning('CotistaMailService: falha ao enviar e-mail de status', [
                'cotista_id' => $cotista->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'message' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'skipped' => true,
                'reason' => $e->getMessage(),
            ];
        }
    }
}
