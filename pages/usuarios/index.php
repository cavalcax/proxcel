<?php
/**
 * Proxcel - Lista de Usuários do Sistema
 */
$pageTitle = 'Usuários';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('usuarios.visualizar');

$usuarios = db()->fetchAll("SELECT * FROM " . tb('usuarios') . " ORDER BY nome ASC");
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Usuários', 'url' => '#']
]) ?>

<!-- Header com Ação Principal -->
<div class="flex items-center justify-between gap-4 mb-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Usuários do Sistema</h2>
        <p class="text-xs text-gray-500">Gestão de administradores e operadores do sistema</p>
    </div>
    <?php if (Permissions::has('usuarios.criar')): ?>
        <a href="<?= APP_URL ?>/pages/usuarios/form.php" class="btn btn-primary shadow-sm text-sm">
            <i class="fas fa-plus"></i> Novo Usuário
        </a>
    <?php endif; ?>
</div>

<!-- Card de Filtros Dinâmicos -->
<div class="filter-card">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
        <div>
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Buscar</label>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="userSysSearchInput" placeholder="Digite para buscar por nome ou e-mail..." 
                       class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
            </div>
        </div>
        <div>
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Tipo de Usuário</label>
            <select id="userSysTipoFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todos os tipos</option>
                <option value="admin">Administrador</option>
                <option value="operador">Operador</option>
            </select>
        </div>
    </div>
</div>

<div class="card animate-fadeInUp">
    <div class="table-container">
        <table class="data-table" id="userSysDataTable">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th class="hidden sm:table-cell">E-mail</th>
                    <th>Tipo</th>
                    <th class="hidden md:table-cell">Último Acesso</th>
                    <th>Status</th>
                    <th class="text-center" data-no-sort>Ações</th>
                </tr>
            </thead>
            <tbody>
                <tr id="noUserSysResultsRow" class="hidden">
                    <td colspan="6" class="text-center py-12 text-gray-400">
                        <i class="fas fa-search text-4xl mb-3 block text-gray-300"></i>
                        <p class="font-medium text-gray-600">Nenhum usuário encontrado com os filtros selecionados.</p>
                    </td>
                </tr>

                <?php if (empty($usuarios)): ?>
                    <tr><td colspan="6" class="text-center py-10 text-gray-400"><i class="fas fa-users text-4xl mb-3 block"></i><p>Nenhum usuário encontrado.</p></td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                    <tr class="usersys-row" data-tipo="<?= e($u['tipo']) ?>">
                        <td>
                            <p class="font-semibold text-gray-800 text-sm"><?= e($u['nome']) ?></p>
                        </td>
                        <td class="hidden sm:table-cell text-sm text-gray-600"><?= e($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['tipo'] === 'admin' ? 'badge-info' : 'badge-gray' ?>">
                                <?= ucfirst($u['tipo']) ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell text-sm text-gray-500 font-mono text-xs"><?= $u['ultimo_acesso'] ? dateTimeBr($u['ultimo_acesso']) : 'Nunca' ?></td>
                        <td>
                            <span class="badge <?= $u['status'] === 'ativo' ? 'badge-success' : ($u['status'] === 'bloqueado' ? 'badge-warning' : 'badge-danger') ?>">
                                <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center justify-center gap-1">
                                <?php if (Permissions::has('usuarios.editar')): ?>
                                <a href="<?= APP_URL ?>/pages/usuarios/form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Editar"><i class="fas fa-pen text-xs"></i></a>
                                <a href="<?= APP_URL ?>/pages/usuarios/permissoes.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary btn-icon" title="Permissões"><i class="fas fa-shield-alt text-xs"></i></a>
                                <?php endif; ?>
                                <?php if (Permissions::has('usuarios.excluir') && $u['id'] != $currentUser['id']): ?>
                                <button onclick="deleteUsuario(<?= $u['id'] ?>)" class="btn btn-sm btn-icon text-red-500 hover:bg-red-50" title="Excluir"><i class="fas fa-trash text-xs"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let userSysTable;

document.addEventListener('DOMContentLoaded', () => {
    const tipoSelect = document.getElementById('userSysTipoFilter');

    userSysTable = new ProxcelTable('#userSysDataTable', {
        perPage: 10,
        searchInput: '#userSysSearchInput',
        customFilter: function(row) {
            const selectedTipo = (tipoSelect.value || '').toLowerCase();
            const rowTipo = (row.getAttribute('data-tipo') || '').toLowerCase();
            return !selectedTipo || rowTipo === selectedTipo;
        }
    });

    tipoSelect.addEventListener('change', () => userSysTable.applyFilters());
});

function deleteUsuario(id) {
    confirmDelete(() => {
        ajaxRequest('<?= APP_URL ?>/pages/usuarios/actions.php', { action: 'delete', id: id })
        .done(res => { if (res.success) { showSuccess(res.message); setTimeout(() => location.reload(), 1000); } else { showError(res.message); }})
        .fail(() => showError('Erro ao processar requisição.'));
    });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
