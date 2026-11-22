<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

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

        $modes = [null];
        // Se STARTTLS (587) quebrar o contexto SSL do PHP-FPM, tenta SMTPS (465).
        if (self::shouldTrySmtpsFallback()) {
            $modes[] = 'smtps465';
        }

        $lastException = null;
        foreach ($modes as $mode) {
            try {
                $mail = self::buildMailer($params, $from, $recipients, $isHtml, $mode);
                $mail->send();

                return ['sent' => true];
            } catch (Exception $e) {
                $lastException = $e;
                Log::warning('MailService: falha ao enviar e-mail', [
                    'subject' => $subject,
                    'to' => array_column($recipients, 'email'),
                    'mode' => $mode === null ? 'default' : $mode,
                    'php' => PHP_VERSION,
                    'sapi' => PHP_SAPI,
                    'openssl' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : null,
                    'message' => $e->getMessage(),
                ]);

                if ($mode === null && self::isSslContextFailure($e) && in_array('smtps465', $modes, true)) {
                    continue;
                }

                break;
            }
        }

        throw new Exception(
            'Erro ao enviar e-mail: ' . ($lastException ? $lastException->getMessage() : 'falha desconhecida'),
            0,
            $lastException
        );
    }

    /**
     * @param array $params
     * @param array{email: string, name: string} $from
     * @param array<int, array{email: string, name: string}> $recipients
     * @param bool $isHtml
     * @param string|null $mode
     * @return PHPMailer
     */
    private static function buildMailer(array $params, array $from, array $recipients, $isHtml, $mode = null)
    {
        $mail = new PHPMailer(true);
        $mail->setSMTPInstance(new TlsSmtp());
        self::configureSmtp($mail, $mode);

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
        $mail->Subject = trim((string) (isset($params['subject']) ? $params['subject'] : ''));
        $mail->Body = isset($params['body']) ? (string) $params['body'] : '';

        if ($isHtml && ! empty($params['alt_body'])) {
            $mail->AltBody = (string) $params['alt_body'];
        } elseif (! $isHtml) {
            $mail->AltBody = '';
        }

        return $mail;
    }

    /**
     * @param \Exception $e
     * @return bool
     */
    private static function isSslContextFailure($e)
    {
        $message = $e->getMessage();

        return stripos($message, 'SSL context creation failure') !== false
            || stripos($message, 'Could not connect to SMTP host') !== false;
    }

    /**
     * @return bool
     */
    private static function shouldTrySmtpsFallback()
    {
        $port = (int) env('PHP_MAIL_PORT', config('mail.port', 587));
        $encryption = strtolower(self::credentialString(env('PHP_MAIL_SMTP_SECURY', config('mail.encryption'))));

        return $port === 587 || $encryption === 'tls' || $encryption === '';
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
     * SMTP igual ao fluxo de jobs/tasks que ja funcionava localmente.
     *
     * Prioridade:
     * 1) MAIL_* (config/mail.php) — jobs feedback, check-in, cedente
     * 2) PHP_MAIL_* — legado Task::sendMailTask (Gmail), se MAIL_USERNAME estiver vazio
     *
     * @param PHPMailer $mail
     * @param string|null $mode null = env padrao; smtps465 = ssl:// host na porta 465
     */
    private static function configureSmtp(PHPMailer $mail, $mode = null)
    {
        $mail->isSMTP();

        $username = self::credentialString(config('mail.username'));
        $password = self::credentialString(config('mail.password'));
        $host = self::credentialString(config('mail.host'));
        $port = (int) config('mail.port', 587);
        $encryption = self::credentialString(config('mail.encryption'));
        $charset = 'UTF-8';
        $smtpAuth = $username !== '';

        // Fallback para as vars antigas do modulo de jobs/tasks (Task.php).
        if ($username === '') {
            $username = self::credentialString(env('PHP_MAIL_USERNAME'));
            $password = self::credentialString(env('PHP_MAIL_PASSWORD'));
            $phpHost = self::credentialString(env('PHP_MAIL_HOST'));
            if ($phpHost !== '') {
                $host = $phpHost;
            }
            $phpPort = env('PHP_MAIL_PORT');
            if ($phpPort !== null && $phpPort !== '') {
                $port = (int) $phpPort;
            }
            // Nome historico com typo no Task.php: PHP_MAIL_SMTP_SECURY
            $encryption = self::credentialString(env('PHP_MAIL_SMTP_SECURY'));
            $authFlag = env('PHP_MAIL_SMTP_AUTH');
            if ($authFlag !== null && $authFlag !== '') {
                $smtpAuth = filter_var($authFlag, FILTER_VALIDATE_BOOLEAN);
            } else {
                $smtpAuth = $username !== '';
            }
            $phpCharset = self::credentialString(env('PHP_MAIL_CHARSET'));
            if ($phpCharset !== '') {
                $charset = $phpCharset;
            }
        }

        if ($host === '') {
            $host = 'smtp.mailgun.org';
        }

        if ($mode === 'smtps465') {
            $port = 465;
            $encryption = PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->Host = $host;
        $mail->Port = $port > 0 ? $port : 587;
        $mail->CharSet = $charset;
        $mail->SMTPAuth = $smtpAuth || $username !== '';
        $mail->SMTPOptions = self::smtpStreamOptions();

        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }

        if ($encryption !== '') {
            $mail->SMTPSecure = $encryption;
        }
    }

    /**
     * Contexto SSL usado no connect e no STARTTLS.
     *
     * @return array
     */
    private static function smtpStreamOptions()
    {
        $ssl = [
            'crypto_method' => self::preferredTlsCryptoMethod(),
        ];

        $cafile = self::credentialString(env('MAIL_CAFILE', ini_get('openssl.cafile') ?: ''));
        if ($cafile === '' || ! is_readable($cafile)) {
            foreach ([
                '/etc/ssl/certs/ca-certificates.crt',
                '/etc/pki/tls/certs/ca-bundle.crt',
                '/etc/ssl/cert.pem',
            ] as $candidate) {
                if (is_readable($candidate)) {
                    $cafile = $candidate;
                    break;
                }
            }
        }

        if ($cafile !== '' && is_readable($cafile)) {
            $ssl['cafile'] = $cafile;
        }

        return ['ssl' => $ssl];
    }

    /**
     * @return int
     */
    public static function preferredTlsCryptoMethod()
    {
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
            }

            return $method;
        }

        if (defined('STREAM_CRYPTO_METHOD_ANY_CLIENT')) {
            return STREAM_CRYPTO_METHOD_ANY_CLIENT;
        }

        return STREAM_CRYPTO_METHOD_TLS_CLIENT;
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

    /**
     * Normaliza credenciais do .env (trim; trata "null" literal como vazio).
     * Aspas no .env (ex.: MAIL_PASSWORD="abc def") sao removidas pelo Dotenv — ok.
     *
     * @param mixed $value
     * @return string
     */
    private static function credentialString($value)
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ($text === '' || strtolower($text) === 'null') {
            return '';
        }

        return $text;
    }
}

/**
 * STARTTLS alinhado ao teste que funcionou no servidor:
 * usa o crypto_method do contexto SSL da conexao (SMTPOptions).
 * Sem terceiro argumento em stream_socket_enable_crypto, o PHP herda
 * o contexto e evita "SSL context creation failure" em alguns PHP-FPM.
 */
class TlsSmtp extends SMTP
{
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        set_error_handler([$this, 'errorHandler']);
        // Sem 3o argumento: herda crypto_method do SMTPOptions da conexao.
        // Depois do STARTTLS so ha uma chance; retentar no mesmo socket nao ajuda.
        $crypto_ok = stream_socket_enable_crypto($this->smtp_conn, true);
        restore_error_handler();

        return (bool) $crypto_ok;
    }
}
