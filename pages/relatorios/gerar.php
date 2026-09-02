<?php
/**
 * Proxcel - Geração e Impressão de Relatórios
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/permissions.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::startSession();
Auth::requireLogin();
Permissions::requirePermission('relatorios.visualizar');

$tipo = get('tipo', 'clientes');
Security::logAccess('relatorio.gerar', "Geração de relatório do tipo: {$tipo}");

$dataEmissao = date('d/m/Y H:i:s');
$usuarioEmissor = $_SESSION['user_nome'] ?? 'Sistema';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Relatório — <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" sizes="96x96" href="<?= APP_URL ?>/assets/icons/favicon-96x96.png">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/icons/favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                font-size: 11pt;
            }

            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>

<body class="bg-gray-100 text-gray-800 p-4 md:p-8">
    <!-- Barra de ações rápida (No print) -->
    <div class="max-w-5xl mx-auto mb-4 flex justify-between items-center bg-white p-4 rounded-xl shadow-sm no-print">
        <a href="<?= APP_URL ?>/pages/relatorios/index.php"
            class="text-sm font-medium text-gray-600 hover:text-green-600">
            <i class="fas fa-arrow-left mr-1"></i> Voltar
        </a>
        <div class="flex gap-2">
            <button onclick="window.print()"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition">
                <i class="fas fa-print mr-1"></i> Imprimir / Salvar PDF
            </button>
        </div>
    </div>

    <!-- Documento do Relatório -->
    <div class="max-w-5xl mx-auto bg-white p-8 md:p-12 rounded-xl shadow-md border border-gray-200">

        <!-- Header do Relatório com Logo Proxcel -->
        <div class="flex justify-between items-center border-b-2 border-green-600 pb-6 mb-6">
            <div>
                <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Proxcel" class="h-12 w-auto mb-2">
                <p class="text-xs text-gray-500">Assessoria Empresarial & Gestão de Documentos</p>
            </div>
            <div class="text-right text-xs text-gray-500 space-y-1">
                <p><strong>Emissão:</strong> <?= $dataEmissao ?></p>
                <p><strong>Operador:</strong> <?= e($usuarioEmissor) ?></p>
                <span class="inline-block bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full font-semibold">
                    Documento Oficial
                </span>
            </div>
        </div>

        <?php if ($tipo === 'clientes'): ?>
            <?php
            $status = get('status');
            $where = "WHERE 1=1";
            $params = [];
            if ($status) {
                $where .= " AND status = ?";
                $params[] = $status;
            }
            $dados = db()->fetchAll("SELECT * FROM " . tb('clientes') . " {$where} ORDER BY razao_social ASC", $params);
            ?>
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 uppercase tracking-wide">Relatório de Clientes</h2>
                <p class="text-xs text-gray-500">Total de registros: <?= count($dados) ?>
                    <?= $status ? "({$status})" : '' ?>
                </p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 border-b border-gray-300 text-gray-700">
                        <th class="py-2.5 px-3 font-semibold">Razão Social / Fantasia</th>
                        <th class="py-2.5 px-3 font-semibold">Documento</th>
                        <th class="py-2.5 px-3 font-semibold">Contato</th>
                        <th class="py-2.5 px-3 font-semibold">Cidade/UF</th>
                        <th class="py-2.5 px-3 font-semibold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($dados)): ?>
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">Nenhum cliente encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dados as $c): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5 px-3">
                                    <p class="font-bold text-gray-800"><?= e($c['razao_social']) ?></p>
                                    <?php if ($c['nome_fantasia']): ?>
                                        <p class="text-[11px] text-gray-500"><?= e($c['nome_fantasia']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3 font-mono"><?= e($c['cnpj'] ?: $c['cpf'] ?: '-') ?></td>
                                <td class="py-2.5 px-3">
                                    <p><?= e($c['email']) ?></p>
                                    <p class="text-[11px] text-gray-500"><?= e($c['telefone'] ?: $c['celular'] ?: '-') ?></p>
                                </td>
                                <td class="py-2.5 px-3"><?= e(($c['cidade'] ?? '-') . '/' . ($c['estado'] ?? '-')) ?></td>
                                <td class="py-2.5 px-3 text-center">
                                    <span
                                        class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $c['status'] === 'ativo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= strtoupper($c['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tipo === 'documentos'): ?>
            <?php
            $clienteId = (int) get('cliente_id');
            $categoriaId = (int) get('categoria_id');
            $where = "WHERE d.status = 'ativo'";
            $params = [];
            if ($clienteId) {
                $where .= " AND d.cliente_id = ?";
                $params[] = $clienteId;
            }
            if ($categoriaId) {
                $where .= " AND d.categoria_id = ?";
                $params[] = $categoriaId;
            }
            $dados = db()->fetchAll(
                "SELECT d.*, c.razao_social, c.nome_fantasia, cat.nome as categoria_nome 
                 FROM " . tb('documentos') . " d 
                 JOIN " . tb('clientes') . " c ON c.id = d.cliente_id 
                 LEFT JOIN " . tb('categorias_documentos') . " cat ON cat.id = d.categoria_id 
                 {$where} 
                 ORDER BY d.criado_em DESC",
                $params
            );
            ?>
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 uppercase tracking-wide">Relatório de Gestão Documental</h2>
                <p class="text-xs text-gray-500">Total de documentos: <?= count($dados) ?></p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 border-b border-gray-300 text-gray-700">
                        <th class="py-2.5 px-3 font-semibold">Documento</th>
                        <th class="py-2.5 px-3 font-semibold">Cliente</th>
                        <th class="py-2.5 px-3 font-semibold">Categoria</th>
                        <th class="py-2.5 px-3 font-semibold">Tamanho / Tipo</th>
                        <th class="py-2.5 px-3 font-semibold">Data Doc</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($dados)): ?>
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">Nenhum documento encontrado.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dados as $d): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5 px-3">
                                    <p class="font-bold text-gray-800"><?= e($d['nome']) ?></p>
                                    <p class="text-[11px] text-gray-500"><?= e($d['nome_original']) ?></p>
                                </td>
                                <td class="py-2.5 px-3 font-medium text-gray-700">
                                    <?= e($d['nome_fantasia'] ?: $d['razao_social']) ?>
                                </td>
                                <td class="py-2.5 px-3"><?= e($d['categoria_nome'] ?: 'Geral') ?></td>
                                <td class="py-2.5 px-3"><?= Upload::formatSize($d['tamanho']) ?> (<?= strtoupper($d['extensao']) ?>)
                                </td>
                                <td class="py-2.5 px-3"><?= dateBr($d['data_documento'] ?: $d['criado_em']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($tipo === 'logs'): ?>
            <?php
            $dias = (int) get('dias', 30);
            $dados = db()->fetchAll(
                "SELECT * FROM " . tb('logs_acesso') . " 
                 WHERE criado_em >= DATE_SUB(NOW(), INTERVAL ? DAY) 
                 ORDER BY criado_em DESC LIMIT 200",
                [$dias]
            );
            ?>
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 uppercase tracking-wide">Relatório de Auditoria e Segurança
                    (LGPD)</h2>
                <p class="text-xs text-gray-500">Histórico de acessos dos últimos <?= $dias ?> dias (limite de 200 eventos)
                </p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 border-b border-gray-300 text-gray-700">
                        <th class="py-2.5 px-3 font-semibold">Data / Hora</th>
                        <th class="py-2.5 px-3 font-semibold">Usuário</th>
                        <th class="py-2.5 px-3 font-semibold">Ação</th>
                        <th class="py-2.5 px-3 font-semibold">IP</th>
                        <th class="py-2.5 px-3 font-semibold">Detalhes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($dados)): ?>
                        <tr>
                            <td colspan="5" class="py-6 text-center text-gray-400">Nenhum log registrado no período.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dados as $l): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2.5 px-3 whitespace-nowrap"><?= dateTimeBr($l['criado_em']) ?></td>
                                <td class="py-2.5 px-3">
                                    <p class="font-bold text-gray-800"><?= e($l['usuario_nome'] ?: 'Anônimo') ?></p>
                                    <p class="text-[10px] text-gray-400"><?= e($l['usuario_tipo'] ?: '-') ?></p>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-green-700"><?= e($l['acao']) ?></td>
                                <td class="py-2.5 px-3 font-mono text-[11px]"><?= e($l['ip']) ?></td>
                                <td class="py-2.5 px-3 text-gray-600"><?= e($l['descricao'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Rodapé do Relatório -->
        <div class="mt-12 pt-4 border-t border-gray-200 flex justify-between items-center text-[10px] text-gray-400">
            <p><?= APP_NAME ?> — Assessoria Empresarial</p>
            <p>Página gerada em <?= $dataEmissao ?></p>
        </div>
    </div>
</body>

</html>