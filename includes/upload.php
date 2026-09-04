<?php
/**
 * Proxcel - Upload Seguro de Arquivos
 * 
 * Validação MIME, renomeação, armazenamento fora da pasta pública.
 */

require_once __DIR__ . '/../config/config.php';

class Upload {

    /**
     * Processa upload de arquivo
     * 
     * @param array $file $_FILES['campo']
     * @param string $subdir Subdiretório dentro de storage/documentos/
     * @return array Resultado do upload
     */
    public static function process($file, $subdir = '') {
        // Verificações básicas
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return self::error(self::getUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        // Verifica tamanho
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $maxMb = UPLOAD_MAX_SIZE / (1024 * 1024);
            return self::error("O arquivo excede o tamanho máximo permitido ({$maxMb}MB).");
        }

        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, UPLOAD_ALLOWED_EXTENSIONS)) {
            return self::error("Tipo de arquivo não permitido (.{$extension}).");
        }

        // Verifica MIME type real
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
            return self::error("O tipo de arquivo detectado ({$mimeType}) não é permitido.");
        }

        // Verifica se é realmente o que diz ser (anti-spoofing)
        if (!self::validateContent($file['tmp_name'], $extension)) {
            return self::error("O conteúdo do arquivo não corresponde à extensão.");
        }

        // Gera nome seguro
        $newName = self::generateFileName($extension);

        // Define diretório
        $uploadDir = UPLOAD_PATH;
        if ($subdir) {
            $uploadDir .= rtrim($subdir, '/') . '/';
        }

        // Cria diretório se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Cria .htaccess de proteção no diretório
        self::protectDirectory($uploadDir);

        // Move arquivo
        $destination = $uploadDir . $newName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return self::error("Erro ao salvar o arquivo. Verifique as permissões.");
        }

        return [
            'success' => true,
            'nome_original' => $file['name'],
            'nome_arquivo' => ($subdir ? $subdir . '/' : '') . $newName,
            'mime_type' => $mimeType,
            'tamanho' => $file['size'],
            'extensao' => $extension,
            'caminho_completo' => $destination
        ];
    }

    /**
     * Gera nome de arquivo seguro
     */
    private static function generateFileName($extension) {
        return date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }

    /**
     * Protege diretório contra qualquer acesso direto via HTTP
     */
    private static function protectDirectory($dir) {
        $htaccess = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, 
                "# Bloqueio total de acesso direto a arquivos\n" .
                "<IfModule mod_authz_core.c>\n" .
                "    Require all denied\n" .
                "</IfModule>\n" .
                "<IfModule !mod_authz_core.c>\n" .
                "    Order deny,allow\n" .
                "    Deny from all\n" .
                "</IfModule>\n" .
                "<FilesMatch \".*\">\n" .
                "    <IfModule mod_authz_core.c>\n" .
                "        Require all denied\n" .
                "    </IfModule>\n" .
                "    <IfModule !mod_authz_core.c>\n" .
                "        Order deny,allow\n" .
                "        Deny from all\n" .
                "    </IfModule>\n" .
                "</FilesMatch>\n"
            );
        }
    }

    /**
     * Valida conteúdo do arquivo contra a extensão
     */
    private static function validateContent($filePath, $extension) {
        $handle = fopen($filePath, 'rb');
        if (!$handle) return false;
        $header = fread($handle, 8);
        fclose($handle);

        $signatures = [
            'pdf' => '%PDF',
            'jpg' => ["\xFF\xD8\xFF"],
            'jpeg' => ["\xFF\xD8\xFF"],
            'png' => ["\x89PNG"],
            'gif' => ['GIF87a', 'GIF89a'],
            'zip' => ["PK\x03\x04"],
            'doc' => ["\xD0\xCF\x11\xE0"],
            'docx' => ["PK\x03\x04"],
            'xls' => ["\xD0\xCF\x11\xE0"],
            'xlsx' => ["PK\x03\x04"],
            'pbix' => ["PK\x03\x04"],
        ];

        if (!isset($signatures[$extension])) {
            return true; // Extensões sem assinatura conhecida são aceitas
        }

        $sigs = is_array($signatures[$extension]) ? $signatures[$extension] : [$signatures[$extension]];
        
        foreach ($sigs as $sig) {
            if (substr($header, 0, strlen($sig)) === $sig) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exclui arquivo do storage
     */
    public static function delete($nomeArquivo) {
        $path = UPLOAD_PATH . $nomeArquivo;
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    /**
     * Retorna caminho completo do arquivo
     */
    public static function getPath($nomeArquivo) {
        return UPLOAD_PATH . $nomeArquivo;
    }

    /**
     * Verifica se arquivo existe
     */
    public static function exists($nomeArquivo) {
        return file_exists(UPLOAD_PATH . $nomeArquivo);
    }

    /**
     * Formata tamanho em bytes para legível
     */
    public static function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Retorna ícone baseado na extensão
     */
    public static function getIcon($extensao) {
        $icons = [
            'pdf' => 'fa-file-pdf text-red-500',
            'doc' => 'fa-file-word text-blue-600',
            'docx' => 'fa-file-word text-blue-600',
            'xls' => 'fa-file-excel text-green-600',
            'xlsx' => 'fa-file-excel text-green-600',
            'csv' => 'fa-file-csv text-green-500',
            'pbix' => 'fa-chart-pie text-amber-500',
            'jpg' => 'fa-file-image text-purple-500',
            'jpeg' => 'fa-file-image text-purple-500',
            'png' => 'fa-file-image text-purple-500',
            'gif' => 'fa-file-image text-purple-500',
            'webp' => 'fa-file-image text-purple-500',
            'txt' => 'fa-file-alt text-gray-500',
            'zip' => 'fa-file-archive text-yellow-500',
            'rar' => 'fa-file-archive text-yellow-500',
        ];
        return $icons[strtolower($extensao)] ?? 'fa-file text-gray-400';
    }

    /**
     * Retorna mensagem de erro do upload
     */
    private static function getUploadError($code) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor.',
            UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido pelo formulário.',
            UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente.',
            UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar o arquivo.',
            UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão.',
        ];
        return $errors[$code] ?? 'Erro desconhecido no upload.';
    }

    /**
     * Retorna erro formatado
     */
    private static function error($message) {
        return [
            'success' => false,
            'message' => $message
        ];
    }
}
