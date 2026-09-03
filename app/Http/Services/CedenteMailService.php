<?php

namespace App\Http\Services;

use App\Cedente;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * E-mails do fluxo de cedente (mudanca de status, etc.).
 */
class CedenteMailService
{
    /**
     * @return bool
     */
    public static function isStatusEmailEnabled()
    {
        $value = env('CEDENTE_STATUS_EMAIL_ENABLED', false);
        if (is_string($value)) {
            return ! in_array(strtolower($value), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $value;
    }

    /**
     * Labels amigaveis dos status para o corpo do e-mail.
     *
     * @return array<string, string>
     */
    public static function statusLabels()
    {
        return [
            Cedente::STATUS_RASCUNHO => 'Rascunho',
            Cedente::STATUS_PENDENTE => 'Pendente',
            Cedente::STATUS_EM_AVALIACAO => 'Em avaliacao',
            Cedente::STATUS_INCONSISTENTE => 'Inconsistente',
            Cedente::STATUS_APROVADO => 'Aprovado',
            Cedente::STATUS_REJEITADO => 'Rejeitado',
            Cedente::STATUS_VENCIDO => 'Vencido',
            Cedente::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    /**
     * Envia e-mail de mudanca de status se CEDENTE_STATUS_EMAIL_ENABLED=true
     * e o cedente tiver e-mail cadastrado.
     *
     * @param Cedente|int $cedente
     * @param string|null $oldStatus
     * @param string $newStatus
     * @param array $options to, to_name, from, from_name, observacao, subject
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyStatusChange($cedente, $oldStatus, $newStatus, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'CEDENTE_STATUS_EMAIL_ENABLED=false',
            ];
        }

        if ($oldStatus !== null && $oldStatus === $newStatus) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'status_igual',
            ];
        }

        if (! $cedente instanceof Cedente) {
            $cedente = Cedente::find((int) $cedente);
        }

        if (! $cedente) {
            throw new InvalidArgumentException('Cedente nao encontrado para notificacao de status');
        }

        $to = isset($options['to']) ? $options['to'] : $cedente->email;
        $to = is_string($to) ? trim($to) : '';

        if ($to === '') {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cedente_sem_email',
            ];
        }

        $labels = self::statusLabels();
        $oldLabel = ($oldStatus !== null && isset($labels[$oldStatus])) ? $labels[$oldStatus] : ($oldStatus ?: '-');
        $newLabel = isset($labels[$newStatus]) ? $labels[$newStatus] : $newStatus;

        $nome = isset($options['to_name']) ? $options['to_name'] : ($cedente->nome ?: 'Cedente');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';

        $bodyLines = [
            'O status do seu cadastro de cedente foi atualizado.',
            '<br /><br />',
            '<strong>Status anterior:</strong> ' . htmlspecialchars($oldLabel, ENT_QUOTES, 'UTF-8'),
            '<br />',
            '<strong>Status atual:</strong> ' . htmlspecialchars($newLabel, ENT_QUOTES, 'UTF-8'),
        ];

        if ($observacao !== '') {
            $bodyLines[] = '<br /><br /><strong>Observacao:</strong> ' . htmlspecialchars($observacao, ENT_QUOTES, 'UTF-8');
        }

        if (! empty($cedente->documento)) {
            $bodyLines[] = '<br /><br /><strong>Documento:</strong> ' . htmlspecialchars($cedente->documento, ENT_QUOTES, 'UTF-8');
        }

        $subject = isset($options['subject']) && trim((string) $options['subject']) !== ''
            ? (string) $options['subject']
            : 'Cadastro de cedente — status atualizado para ' . $newLabel;

        $params = [
            'to' => $to,
            'to_name' => $nome,
            'subject' => $subject,
            'body' => MailService::renderHtmlLayout(
                'Atualizacao de cadastro',
                implode("\n", $bodyLines)
            ),
        ];

        foreach (['from', 'from_name', 'reply_to', 'cc', 'bcc'] as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        try {
            return MailService::send($params);
        } catch (\Exception $e) {
            Log::warning('CedenteMailService: falha ao enviar e-mail de status', [
                'cedente_id' => $cedente->id,
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
