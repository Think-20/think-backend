<?php

namespace App\Http\Services;

use App\Cedente;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * E-mails do fluxo de cedente (aprovacao e pendencias).
 */
class CedenteMailService
{
    const DEFAULT_PORTAL_PATH = '/cedentes/{id}';

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
        return Cedente::statusLabels();
    }

    /**
     * URL do portal (botao do e-mail). Path via CEDENTE_EMAIL_PORTAL_PATH (default /cedentes/{id}).
     *
     * @param int|null $cedenteId
     * @return string
     */
    public static function portalUrl($cedenteId = null)
    {
        $base = MailService::frontendBaseUrl();
        $path = env('CEDENTE_EMAIL_PORTAL_PATH', self::DEFAULT_PORTAL_PATH);
        $path = is_string($path) && trim($path) !== '' ? trim($path) : self::DEFAULT_PORTAL_PATH;

        $id = $cedenteId !== null ? (string) (int) $cedenteId : '';
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
     * Monta subject + HTML do e-mail de aprovacao.
     *
     * @param array $data nome, documento, limite_aprovado, sla, observacao, subject, portal_url, cedente_id
     * @return array{subject: string, body: string, to_name: string}
     */
    public static function buildAprovadoEmailContent(array $data = [])
    {
        $nome = self::safeName(isset($data['nome']) ? $data['nome'] : null);
        $observacao = isset($data['observacao']) ? trim((string) $data['observacao']) : '';
        $documento = isset($data['documento']) ? trim((string) $data['documento']) : '';
        $limite = array_key_exists('limite_aprovado', $data) ? $data['limite_aprovado'] : null;
        $sla = self::formatSla(isset($data['sla']) ? $data['sla'] : null);
        $portalUrl = isset($data['portal_url']) ? trim((string) $data['portal_url']) : '';
        if ($portalUrl === '') {
            $portalUrl = self::portalUrl(isset($data['cedente_id']) ? $data['cedente_id'] : null);
        }

        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $details = self::buildDetailsTable([
            'Documento' => $documento,
            'Limite aprovado' => ($limite !== null && $limite !== '') ? (string) $limite : '',
            'Validade cadastral' => $sla,
            'Observação' => $observacao,
        ]);

        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Olá, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Boa notícia: o cadastro de cedente foi <strong style="color: #1b7a3d;">aprovado</strong> com sucesso.
            </p>
            <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.5; color: #555555; text-align: left;">
                Você já pode acompanhar o status e os dados do cadastro no sistema.
            </p>
            ' . $details . '
            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.5; color: #666666; text-align: left;">
                Se precisar de ajuda, responda este e-mail ou fale com a equipe responsável.
            </p>';

        $subject = isset($data['subject']) && trim((string) $data['subject']) !== ''
            ? (string) $data['subject']
            : 'Cadastro de cedente aprovado';

        return [
            'subject' => $subject,
            'to_name' => $nome,
            'body' => MailService::renderHtmlLayout(
                'Cadastro aprovado',
                $inner,
                $portalUrl,
                'Acessar o cadastro',
                '1b7a3d'
            ),
        ];
    }

    /**
     * Monta subject + HTML do e-mail de pendencias / correcoes.
     *
     * @param array $data nome, documento, observacao, inconsistencias[], subject, portal_url, cedente_id
     * @return array{subject: string, body: string, to_name: string}
     */
    public static function buildPendenciasEmailContent(array $data = [])
    {
        $nome = self::safeName(isset($data['nome']) ? $data['nome'] : null);
        $observacao = isset($data['observacao']) ? trim((string) $data['observacao']) : '';
        $documento = isset($data['documento']) ? trim((string) $data['documento']) : '';
        $inconsistencias = isset($data['inconsistencias']) && is_array($data['inconsistencias'])
            ? $data['inconsistencias']
            : [];
        $portalUrl = isset($data['portal_url']) ? trim((string) $data['portal_url']) : '';
        if ($portalUrl === '') {
            $portalUrl = self::portalUrl(isset($data['cedente_id']) ? $data['cedente_id'] : null);
        }

        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $details = self::buildDetailsTable([
            'Documento' => $documento,
            'Orientação do analista' => $observacao,
        ]);
        $listaPendencias = self::buildInconsistenciasList($inconsistencias);

        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Olá, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Seu cadastro de cedente precisa de <strong style="color: #b36b00;">ajustes</strong> antes de seguir adiante.
            </p>
            <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.5; color: #555555; text-align: left;">
                Veja as pendências abaixo, faça as correções no sistema e reenvie o cadastro quando estiver pronto.
            </p>
            ' . $details . $listaPendencias . '
            <p style="margin: 20px 0 0; font-size: 14px; line-height: 1.5; color: #666666; text-align: left;">
                Clique no botão abaixo para abrir o cadastro e resolver as pendências.
            </p>';

        $subject = isset($data['subject']) && trim((string) $data['subject']) !== ''
            ? (string) $data['subject']
            : 'Cadastro de cedente — pendências para correção';

        return [
            'subject' => $subject,
            'to_name' => $nome,
            'body' => MailService::renderHtmlLayout(
                'Pendências no cadastro',
                $inner,
                $portalUrl,
                'Acessar o cadastro',
                'b36b00'
            ),
        ];
    }

    /**
     * E-mail ao cedente quando o cadastro e aprovado (destinatario = cedente.email).
     *
     * @param Cedente|int $cedente
     * @param array $options observacao, to, to_name, subject, from, from_name
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyAprovado($cedente, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'CEDENTE_STATUS_EMAIL_ENABLED=false',
            ];
        }

        $cedente = self::resolveCedente($cedente);
        $to = self::resolveRecipientEmail($cedente, $options);
        if ($to === null) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cedente_sem_email',
            ];
        }

        $nome = isset($options['to_name']) ? $options['to_name'] : ($cedente->nome ?: 'Cedente');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';
        if ($observacao === '' && ! empty($cedente->observacao)) {
            $observacao = trim((string) $cedente->observacao);
        }

        $content = self::buildAprovadoEmailContent([
            'cedente_id' => $cedente->id,
            'nome' => $nome,
            'documento' => $cedente->documento,
            'limite_aprovado' => $cedente->limite_aprovado,
            'sla' => $cedente->sla,
            'observacao' => $observacao,
            'subject' => isset($options['subject']) ? $options['subject'] : null,
            'portal_url' => isset($options['portal_url']) ? $options['portal_url'] : null,
        ]);

        return self::dispatch($cedente, [
            'to' => $to,
            'to_name' => $content['to_name'],
            'subject' => $content['subject'],
            'body' => $content['body'],
        ], $options, Cedente::STATUS_APROVADO, Cedente::STATUS_APROVADO);
    }

    /**
     * E-mail ao cedente quando ha pendencias / solicitar_correcoes.
     *
     * @param Cedente|int $cedente
     * @param array $options observacao, to, to_name, subject, from, from_name, inconsistencias
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyPendencias($cedente, array $options = [])
    {
        if (! self::isStatusEmailEnabled()) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'CEDENTE_STATUS_EMAIL_ENABLED=false',
            ];
        }

        $cedente = self::resolveCedente($cedente);
        $cedente->loadMissing(['inconsistencias']);

        $to = self::resolveRecipientEmail($cedente, $options);
        if ($to === null) {
            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'cedente_sem_email',
            ];
        }

        $nome = isset($options['to_name']) ? $options['to_name'] : ($cedente->nome ?: 'Cedente');
        $observacao = isset($options['observacao']) ? trim((string) $options['observacao']) : '';
        if ($observacao === '' && ! empty($cedente->observacao)) {
            $observacao = trim((string) $cedente->observacao);
        }

        $inconsistencias = isset($options['inconsistencias']) && is_array($options['inconsistencias'])
            ? $options['inconsistencias']
            : self::inconsistenciasFromCedente($cedente);

        $content = self::buildPendenciasEmailContent([
            'cedente_id' => $cedente->id,
            'nome' => $nome,
            'documento' => $cedente->documento,
            'observacao' => $observacao,
            'inconsistencias' => $inconsistencias,
            'subject' => isset($options['subject']) ? $options['subject'] : null,
            'portal_url' => isset($options['portal_url']) ? $options['portal_url'] : null,
        ]);

        return self::dispatch($cedente, [
            'to' => $to,
            'to_name' => $content['to_name'],
            'subject' => $content['subject'],
            'body' => $content['body'],
        ], $options, Cedente::STATUS_INCONSISTENTE, Cedente::STATUS_INCONSISTENTE);
    }

    /**
     * Rota de teste: envia HTML de aprovacao ou pendencias.
     * Nao exige CEDENTE_STATUS_EMAIL_ENABLED (ainda respeita MAIL_ENABLED).
     *
     * @param string $email
     * @param array $options tipo (aprovado|pendencias), cedente_id, nome, documento, ...
     * @return array{sent: bool, skipped?: bool, reason?: string, subject?: string, to?: string, tipo?: string}
     */
    public static function sendTestEmail($email, array $options = [])
    {
        $to = is_string($email) ? trim($email) : '';
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('email invalido');
        }

        $tipo = isset($options['tipo']) ? strtolower(trim((string) $options['tipo'])) : 'aprovado';
        if ($tipo === 'pendencia') {
            $tipo = 'pendencias';
        }
        if (! in_array($tipo, ['aprovado', 'pendencias'], true)) {
            throw new InvalidArgumentException('tipo invalido. Use aprovado ou pendencias');
        }

        $cedenteId = isset($options['cedente_id']) ? (int) $options['cedente_id'] : 0;
        if ($cedenteId > 0) {
            $cedente = Cedente::with('inconsistencias')->find($cedenteId);
            if (! $cedente) {
                throw new InvalidArgumentException('Cedente nao encontrado para cedente_id=' . $cedenteId);
            }

            if ($tipo === 'pendencias') {
                $content = self::buildPendenciasEmailContent([
                    'cedente_id' => $cedente->id,
                    'nome' => $cedente->nome ?: 'Cedente',
                    'documento' => $cedente->documento,
                    'observacao' => isset($options['observacao'])
                        ? $options['observacao']
                        : ($cedente->observacao ?: 'Há itens pendentes no cadastro.'),
                    'inconsistencias' => self::inconsistenciasFromCedente($cedente),
                    'subject' => isset($options['subject']) ? $options['subject'] : null,
                ]);
            } else {
                $content = self::buildAprovadoEmailContent([
                    'cedente_id' => $cedente->id,
                    'nome' => $cedente->nome ?: 'Cedente',
                    'documento' => $cedente->documento,
                    'limite_aprovado' => $cedente->limite_aprovado,
                    'sla' => $cedente->sla,
                    'observacao' => isset($options['observacao']) ? $options['observacao'] : $cedente->observacao,
                    'subject' => isset($options['subject']) ? $options['subject'] : null,
                ]);
            }
        } elseif ($tipo === 'pendencias') {
            $content = self::buildPendenciasEmailContent([
                'cedente_id' => 0,
                'nome' => isset($options['nome']) ? $options['nome'] : 'Cedente Teste',
                'documento' => isset($options['documento']) ? $options['documento'] : '00.000.000/0001-00',
                'observacao' => isset($options['observacao'])
                    ? $options['observacao']
                    : 'Por favor, revise os dados cadastrais e reenvie os documentos solicitados.',
                'inconsistencias' => isset($options['inconsistencias']) && is_array($options['inconsistencias'])
                    ? $options['inconsistencias']
                    : [
                        ['campo' => 'nome', 'valor' => 'Razão social diverge da Receita'],
                        ['campo' => 'endereco.cep', 'valor' => 'CEP inválido ou incompleto'],
                    ],
                'subject' => isset($options['subject']) ? $options['subject'] : null,
            ]);
        } else {
            $content = self::buildAprovadoEmailContent([
                'cedente_id' => 0,
                'nome' => isset($options['nome']) ? $options['nome'] : 'Cedente Teste',
                'documento' => isset($options['documento']) ? $options['documento'] : '00.000.000/0001-00',
                'limite_aprovado' => array_key_exists('limite_aprovado', $options) ? $options['limite_aprovado'] : '100000.00',
                'sla' => isset($options['sla']) ? $options['sla'] : date('d/m/Y', strtotime('+3 months')),
                'observacao' => isset($options['observacao']) ? $options['observacao'] : 'E-mail de teste da aprovação de cedente.',
                'subject' => isset($options['subject']) ? $options['subject'] : null,
            ]);
        }

        $params = [
            'to' => $to,
            'to_name' => $content['to_name'],
            'subject' => $content['subject'],
            'body' => $content['body'],
        ];

        foreach (['from', 'from_name', 'reply_to', 'cc', 'bcc'] as $key) {
            if (isset($options[$key])) {
                $params[$key] = $options[$key];
            }
        }

        $result = MailService::send($params);
        $result['to'] = $to;
        $result['subject'] = $content['subject'];
        $result['tipo'] = $tipo;

        return $result;
    }

    /**
     * Compatibilidade com chamadas antigas da rota de teste.
     *
     * @param string $email
     * @param array $options
     * @return array
     */
    public static function sendTestAprovadoEmail($email, array $options = [])
    {
        $options['tipo'] = 'aprovado';

        return self::sendTestEmail($email, $options);
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

        if ($newStatus === Cedente::STATUS_APROVADO) {
            return self::notifyAprovado($cedente, $options);
        }

        if ($newStatus === Cedente::STATUS_INCONSISTENTE) {
            return self::notifyPendencias($cedente, $options);
        }

        $cedente = self::resolveCedente($cedente);
        $to = self::resolveRecipientEmail($cedente, $options);
        if ($to === null) {
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
        $portalUrl = self::portalUrl($cedente->id);

        $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
        $inner = '
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                Olá, <strong>' . $safeNome . '</strong>.
            </p>
            <p style="margin: 0 0 16px; font-size: 16px; line-height: 1.5; color: #333333; text-align: left;">
                O status do seu cadastro de cedente foi atualizado.
            </p>
            ' . self::buildDetailsTable([
                'Status anterior' => $oldLabel,
                'Status atual' => $newLabel,
                'Documento' => $cedente->documento,
                'Observação' => $observacao,
            ]);

        $subject = isset($options['subject']) && trim((string) $options['subject']) !== ''
            ? (string) $options['subject']
            : 'Cadastro de cedente — status atualizado para ' . $newLabel;

        return self::dispatch($cedente, [
            'to' => $to,
            'to_name' => $nome,
            'subject' => $subject,
            'body' => MailService::renderHtmlLayout(
                'Atualização de cadastro',
                $inner,
                $portalUrl,
                'Acessar o cadastro'
            ),
        ], $options, $oldStatus, $newStatus);
    }

    /**
     * @param Cedente $cedente
     * @return array<int, array{campo: string, valor: string}>
     */
    private static function inconsistenciasFromCedente(Cedente $cedente)
    {
        $rows = [];
        $items = $cedente->inconsistencias;
        if (! $items) {
            return $rows;
        }

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
            $value = is_string($value) ? trim($value) : '';
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
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px; border-collapse: collapse; background: #fafafa; border: 1px solid #eeeeee; border-radius: 4px;">
                ' . $lines . '
            </table>';
    }

    /**
     * @param array<int, array{campo?: string, valor?: string}|object> $items
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
                $campo = isset($item->campo) ? trim((string) $item->campo) : '';
                $valor = isset($item->valor) ? trim((string) $item->valor) : '';
            } else {
                continue;
            }

            if ($campo === '' && $valor === '') {
                continue;
            }

            $label = $campo !== '' ? $campo : 'Pendência';
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
            <p style="margin: 16px 0 8px; font-size: 14px; font-weight: bold; color: #333333; text-align: left;">Pendências:</p>
            <ul style="margin: 0 0 8px; padding-left: 20px; text-align: left;">
                ' . $lis . '
            </ul>';
    }

    /**
     * @param mixed $nome
     * @return string
     */
    private static function safeName($nome)
    {
        $nome = is_string($nome) ? trim($nome) : '';

        return $nome !== '' ? $nome : 'Cedente';
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
     * @param Cedente|int $cedente
     * @return Cedente
     */
    private static function resolveCedente($cedente)
    {
        if (! $cedente instanceof Cedente) {
            $cedente = Cedente::find((int) $cedente);
        }

        if (! $cedente) {
            throw new InvalidArgumentException('Cedente nao encontrado para notificacao de status');
        }

        return $cedente;
    }

    /**
     * @param Cedente $cedente
     * @param array $options
     * @return string|null
     */
    private static function resolveRecipientEmail(Cedente $cedente, array $options)
    {
        $to = isset($options['to']) ? $options['to'] : $cedente->email;
        $to = is_string($to) ? trim($to) : '';

        return $to !== '' ? $to : null;
    }

    /**
     * @param Cedente $cedente
     * @param array $params
     * @param array $options
     * @param string|null $oldStatus
     * @param string $newStatus
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    private static function dispatch(Cedente $cedente, array $params, array $options, $oldStatus, $newStatus)
    {
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
