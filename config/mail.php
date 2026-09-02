<?php
/**
 * Proxcel - Configuração de E-mail
 * 
 * Configurações para envio de e-mails via SMTP ou mail() nativo.
 */

require_once __DIR__ . '/config.php';

/**
 * Template HTML base para e-mails
 */
function getEmailTemplate($titulo, $conteudo, $botaoTexto = '', $botaoUrl = '') {
    $botaoHtml = '';
    if ($botaoTexto && $botaoUrl) {
        $botaoHtml = '
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . htmlspecialchars($botaoUrl) . '" 
               style="background-color: #3EAF5C; color: #ffffff; padding: 14px 32px; 
                      text-decoration: none; border-radius: 8px; font-weight: 600;
                      font-size: 16px; display: inline-block;">
                ' . htmlspecialchars($botaoTexto) . '
            </a>
        </div>';
    }

    return '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #2D8A47, #3EAF5C); padding: 30px; text-align: center;">
                    <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 700;">' . APP_NAME . '</h1>
                    <p style="color: rgba(255,255,255,0.85); margin: 5px 0 0; font-size: 14px;">Assessoria Empresarial</p>
                </div>
                
                <!-- Content -->
                <div style="padding: 30px;">
                    <h2 style="color: #2D2D2D; margin: 0 0 15px; font-size: 20px;">' . htmlspecialchars($titulo) . '</h2>
                    <div style="color: #6B7280; font-size: 15px; line-height: 1.6;">
                        ' . $conteudo . '
                    </div>
                    ' . $botaoHtml . '
                </div>
                
                <!-- Footer -->
                <div style="background-color: #f9fafb; padding: 20px 30px; text-align: center; border-top: 1px solid #e5e7eb;">
                    <p style="color: #9CA3AF; font-size: 12px; margin: 0;">
                        &copy; ' . date('Y') . ' ' . APP_NAME . ' - Assessoria Empresarial<br>
                        Este é um e-mail automático. Não responda.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>';
}
