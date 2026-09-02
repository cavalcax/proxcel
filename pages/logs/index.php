<?php
/**
 * Proxcel - Visualização de Logs do Sistema (LGPD)
 */
$pageTitle = 'Logs de Auditoria';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('logs.visualizar');
Security::logAccess('logs.index', 'Visualização de logs de auditoria');

$tipoLog = get('tipo', 'acesso');

if ($tipoLog === 'alteracao') {
    $logs = db()->fetchAll("SELECT * FROM " . tb('logs_alteracao') . " ORDER BY criado_em DESC LIMIT 500");
} else {
    $logs = db()->fetchAll("SELECT * FROM " . tb('logs_acesso') . " ORDER BY criado_em DESC LIMIT 500");
}
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Logs de Auditoria', 'url' => '#']
]) ?>

<!-- Tabs e Filtros -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-4">
    <div class="flex items-center gap-2 bg-gray-200 p-1 rounded-xl">
        <a href="?tipo=acesso" class="px-4 py-2 text-xs font-bold rounded-lg transition <?= $tipoLog === 'acesso' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
            <i class="fas fa-sign-in-alt mr-1"></i> Acessos & Logins
        </a>
        <a href="?tipo=alteracao" class="px-4 py-2 text-xs font-bold rounded-lg transition <?= $tipoLog === 'alteracao' ? 'bg-white text-gray-800 shadow-sm' : 'text-gray-600 hover:text-gray-900' ?>">
            <i class="fas fa-edit mr-1"></i> Alterações de Dados
        </a>
    </div>

    <div class="search-box w-full sm:w-72">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="logSearchInput" placeholder="Digite para filtrar logs..."
               class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
    </div>
</div>

<div class="card animate-fadeInUp">
    <div class="table-container">
        <table class="data-table" id="logsDataTable">
            <thead>
                <tr>
                    <th>Data/Hora</th>
                    <th>Usuário</th>
                    <th>Ação</th>
                    <?php if ($tipoLog === 'alteracao'): ?>
                    <th>Tabela</th>
                    <th>Registro ID</th>
                    <?php else: ?>
                    <th>Detalhes</th>
                    <?php endif; ?>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <tr id="noLogResultsRow" class="hidden">
                    <td colspan="<?= $tipoLog === 'alteracao' ? 6 : 5 ?>" class="text-center py-12 text-gray-400">
                        <i class="fas fa-search text-4xl mb-3 block text-gray-300"></i>
                        <p class="font-medium text-gray-600">Nenhum registro de log corresponde aos critérios.</p>
                    </td>
                </tr>

                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="<?= $tipoLog === 'alteracao' ? 6 : 5 ?>" class="text-center py-10 text-gray-400">
                        <i class="fas fa-shield-alt text-4xl mb-3 block"></i>
                        <p>Nenhum registro de log encontrado.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="whitespace-nowrap text-xs text-gray-500 font-mono"><?= dateTimeBr($l['criado_em']) ?></td>
                    <td>
                        <p class="font-semibold text-gray-800 text-sm"><?= e($l['usuario_nome'] ?: 'Anônimo / Visitante') ?></p>
                        <p class="text-[10px] text-gray-400"><?= e($l['usuario_tipo'] ?? '-') ?></p>
                    </td>
                    <td>
                        <span class="badge badge-info font-mono text-[11px]"><?= e($l['acao']) ?></span>
                    </td>
                    <?php if ($tipoLog === 'alteracao'): ?>
                    <td class="font-mono text-xs font-medium text-gray-700"><?= e($l['tabela']) ?></td>
                    <td class="font-mono text-xs font-bold text-proxcel">#<?= $l['registro_id'] ?></td>
                    <?php else: ?>
                    <td class="text-xs text-gray-600 max-w-xs truncate"><?= e($l['descricao'] ?: $l['pagina'] ?: '-') ?></td>
                    <?php endif; ?>
                    <td class="font-mono text-xs text-gray-500"><?= e($l['ip']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let logsTable;
document.addEventListener('DOMContentLoaded', () => {
    logsTable = new ProxcelTable('#logsDataTable', {
        perPage: 15,
        searchInput: '#logSearchInput'
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
