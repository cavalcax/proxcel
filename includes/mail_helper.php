<?php
/**
 * Proxcel - Helper de Envio de E-mail
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/mail.php';

class MailHelper {

    /**
     * Envia e-mail
     */
    public static function send($to, $subject, $htmlBody) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
            'Reply-To: ' . MAIL_FROM_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];

        if (MAIL_USE_SMTP) {
            return self::sendSMTP($to, $subject, $htmlBody);
        }

        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /**
     * Envia e-mail via SMTP (usando sockets)
     */
    private static function sendSMTP($to, $subject, $htmlBody) {
        try {
            $smtp = fsockopen(
                (MAIL_SMTP_PORT == 465 ? 'ssl://' : '') . MAIL_SMTP_HOST,
                MAIL_SMTP_PORT,
                $errno, $errstr, 30
            );

            if (!$smtp) {
                error_log("SMTP Connection failed: $errstr ($errno)");
                return false;
            }

            $response = fgets($smtp, 515);

            // EHLO
            fputs($smtp, "EHLO " . MAIL_SMTP_HOST . "\r\n");
            $response = '';
            while ($line = fgets($smtp, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) == ' ') break;
            }

            // STARTTLS for port 587
            if (MAIL_SMTP_PORT == 587) {
                fputs($smtp, "STARTTLS\r\n");
                fgets($smtp, 515);
                stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                fputs($smtp, "EHLO " . MAIL_SMTP_HOST . "\r\n");
                $response = '';
                while ($line = fgets($smtp, 515)) {
                    $response .= $line;
                    if (substr($line, 3, 1) == ' ') break;
                }
            }

            // AUTH LOGIN
            fputs($smtp, "AUTH LOGIN\r\n");
            fgets($smtp, 515);

            fputs($smtp, base64_encode(MAIL_SMTP_USER) . "\r\n");
            fgets($smtp, 515);

            fputs($smtp, base64_encode(MAIL_SMTP_PASS) . "\r\n");
            $authResponse = fgets($smtp, 515);

            if (substr($authResponse, 0, 3) != '235') {
                error_log("SMTP Auth failed: $authResponse");
                fclose($smtp);
                return false;
            }

            // MAIL FROM
            fputs($smtp, "MAIL FROM:<" . MAIL_FROM_EMAIL . ">\r\n");
            fgets($smtp, 515);

            // RCPT TO
            fputs($smtp, "RCPT TO:<" . $to . ">\r\n");
            fgets($smtp, 515);

            // DATA
            fputs($smtp, "DATA\r\n");
            fgets($smtp, 515);

            // Build message
            $message = "To: $to\r\n";
            $message .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
            $message .= "Subject: $subject\r\n";
            $message .= "MIME-Version: 1.0\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "\r\n";
            $message .= $htmlBody;
            $message .= "\r\n.\r\n";

            fputs($smtp, $message);
            $response = fgets($smtp, 515);

            fputs($smtp, "QUIT\r\n");
            fclose($smtp);

            return substr($response, 0, 3) == '250';
        } catch (\Exception $e) {
            error_log("SMTP Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envia e-mail de recuperação de senha
     */
    public static function sendPasswordReset($email, $token, $nome) {
        $url = APP_URL . '/pages/redefinir_senha.php?token=' . urlencode($token);
        
        $html = getEmailTemplate(
            'Recuperação de Senha',
            '<p>Olá, <strong>' . htmlspecialchars($nome) . '</strong>!</p>
             <p>Recebemos uma solicitação para redefinir sua senha no ' . APP_NAME . '.</p>
             <p>Clique no botão abaixo para criar uma nova senha. Este link é válido por <strong>1 hora</strong>.</p>
             <p style="margin-top: 20px; color: #9CA3AF; font-size: 13px;">
                Se você não solicitou esta alteração, ignore este e-mail. Sua senha permanecerá a mesma.
             </p>',
            'Redefinir Senha',
            $url
        );

        return self::send($email, APP_NAME . ' - Recuperação de Senha', $html);
    }

    /**
     * Envia e-mail de boas-vindas
     */
    public static function sendWelcome($email, $nome, $senhaTemporaria = null) {
        $conteudo = '<p>Olá, <strong>' . htmlspecialchars($nome) . '</strong>!</p>
                     <p>Sua conta no <strong>' . APP_NAME . '</strong> foi criada com sucesso.</p>';
        
        if ($senhaTemporaria) {
            $conteudo .= '<p>Seus dados de acesso:</p>
                          <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0;">
                              <p style="margin: 5px 0;"><strong>E-mail:</strong> ' . htmlspecialchars($email) . '</p>
                              <p style="margin: 5px 0;"><strong>Senha:</strong> ' . htmlspecialchars($senhaTemporaria) . '</p>
                          </div>
                          <p style="color: #EF4444;"><strong>Importante:</strong> Altere sua senha no primeiro acesso.</p>';
        }

        $html = getEmailTemplate(
            'Bem-vindo ao ' . APP_NAME,
            $conteudo,
            'Acessar o Sistema',
            APP_URL . '/pages/login.php'
        );

        return self::send($email, APP_NAME . ' - Bem-vindo!', $html);
    }

    /**
     * Envia notificação de novo documento
     */
    public static function sendDocumentNotification($email, $nome, $documentoNome) {
        $html = getEmailTemplate(
            'Novo Documento Disponível',
            '<p>Olá, <strong>' . htmlspecialchars($nome) . '</strong>!</p>
             <p>Um novo documento foi disponibilizado para você:</p>
             <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 15px 0;">
                 <p style="margin: 5px 0; font-size: 16px;"><strong>📄 ' . htmlspecialchars($documentoNome) . '</strong></p>
             </div>
             <p>Acesse o sistema para visualizar e fazer download.</p>',
            'Ver Documentos',
            APP_URL . '/pages/dashboard.php'
        );

        return self::send($email, APP_NAME . ' - Novo Documento Disponível', $html);
    }
}
