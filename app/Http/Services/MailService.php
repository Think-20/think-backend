<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envio de e-mail centralizado (jobs, cedentes e demais modulos).
 *
 * Configuracao SMTP via .env / config/mail.php (MAIL_HOST, MAIL_PORT, etc.).
 *
 * Exemplo:
 *   MailService::send([
 *       'to' => 'destino@exemplo.com',
 *       'to_name' => 'Maria Souza',
 *       'from' => 'no-reply@think.com',
 *       'from_name' => 'Think Cadastro',
 *       'subject' => 'Cadastro aprovado',
 *       'body' => '<p>Seu cadastro foi aprovado.</p>',
 *   ]);
 */
class MailService
{
    /**
     * @return bool
     */
    public static function isEnabled()
    {
        return self::envFlag('MAIL_ENABLED', true);
    }

    /**
     * Envia um e-mail.
     *
     * Parametros principais:
     * - to (obrigatorio): string, lista de strings ou [['email' => '', 'name' => '']]
     * - subject (obrigatorio)
     * - body (obrigatorio): HTML ou texto
     * - from, from_name (opcional; padrao MAIL_FROM_ADDRESS / MAIL_FROM_NAME)
     * - reply_to, reply_to_name (opcional)
     * - cc, bcc (opcional; mesmo formato de to)
     * - is_html (bool, default true)
     * - alt_body (string, opcional — versao texto quando is_html=true)
     *
     * @param array $params
     * @return array{sent: bool, skipped?: bool, reason?: string}
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public static function send(array $params)
    {
        if (! self::isEnabled()) {
            Log::info('MailService: envio ignorado (MAIL_ENABLED=false)', [
                'subject' => isset($params['subject']) ? $params['subject'] : null,
                'to' => isset($params['to']) ? $params['to'] : null,
            ]);

            return [
                'sent' => false,
                'skipped' => true,
                'reason' => 'MAIL_ENABLED=false',
            ];
        }

        $recipients = self::normalizeAddresses(isset($params['to']) ? $params['to'] : null, 'to');
        $subject = trim((string) (isset($params['subject']) ? $params['subject'] : ''));
        $body = isset($params['body']) ? (string) $params['body'] : '';

        if ($subject === '') {
            throw new InvalidArgumentException('subject e obrigatorio');
        }

        if ($body === '') {
            throw new InvalidArgumentException('body e obrigatorio');
        }

        $from = self::resolveFrom($params);
        $isHtml = ! array_key_exists('is_html', $params) || (bool) $params['is_html'];

        $mail = new PHPMailer(true);
        self::configureSmtp($mail);

        $mail->setFrom($from['email'], $from['name']);
        self::addAddresses($mail, $recipients, 'addAddress');

        if (! empty($params['reply_to'])) {
            $reply = self::normalizeAddresses($params['reply_to'], 'reply_to');
            if (! empty($reply[0])) {
                $mail->addReplyTo($reply[0]['email'], $reply[0]['name']);
            }
        }

        if (! empty($params['cc'])) {
            self::addAddresses($mail, self::normalizeAddresses($params['cc'], 'cc'), 'addCC');
        }

        if (! empty($params['bcc'])) {
            self::addAddresses($mail, self::normalizeAddresses($params['bcc'], 'bcc'), 'addBCC');
        }

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if ($isHtml && ! empty($params['alt_body'])) {
            $mail->AltBody = (string) $params['alt_body'];
        } elseif (! $isHtml) {
            $mail->AltBody = '';
        }

        try {
            $mail->send();
        } catch (Exception $e) {
            Log::warning('MailService: falha ao enviar e-mail', [
                'subject' => $subject,
                'to' => array_column($recipients, 'email'),
                'message' => $e->getMessage(),
            ]);

            throw new Exception('Erro ao enviar e-mail: ' . $e->getMessage(), 0, $e);
        }

        return ['sent' => true];
    }

    /**
     * Atalho para HTML.
     *
     * @param string|array $to
     * @param string $subject
     * @param string $bodyHtml
     * @param array $options from, from_name, reply_to, cc, bcc, alt_body
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function sendHtml($to, $subject, $bodyHtml, array $options = [])
    {
        $params = array_merge($options, [
            'to' => $to,
            'subject' => $subject,
            'body' => $bodyHtml,
            'is_html' => true,
        ]);

        return self::send($params);
    }

    /**
     * Notifica mudanca de status de cedente (delega a CedenteMailService).
     *
     * @param \App\Cedente|int $cedente
     * @param string|null $oldStatus
     * @param string $newStatus
     * @param array $options
     * @return array{sent: bool, skipped?: bool, reason?: string}
     */
    public static function notifyCedenteStatusChange($cedente, $oldStatus, $newStatus, array $options = [])
    {
        return CedenteMailService::notifyStatusChange($cedente, $oldStatus, $newStatus, $options);
    }

