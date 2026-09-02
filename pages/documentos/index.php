<?php
/**
 * Proxcel - Lista de Documentos
 */
$pageTitle = 'Documentos';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/upload.php';

Permissions::requirePermission('documentos.visualizar');

$where = "WHERE d.status = 'ativo'";
$params = [];

// Cliente só vê seus docs
if (Auth::isCliente()) {
    $where .= " AND d.cliente_id = ?";
    $params[] = $currentUser['cliente_id'];
}

$documentos = db()->fetchAll(
    "SELECT d.*, c.razao_social, c.nome_fantasia, cat.nome as categoria_nome, cat.cor as categoria_cor 
     FROM " . tb('documentos') . " d 
     JOIN " . tb('clientes') . " c ON c.id = d.cliente_id 
     LEFT JOIN " . tb('categorias_documentos') . " cat ON cat.id = d.categoria_id
     {$where} 
     ORDER BY d.criado_em DESC",
    $params
);

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");
$filtroClienteId = (int) get('cliente_id');
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Documentos', 'url' => '#']
]) ?>

<!-- Header com Ação Principal -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Gestão de Documentos</h2>
        <p class="text-xs text-gray-500">Visualize, ordene, busque e faça o download dos arquivos importados</p>
    </div>
    <?php if (Permissions::has('documentos.criar')): ?>
        <div class="flex items-center gap-2">
            <a href="<?= APP_URL ?>/pages/documentos/importar_mensal.php" class="btn btn-secondary shadow-sm text-sm border-emerald-300 text-emerald-700 hover:bg-emerald-50">
                <i class="fas fa-calendar-alt text-emerald-600"></i> Importação Mensal
            </a>
            <a href="<?= APP_URL ?>/pages/documentos/importar_trimestral.php" class="btn btn-secondary shadow-sm text-sm border-blue-300 text-blue-700 hover:bg-blue-50">
                <i class="fas fa-chart-pie text-blue-600"></i> Importação Trimestral
            </a>
            <a href="<?= APP_URL ?>/pages/documentos/upload.php" class="btn btn-primary shadow-sm text-sm">
                <i class="fas fa-cloud-upload-alt"></i> Novo Upload
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Card de Filtros Dinâmicos -->
<div class="filter-card">
    <div
        class="grid grid-cols-1 sm:grid-cols-2 <?= Auth::isSistema() ? 'lg:grid-cols-12' : 'lg:grid-cols-2' ?> gap-3 items-end">

        <!-- Campo Busca -->
        <div class="<?= Auth::isSistema() ? 'lg:col-span-5' : 'lg:col-span-1' ?>">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Buscar</label>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="docSearchInput" placeholder="Digite para buscar título, arquivo..."
                    class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
            </div>
        </div>

        <!-- Filtro Categoria -->
        <div class="<?= Auth::isSistema() ? 'lg:col-span-3' : 'lg:col-span-1' ?>">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block">Categoria</label>
            <select id="docCategoriaFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>">
                        <?= e($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filtro Empresa / Cliente (Apenas Sistema) -->
        <?php if (Auth::isSistema()): ?>
            <div class="lg:col-span-4">
                <div class="flex justify-between items-center mb-1">
                    <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Empresa /
                        Cliente</label>
                    <button type="button" id="btnResetFilters" onclick="resetAllDocFilters()"
                        class="text-[11px] text-gray-400 hover:text-red-500 transition <?= $filtroClienteId ? '' : 'hidden' ?>">
                        <i class="fas fa-times-circle"></i> Limpar filtros
                    </button>
                </div>
                <select id="docClienteFilter" class="select2 form-control !py-2 text-sm w-full"
                    data-placeholder="Todas as empresas">
                    <option value="">Todas as empresas</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filtroClienteId === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= e($c['nome_fantasia'] ?: $c['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabela de Documentos -->
<div class="card animate-fadeInUp">
    <div class="table-container">
        <table class="data-table" id="docDataTable">
            <thead>
                <tr>
                    <th>Documento</th>
                    <?php if (Auth::isSistema()): ?>
                        <th class="hidden sm:table-cell">Cliente</th>
                    <?php endif; ?>
                    <th class="hidden md:table-cell">Categoria</th>
                    <th class="hidden lg:table-cell">Tamanho</th>
                    <th class="hidden md:table-cell">Data</th>
                    <th class="text-center" data-no-sort>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Linha vazia de feedback dinâmico -->
                <tr id="noDocResultsRow" class="hidden">
                    <td colspan="<?= Auth::isSistema() ? 6 : 5 ?>" class="text-center py-12 text-gray-400">
                        <i class="fas fa-search text-4xl mb-3 block text-gray-300"></i>
                        <p class="font-medium text-gray-600">Nenhum documento corresponde aos filtros selecionados.</p>
                        <button type="button" onclick="resetAllDocFilters()" class="btn btn-sm btn-secondary mt-3">
                            <i class="fas fa-times mr-1"></i> Limpar Filtros
                        </button>
                    </td>
                </tr>

                <?php if (empty($documentos)): ?>
                    <tr id="emptyInitialRow">
                        <td colspan="<?= Auth::isSistema() ? 6 : 5 ?>" class="text-center py-12 text-gray-400">
                            <i class="fas fa-folder-open text-4xl mb-3 block text-gray-300"></i>
                            <p class="font-medium text-gray-600">Nenhum documento cadastrado no momento.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($documentos as $doc): ?>
                        <tr class="document-row" data-cliente-id="<?= $doc['cliente_id'] ?>"
                            data-categoria-id="<?= $doc['categoria_id'] ?? '' ?>">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center shrink-0 border border-gray-100">
                                        <i class="fas <?= Upload::getIcon($doc['extensao']) ?> text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm"><?= e($doc['nome']) ?></p>
                                        <p class="text-xs text-gray-400"><?= e($doc['nome_original']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <?php if (Auth::isSistema()): ?>
                                <td class="hidden sm:table-cell text-sm text-gray-600 font-medium">
                                    <?= e($doc['nome_fantasia'] ?: $doc['razao_social']) ?>
                                </td>
                            <?php endif; ?>
                            <td class="hidden md:table-cell">
                                <?php if ($doc['categoria_nome']): ?>
                                    <span class="badge"
                                        style="background: <?= e($doc['categoria_cor']) ?>15; color: <?= e($doc['categoria_cor']) ?>; font-weight: 600;">
                                        <?= e($doc['categoria_nome']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Sem categoria</span>
                                <?php endif; ?>
                            </td>
                            <td class="hidden lg:table-cell text-xs text-gray-500 font-mono">
                                <?= Upload::formatSize($doc['tamanho']) ?>
                            </td>
                            <td class="hidden md:table-cell text-sm text-gray-500">
                                <?= dateBr($doc['data_documento'] ?: $doc['criado_em']) ?>
                            </td>
                            <td>
                                <div class="flex items-center justify-center gap-1">
                                    <?php if (Permissions::has('documentos.download')): ?>
                                        <a href="<?= APP_URL ?>/pages/documentos/download.php?id=<?= $doc['id'] ?>"
                                            class="btn btn-sm btn-primary btn-icon" title="Download">
                                            <i class="fas fa-download text-xs"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (Permissions::has('documentos.excluir')): ?>
                                        <button onclick="deleteDoc(<?= $doc['id'] ?>)"
                                            class="btn btn-sm btn-icon text-red-500 hover:bg-red-50" title="Excluir">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
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
    let docTable;

    document.addEventListener('DOMContentLoaded', () => {
        const categoriaSelect = document.getElementById('docCategoriaFilter');
        const clienteSelect = $('#docClienteFilter');
        const resetBtn = document.getElementById('btnResetFilters');

        docTable = new ProxcelTable('#docDataTable', {
            perPage: 10,
            searchInput: '#docSearchInput',
            customFilter: function (row) {
                const selectedCategoria = categoriaSelect.value || '';
                const selectedCliente = clienteSelect.length ? (clienteSelect.val() || '') : '';

                const rowCliente = row.getAttribute('data-cliente-id') || '';
                const rowCategoria = row.getAttribute('data-categoria-id') || '';

                const matchCategoria = !selectedCategoria || rowCategoria === selectedCategoria;
                const matchCliente = !selectedCliente || rowCliente === selectedCliente;

                const isFiltered = Boolean(document.getElementById('docSearchInput').value || selectedCategoria || selectedCliente);
                if (resetBtn) resetBtn.classList.toggle('hidden', !isFiltered);

                return matchCategoria && matchCliente;
            }
        });

        categoriaSelect.addEventListener('change', () => docTable.applyFilters());

        if (clienteSelect.length) {
            clienteSelect.on('change select2:select select2:clear', () => docTable.applyFilters());
            if (clienteSelect.val()) {
                docTable.applyFilters();
            }
        }
    });

    function resetAllDocFilters() {
        const searchInput = document.getElementById('docSearchInput');
        const categoriaSelect = document.getElementById('docCategoriaFilter');
        const clienteSelect = $('#docClienteFilter');

        if (searchInput) searchInput.value = '';
        if (categoriaSelect) categoriaSelect.value = '';
        if (clienteSelect.length) clienteSelect.val('').trigger('change');

        if (docTable) docTable.applyFilters();
    }

    function deleteDoc(id) {
        confirmDelete(() => {
            ajaxRequest('<?= APP_URL ?>/pages/documentos/actions.php', { action: 'delete', id: id })
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