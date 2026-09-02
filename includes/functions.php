<?php
/**
 * Proxcel - Funções Utilitárias
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Redireciona para URL
 */
function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '"></noscript>';
    exit;
}

/**
 * Define mensagem flash na sessão
 */
function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Obtém e limpa mensagem flash
 */
function getFlash($type) {
    $message = $_SESSION['flash_' . $type] ?? null;
    unset($_SESSION['flash_' . $type]);
    return $message;
}

/**
 * Retorna JSON e encerra
 */
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Verifica se é requisição POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Verifica se é requisição AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtém parâmetro GET sanitizado
 */
function get($key, $default = '') {
    return isset($_GET[$key]) ? Security::sanitize($_GET[$key]) : $default;
}

/**
 * Obtém parâmetro POST sanitizado
 */
function post($key, $default = '') {
    return isset($_POST[$key]) ? Security::sanitize($_POST[$key]) : $default;
}

/**
 * Formata data BR
 */
function dateBr($date) {
    if (!$date) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Formata data e hora BR
 */
function dateTimeBr($date) {
    if (!$date) return '-';
    return date('d/m/Y H:i', strtotime($date));
}

/**
 * Formata CNPJ
 */
function formatCnpj($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) !== 14) return $cnpj;
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

/**
 * Formata CPF
 */
function formatCpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

/**
 * Formata telefone
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    }
    if (strlen($phone) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    }
    return $phone;
}

/**
 * Gera paginação
 */
function paginate($total, $perPage, $currentPage, $baseUrl) {
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => ($currentPage - 1) * $perPage,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_url' => $baseUrl . '?page=' . ($currentPage - 1),
        'next_url' => $baseUrl . '?page=' . ($currentPage + 1),
    ];
}

/**
 * Renderiza HTML da paginação
 */
function renderPagination($pagination) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav class="flex items-center justify-between px-2 py-3">';
    $html .= '<div class="text-sm text-gray-500">Mostrando ' . 
              (($pagination['current_page'] - 1) * $pagination['per_page'] + 1) . 
              ' a ' . min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) . 
              ' de ' . $pagination['total'] . ' registros</div>';
    $html .= '<div class="flex gap-1">';

    // Botão anterior
    if ($pagination['has_prev']) {
        $html .= '<a href="' . e($pagination['prev_url']) . '" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="fas fa-chevron-left"></i></a>';
    }

    // Números de página
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? 'bg-proxcel text-white border-proxcel' : 'bg-white border-gray-300 hover:bg-gray-50';
        $url = str_replace('page=' . $pagination['current_page'], 'page=' . $i, $pagination['prev_url']);
        $url = preg_replace('/page=\d+/', 'page=' . $i, $pagination['next_url']);
        $html .= '<a href="' . e($url) . '" class="px-3 py-2 text-sm border rounded-lg transition ' . $active . '">' . $i . '</a>';
    }

    // Botão próximo
    if ($pagination['has_next']) {
        $html .= '<a href="' . e($pagination['next_url']) . '" class="px-3 py-2 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"><i class="fas fa-chevron-right"></i></a>';
    }

    $html .= '</div></nav>';
    return $html;
}

/**
 * Retorna saudação baseada na hora
 */
function greeting() {
    $hour = (int) date('H');
    if ($hour < 12) return 'Bom dia';
    if ($hour < 18) return 'Boa tarde';
    return 'Boa noite';
}

/**
 * Retorna iniciais do nome
 */
function getInitials($name) {
    $parts = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }
    return $initials;
}

/**
 * Gera breadcrumb
 */
function breadcrumb($items) {
    $html = '<nav class="flex items-center gap-2 text-sm text-gray-500 mb-4">';
    $total = count($items);
    foreach ($items as $i => $item) {
        if ($i > 0) {
            $html .= '<i class="fas fa-chevron-right text-xs text-gray-300"></i>';
        }
        if ($i === $total - 1) {
            $html .= '<span class="text-gray-800 font-medium">' . e($item['label']) . '</span>';
        } else {
            $html .= '<a href="' . e($item['url']) . '" class="hover:text-proxcel transition">' . e($item['label']) . '</a>';
        }
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Gera opções de estado BR
 */
function estadosBR() {
    return [
        'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas',
        'BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal',
        'ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão',
        'MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais',
        'PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco',
        'PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte',
        'RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima',
        'SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins'
    ];
}
