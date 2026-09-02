<?php
/**
 * Proxcel - Central de Relatórios
 */
$pageTitle = 'Relatórios';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('relatorios.visualizar');
Security::logAccess('relatorios.index', 'Acesso à central de relatórios');

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Relatórios', 'url' => '#']
]) ?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Relatório 1: Clientes Cadastrados -->
    <div class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all animate-fadeInUp stagger-1">
        <div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl mb-4">
                <i class="fas fa-building"></i>
            </div>
            <h3 class="text-base font-bold text-gray-800 mb-2">Relatório de Clientes</h3>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                Listagem completa dos clientes cadastrados com dados de contato, status e data de registro.
            </p>
        </div>
        <form action="<?= APP_URL ?>/pages/relatorios/gerar.php" method="GET" target="_blank">
            <input type="hidden" name="tipo" value="clientes">
            <div class="mb-4">
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Status</label>
                <select name="status" class="form-control !py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="ativo">Apenas Ativos</option>
                    <option value="inativo">Apenas Inativos</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2.5 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> Gerar Relatório
            </button>
        </form>
    </div>

    <!-- Relatório 2: Documentos por Cliente -->
    <div class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all animate-fadeInUp stagger-2">
        <div>
            <div class="w-12 h-12 rounded-xl bg-green-50 text-proxcel flex items-center justify-center text-xl mb-4">
                <i class="fas fa-folder-open"></i>
            </div>
            <h3 class="text-base font-bold text-gray-800 mb-2">Relatório de Documentos</h3>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                Relação analítica de documentos importados por cliente, categoria e período.
            </p>
        </div>
        <form action="<?= APP_URL ?>/pages/relatorios/gerar.php" method="GET" target="_blank">
            <input type="hidden" name="tipo" value="documentos">
            <div class="space-y-3 mb-4">
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Cliente</label>
                    <select name="cliente_id" class="form-control select2 !py-2 text-sm">
                        <option value="">Todos os clientes</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Categoria</label>
                    <select name="categoria_id" class="form-control !py-2 text-sm">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2.5 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> Gerar Relatório
            </button>
        </form>
    </div>

    <!-- Relatório 3: Logs de Acesso e Auditoria -->
    <div class="card p-6 flex flex-col justify-between hover:shadow-lg transition-all animate-fadeInUp stagger-3">
        <div>
            <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl mb-4">
                <i class="fas fa-history"></i>
            </div>
            <h3 class="text-base font-bold text-gray-800 mb-2">Logs de Auditoria (LGPD)</h3>
            <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                Relatório de conformidade com histórico de acessos, logins e ações executadas no sistema.
            </p>
        </div>
        <form action="<?= APP_URL ?>/pages/relatorios/gerar.php" method="GET" target="_blank">
            <input type="hidden" name="tipo" value="logs">
            <div class="space-y-3 mb-4">
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Período (dias)</label>
                    <select name="dias" class="form-control !py-2 text-sm">
                        <option value="7">Últimos 7 dias</option>
                        <option value="15">Últimos 15 dias</option>
                        <option value="30" selected>Últimos 30 dias</option>
                        <option value="90">Últimos 90 dias</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2.5 text-sm">
                <i class="fas fa-file-pdf mr-1"></i> Gerar Relatório
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
