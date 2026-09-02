<?php
/**
 * Proxcel - Lista de Usuários de Clientes
 */
$pageTitle = 'Usuários de Clientes';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('usuarios_clientes.visualizar');

$usuarios = db()->fetchAll(
    "SELECT uc.*, c.razao_social, c.nome_fantasia 
     FROM " . tb('usuarios_clientes') . " uc 
     JOIN " . tb('clientes') . " c ON c.id = uc.cliente_id 
     ORDER BY uc.nome ASC"
);

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Usuários de Clientes', 'url' => '#']
]) ?>

<!-- Header com Ação -->
<div class="flex items-center justify-between gap-4 mb-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Usuários de Clientes</h2>
        <p class="text-xs text-gray-500">Gerencie os acessos de usuários vinculados às empresas cadastradas</p>
    </div>
    <?php if (Permissions::has('usuarios_clientes.criar')): ?>
        <a href="<?= APP_URL ?>/pages/usuarios_clientes/form.php" class="btn btn-primary shadow-sm text-sm">
            <i class="fas fa-plus"></i> Novo Usuário
        </a>
    <?php endif; ?>
</div>

<!-- Card de Filtros Dinâmicos -->
<div class="filter-card">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">

        <div class="lg:col-span-8">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Buscar</label>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="userSearchInput" placeholder="Digite para buscar por nome, e-mail..."
                    class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Filtrar por
                    Empresa</label>
                <button type="button" id="btnResetUserFilters" onclick="resetUserFilters()"
                    class="text-[11px] text-gray-400 hover:text-red-500 transition hidden">
                    <i class="fas fa-times-circle"></i> Limpar filtros
                </button>
            </div>
            <select id="userClienteFilter" class="select2 form-control !py-2 text-sm w-full"
                data-placeholder="Todas as empresas">
                <option value="">Todas as empresas</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= e($c['nome_fantasia'] ?: $c['razao_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- Tabela -->
<div class="card animate-fadeInUp">
    <div class="table-container">
        <table class="data-table" id="userDataTable">
            <thead>
                <tr>
                    <th>Usuário</th>
                    <th class="hidden sm:table-cell">Empresa / Cliente</th>
                    <th class="hidden md:table-cell">E-mail</th>
                    <th>Status</th>
                    <th class="text-center" data-no-sort>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Linha vazia de feedback dinâmico -->
                <tr id="noUserResultsRow" class="hidden">
                    <td colspan="5" class="text-center py-12 text-gray-400">
                        <i class="fas fa-search text-4xl mb-3 block text-gray-300"></i>
                        <p class="font-medium text-gray-600">Nenhum usuário corresponde aos filtros selecionados.</p>
                        <button type="button" onclick="resetUserFilters()" class="btn btn-sm btn-secondary mt-3">
                            <i class="fas fa-times mr-1"></i> Limpar Filtros
                        </button>
                    </td>
                </tr>

                <?php if (empty($usuarios)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-12 text-gray-400">
                            <i class="fas fa-user-tie text-4xl mb-3 block text-gray-300"></i>
                            <p class="font-medium text-gray-600">Nenhum usuário encontrado.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $u): ?>
                        <tr class="user-row" data-cliente-id="<?= $u['cliente_id'] ?>">
                            <td>
                                <p class="font-semibold text-gray-800 text-sm"><?= e($u['nome']) ?></p>
                            </td>
                            <td class="hidden sm:table-cell text-sm text-gray-600 font-medium">
                                <?= e($u['nome_fantasia'] ?: $u['razao_social']) ?>
                            </td>
                            <td class="hidden md:table-cell text-sm text-gray-600">
                                <?= e($u['email']) ?>
                            </td>
                            <td>
                                <span class="badge <?= $u['status'] === 'ativo' ? 'badge-success' : 'badge-danger' ?>">
                                    <i class="fas fa-circle text-[6px]"></i> <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-1">
                                    <?php if (Permissions::has('usuarios_clientes.editar')): ?>
                                        <a href="<?= APP_URL ?>/pages/usuarios_clientes/form.php?id=<?= $u['id'] ?>"
                                            class="btn btn-sm btn-secondary btn-icon" title="Editar"><i
                                                class="fas fa-pen text-xs"></i></a>
                                        <a href="<?= APP_URL ?>/pages/usuarios/permissoes.php?id=<?= $u['id'] ?>&tipo_acesso=cliente"
                                            class="btn btn-sm btn-secondary btn-icon" title="Permissões"><i
                                                class="fas fa-shield-alt text-xs"></i></a>
                                    <?php endif; ?>
                                    <?php if (Permissions::has('usuarios_clientes.excluir')): ?>
                                        <button onclick="deleteUser(<?= $u['id'] ?>)"
                                            class="btn btn-sm btn-icon text-red-500 hover:bg-red-50" title="Excluir"><i
                                                class="fas fa-trash text-xs"></i></button>
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
    let userTable;

    document.addEventListener('DOMContentLoaded', () => {
        const clienteSelect = $('#userClienteFilter');
        const resetBtn = document.getElementById('btnResetUserFilters');

        userTable = new ProxcelTable('#userDataTable', {
            perPage: 10,
            searchInput: '#userSearchInput',
            customFilter: function (row) {
                const selectedCliente = clienteSelect.length ? (clienteSelect.val() || '') : '';
                const rowCliente = row.getAttribute('data-cliente-id') || '';
                const matchCliente = !selectedCliente || rowCliente === selectedCliente;

                const isFiltered = Boolean(document.getElementById('userSearchInput').value || selectedCliente);
                if (resetBtn) resetBtn.classList.toggle('hidden', !isFiltered);

                return matchCliente;
            }
        });

        if (clienteSelect.length) {
            clienteSelect.on('change select2:select select2:clear', () => userTable.applyFilters());
        }
    });

    function resetUserFilters() {
        const searchInput = document.getElementById('userSearchInput');
        const clienteSelect = $('#userClienteFilter');

        if (searchInput) searchInput.value = '';
        if (clienteSelect.length) clienteSelect.val('').trigger('change');

        if (userTable) userTable.applyFilters();
    }

    function deleteUser(id) {
        confirmDelete(() => {
            ajaxRequest('<?= APP_URL ?>/pages/usuarios_clientes/actions.php', { action: 'delete', id: id })
                .done(res => {
                    if (res.success) {
                        showSuccess(res.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(res.message);
                    }
                })
                .fail(() => showError('Erro ao processar requisição.'));
        });
    }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>