    /**
     * Layout HTML padrao (jobs, cedentes, check-in).
     *
     * @param string $title
     * @param string $bodyHtml
     * @param string|null $buttonUrl
     * @param string|null $buttonLabel
     * @param string|null $buttonColor hex sem #
     * @param string|null $footerHtml
     * @return string
     */
    public static function renderHtmlLayout($title, $bodyHtml, $buttonUrl = null, $buttonLabel = null, $buttonColor = '286ea7', $footerHtml = null)
    {
        $buttonBlock = '';
        if ($buttonUrl !== null && $buttonUrl !== '' && $buttonLabel !== null && $buttonLabel !== '') {
            $safeUrl = htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8');
            $safeLabel = htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8');
            $color = preg_replace('/[^0-9a-fA-F]/', '', (string) $buttonColor);
            if ($color === '') {
                $color = '286ea7';
            }

            $buttonBlock = '
                <br />
                <table align="center" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="background-color: #' . $color . '; border-radius: 4px;">
                            <a href="' . $safeUrl . '" target="_blank" style="display: block; padding: 12px 20px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: bold; font-family: Arial, sans-serif;">'
                                . $safeLabel .
                            '</a>
                        </td>
                    </tr>
                </table>';
        }

        $footer = $footerHtml !== null && $footerHtml !== ''
            ? $footerHtml
            : 'Caso tenha alguma duvida, nao hesite em nos contatar.<br /><strong>Think</strong>';

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>' . $safeTitle . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0; background-color: #0056b3; color: #ffffff;">
                <h1 style="margin: 0; font-size: 24px; font-weight: bold;">' . $safeTitle . '</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 30px; color: #333333; text-align: center; font-size: 16px;">
                <div style="margin: 0;">' . $bodyHtml . '</div>'
                . $buttonBlock .
            '</td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px; font-size: 12px; color: #777777; background-color: #f5f5f5;">'
                . $footer .
            '</td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * URL base do front (APP_URL_FRONT ou APP_URL).
     *
     * @return string
     */
    public static function frontendBaseUrl()
    {
        $url = env('APP_URL_FRONT', env('APP_URL', 'http://localhost:4200'));

        return rtrim((string) $url, '/');
    }

    /**
     * @param PHPMailer $mail
     */
    private static function configureSmtp(PHPMailer $mail)
    {
        $mail->isSMTP();
        $mail->Host = (string) config('mail.host', 'smtp.mailgun.org');
        $mail->Port = (int) config('mail.port', 587);
        $mail->CharSet = 'UTF-8';

        $username = config('mail.username');
        $password = config('mail.password');
        $mail->SMTPAuth = $username !== null && $username !== '' && $username !== 'null';

        if ($mail->SMTPAuth) {
            $mail->Username = (string) $username;
            $mail->Password = (string) $password;
        }

        $encryption = config('mail.encryption');
        if ($encryption !== null && $encryption !== '' && $encryption !== 'null') {
            $mail->SMTPSecure = (string) $encryption;
        }
    }

    /**
     * @param array $params
     * @return array{email: string, name: string}
     */
    private static function resolveFrom(array $params)
    {
        $defaultAddress = (string) config('mail.from.address', 'no-reply@example.com');
        $defaultName = (string) config('mail.from.name', 'Think');

        $email = trim((string) (isset($params['from']) ? $params['from'] : $defaultAddress));
        $name = trim((string) (isset($params['from_name']) ? $params['from_name'] : $defaultName));

        if ($email === '') {
            throw new InvalidArgumentException('from e obrigatorio (ou configure MAIL_FROM_ADDRESS)');
        }

        return [
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
        ];
    }

    /**
     * @param mixed $value
     * @param string $field
     * @return array<int, array{email: string, name: string}>
     */
    private static function normalizeAddresses($value, $field)
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException($field . ' e obrigatorio');
        }

        $items = is_array($value) ? $value : [$value];
        $out = [];

        foreach ($items as $item) {
            if (is_string($item)) {
                $email = trim($item);
                if ($email === '') {
                    continue;
                }
                $out[] = ['email' => $email, 'name' => ''];
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $email = trim((string) (isset($item['email']) ? $item['email'] : ''));
            if ($email === '') {
                continue;
            }

            $out[] = [
                'email' => $email,
                'name' => trim((string) (isset($item['name']) ? $item['name'] : '')),
            ];
        }

        if (empty($out)) {
            throw new InvalidArgumentException($field . ' invalido ou vazio');
        }

        return $out;
    }

    /**
     * @param PHPMailer $mail
     * @param array<int, array{email: string, name: string}> $addresses
     * @param string $method addAddress|addCC|addBCC
     */
    private static function addAddresses(PHPMailer $mail, array $addresses, $method)
    {
        foreach ($addresses as $row) {
            $mail->{$method}($row['email'], $row['name']);
        }
    }

    /**
     * @param string $key
     * @param bool $default
     * @return bool
     */
    private static function envFlag($key, $default)
    {
        $value = env($key, $default);
        if (is_string($value)) {
            return ! in_array(strtolower($value), ['false', '0', 'no', 'off', ''], true);
        }

        return (bool) $value;
    }
}
