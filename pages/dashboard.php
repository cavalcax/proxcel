<?php
/**
 * Proxcel - Dashboard
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../includes/upload.php';

Security::logAccess('dashboard', 'Acesso ao dashboard');

// ============================================================
// DADOS DO DASHBOARD (SISTEMA)
// ============================================================
if (Auth::isSistema()) {
    $totalClientes = db()->fetch("SELECT COUNT(*) as total FROM " . tb('clientes') . " WHERE status = 'ativo'")['total'];
    $totalUsuarios = db()->fetch("SELECT COUNT(*) as total FROM " . tb('usuarios') . " WHERE status = 'ativo'")['total'];
    $totalUsuariosClientes = db()->fetch("SELECT COUNT(*) as total FROM " . tb('usuarios_clientes') . " WHERE status = 'ativo'")['total'];
    $totalDocumentos = db()->fetch("SELECT COUNT(*) as total FROM " . tb('documentos') . " WHERE status = 'ativo'")['total'];

    $documentosRecentes = db()->fetchAll(
        "SELECT d.*, c.razao_social, c.nome_fantasia 
         FROM " . tb('documentos') . " d 
         JOIN " . tb('clientes') . " c ON c.id = d.cliente_id 
         WHERE d.status = 'ativo' 
         ORDER BY d.criado_em DESC LIMIT 10"
    );

    $clientesRecentes = db()->fetchAll(
        "SELECT * FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY criado_em DESC LIMIT 5"
    );

    // Dados para gráfico - documentos por mês (últimos 6 meses)
    $docsPorMes = db()->fetchAll(
        "SELECT DATE_FORMAT(criado_em, '%Y-%m') as mes, COUNT(*) as total 
         FROM " . tb('documentos') . " 
         WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
         GROUP BY DATE_FORMAT(criado_em, '%Y-%m') 
         ORDER BY mes"
    );

    // Documentos por categoria
    $docsPorCategoria = db()->fetchAll(
        "SELECT cat.nome, COUNT(d.id) as total, cat.cor 
         FROM " . tb('categorias_documentos') . " cat 
         LEFT JOIN " . tb('documentos') . " d ON d.categoria_id = cat.id AND d.status = 'ativo' 
         WHERE cat.status = 'ativo' 
         GROUP BY cat.id 
         ORDER BY total DESC"
    );
}

// ============================================================
// DADOS DO DASHBOARD (CLIENTE)
// ============================================================
if (Auth::isCliente()) {
    $clienteId = $currentUser['cliente_id'];

    $totalDocumentosCliente = db()->fetch(
        "SELECT COUNT(*) as total FROM " . tb('documentos') . " WHERE cliente_id = ? AND status = 'ativo'",
        [$clienteId]
    )['total'];

    $documentosCliente = db()->fetchAll(
        "SELECT d.*, cat.nome as categoria_nome, cat.cor as categoria_cor 
         FROM " . tb('documentos') . " d 
         LEFT JOIN " . tb('categorias_documentos') . " cat ON cat.id = d.categoria_id 
         WHERE d.cliente_id = ? AND d.status = 'ativo' 
         ORDER BY d.criado_em DESC LIMIT 20",
        [$clienteId]
    );

    $clienteInfo = db()->fetch(
        "SELECT * FROM " . tb('clientes') . " WHERE id = ?",
        [$clienteId]
    );

    $docsPorCatCliente = db()->fetchAll(
        "SELECT cat.nome, COUNT(d.id) as total, cat.cor 
         FROM " . tb('categorias_documentos') . " cat 
         LEFT JOIN " . tb('documentos') . " d ON d.categoria_id = cat.id AND d.status = 'ativo' AND d.cliente_id = ? 
         WHERE cat.status = 'ativo' 
         GROUP BY cat.id 
         ORDER BY total DESC",
        [$clienteId]
    );
}

$meses = ['01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez'];
?>

<!-- Welcome -->
<div class="mb-6 animate-fadeInUp">
    <h2 class="text-2xl font-bold text-gray-800"><?= greeting() ?>, <?= e(explode(' ', $currentUser['nome'])[0]) ?>!
    </h2>
    <p class="text-gray-500 mt-1">
        <?php if (Auth::isCliente()): ?>
            Confira seus documentos e informações.
        <?php else: ?>
            Aqui está o resumo do seu sistema.
        <?php endif; ?>
    </p>
</div>

<?php if (Auth::isSistema()): ?>
    <!-- ============================================================
     DASHBOARD SISTEMA
     ============================================================ -->

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Clientes -->
        <div class="card card-stat p-5 animate-fadeInUp stagger-1">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Clientes</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?= $totalClientes ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-building text-blue-500 text-lg"></i>
                </div>
            </div>
            <a href="<?= APP_URL ?>/pages/clientes/index.php"
                class="text-xs text-proxcel font-semibold mt-3 inline-block hover:underline">Ver todos →</a>
        </div>

        <!-- Usuários -->
        <div class="card card-stat p-5 animate-fadeInUp stagger-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Usuários</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?= $totalUsuarios + $totalUsuariosClientes ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 flex items-center justify-center">
                    <i class="fas fa-users text-purple-500 text-lg"></i>
                </div>
            </div>
            <a href="<?= APP_URL ?>/pages/usuarios/index.php"
                class="text-xs text-proxcel font-semibold mt-3 inline-block hover:underline">Ver todos →</a>
        </div>

        <!-- Documentos -->
        <div class="card card-stat p-5 animate-fadeInUp stagger-3">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Documentos</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?= $totalDocumentos ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
                    <i class="fas fa-file-alt text-proxcel text-lg"></i>
                </div>
            </div>
            <a href="<?= APP_URL ?>/pages/documentos/index.php"
                class="text-xs text-proxcel font-semibold mt-3 inline-block hover:underline">Ver todos →</a>
        </div>

        <!-- Upload -->
        <div class="card card-stat p-5 animate-fadeInUp stagger-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ação Rápida</p>
                    <p class="text-sm font-medium text-gray-600 mt-2">Novo Upload</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
                    <i class="fas fa-cloud-upload-alt text-amber-500 text-lg"></i>
                </div>
            </div>
            <a href="<?= APP_URL ?>/pages/documentos/upload.php"
                class="text-xs text-proxcel font-semibold mt-3 inline-block hover:underline">Enviar arquivo →</a>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Chart: Documentos por Mês -->
        <div class="card p-5 animate-fadeInUp">
            <h3 class="text-sm font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-proxcel mr-1"></i> Documentos por Mês
            </h3>
            <div style="height: 250px;">
                <canvas id="chartDocsMes"></canvas>
            </div>
        </div>

        <!-- Chart: Documentos por Categoria -->
        <div class="card p-5 animate-fadeInUp">
            <h3 class="text-sm font-bold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-proxcel mr-1"></i> Por Categoria
            </h3>
            <div style="height: 250px;">
                <canvas id="chartDocsCat"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Documents -->
    <div class="card animate-fadeInUp">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-800">
                    <i class="fas fa-clock text-proxcel mr-1"></i> Documentos Recentes
                </h3>
                <a href="<?= APP_URL ?>/pages/documentos/index.php"
                    class="text-xs text-proxcel font-semibold hover:underline">Ver todos</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th class="hidden sm:table-cell">Cliente</th>
                        <th class="hidden md:table-cell">Tipo</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documentosRecentes)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-8 text-gray-400">
                                <i class="fas fa-folder-open text-3xl mb-2"></i>
                                <p>Nenhum documento encontrado.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documentosRecentes as $doc): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center">
                                            <i class="fas <?= Upload::getIcon($doc['extensao']) ?>"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800 text-sm"><?= e($doc['nome']) ?></p>
                                            <p class="text-xs text-gray-400"><?= Upload::formatSize($doc['tamanho']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell">
                                    <span
                                        class="text-sm text-gray-600"><?= e($doc['nome_fantasia'] ?: $doc['razao_social']) ?></span>
                                </td>
                                <td class="hidden md:table-cell">
                                    <span class="badge badge-gray"><?= strtoupper(e($doc['extensao'])) ?></span>
                                </td>
                                <td>
                                    <span class="text-sm text-gray-500"><?= dateTimeBr($doc['criado_em']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Charts Script -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Documentos por Mês
            const docsMesCtx = document.getElementById('chartDocsMes');
            if (docsMesCtx) {
                new Chart(docsMesCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php
                        $labels = [];
                        foreach ($docsPorMes as $d) {
                            $parts = explode('-', $d['mes']);
                            $labels[] = "'" . ($meses[$parts[1]] ?? $parts[1]) . '/' . substr($parts[0], 2) . "'";
                        }
                        echo implode(',', $labels);
                        ?>],
                        datasets: [{
                            label: 'Documentos',
                            data: [<?= implode(',', array_column($docsPorMes, 'total')) ?>],
                            backgroundColor: 'rgba(62, 175, 92, 0.2)',
                            borderColor: '#3EAF5C',
                            borderWidth: 2,
                            borderRadius: 8,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, ticks: { stepSize: 1 } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Documentos por Categoria
            const docsCatCtx = document.getElementById('chartDocsCat');
            if (docsCatCtx) {
                new Chart(docsCatCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?= implode(',', array_map(function ($c) {
                            return "'" . e($c['nome']) . "'"; }, $docsPorCategoria)) ?>],
                        datasets: [{
                            data: [<?= implode(',', array_column($docsPorCategoria, 'total')) ?>],
                            backgroundColor: [<?= implode(',', array_map(function ($c) {
                                return "'" . e($c['cor']) . "'"; }, $docsPorCategoria)) ?>],
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right', labels: { padding: 15, usePointStyle: true, pointStyle: 'circle' } }
                        },
                        cutout: '65%'
                    }
                });
            }
        });
    </script>

<?php else: ?>
    <!-- ============================================================
     DASHBOARD CLIENTE
     ============================================================ -->

    <!-- Client Info Card -->
    <div class="card p-5 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-4">
            <div
                class="w-14 h-14 rounded-xl bg-gradient-to-br from-proxcel to-proxcel-dark flex items-center justify-center text-white text-xl font-bold shadow-lg">
                <?= getInitials($clienteInfo['razao_social'] ?? 'C') ?>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-800">
                    <?= e($clienteInfo['nome_fantasia'] ?: $clienteInfo['razao_social']) ?></h3>
                <p class="text-sm text-gray-500">
                    <?php if ($clienteInfo['cnpj']): ?>
                        CNPJ: <?= e($clienteInfo['cnpj']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="card card-stat p-5 animate-fadeInUp stagger-1">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Documentos</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2"><?= $totalDocumentosCliente ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center">
                    <i class="fas fa-file-alt text-proxcel text-lg"></i>
                </div>
            </div>
        </div>

        <div class="card card-stat p-5 animate-fadeInUp stagger-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Categorias</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2">
                        <?= count(array_filter($docsPorCatCliente, fn($c) => $c['total'] > 0)) ?></p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                    <i class="fas fa-tags text-blue-500 text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents List -->
    <div class="card animate-fadeInUp">
        <div class="p-5 border-b border-gray-100">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-sm font-bold text-gray-800">
                    <i class="fas fa-folder-open text-proxcel mr-1"></i> Meus Documentos
                </h3>
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="searchInput" placeholder="Buscar..." class="form-control !py-2 text-sm"
                        style="padding-left: 2.5rem;">
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th class="hidden sm:table-cell">Categoria</th>
                        <th class="hidden md:table-cell">Data</th>
                        <th class="text-center">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documentosCliente)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-10 text-gray-400">
                                <i class="fas fa-folder-open text-4xl mb-3 block"></i>
                                <p class="text-base">Nenhum documento disponível.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($documentosCliente as $doc): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
                                            <i class="fas <?= Upload::getIcon($doc['extensao']) ?> text-lg"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?= e($doc['nome']) ?></p>
                                            <p class="text-xs text-gray-400"><?= Upload::formatSize($doc['tamanho']) ?> •
                                                <?= strtoupper(e($doc['extensao'])) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell">
                                    <?php if ($doc['categoria_nome']): ?>
                                        <span class="badge"
                                            style="background: <?= e($doc['categoria_cor']) ?>15; color: <?= e($doc['categoria_cor']) ?>;">
                                            <?= e($doc['categoria_nome']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-gray">Sem categoria</span>
                                    <?php endif; ?>
                                </td>
                                <td class="hidden md:table-cell">
                                    <span
                                        class="text-sm text-gray-500"><?= dateBr($doc['data_documento'] ?: $doc['criado_em']) ?></span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= APP_URL ?>/pages/documentos/download.php?id=<?= $doc['id'] ?>"
                                        class="btn btn-sm btn-primary" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new ProxcelTable('#dataTable', {
                perPage: 10,
                searchInput: '#searchInput'
            });
        });
    </script>

<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>