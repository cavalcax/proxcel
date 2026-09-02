<?php
/**
 * Proxcel - Lista de Clientes
 */
$pageTitle = 'Clientes';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('clientes.visualizar');
Security::logAccess('clientes.lista', 'Listagem de clientes');

$clientes = db()->fetchAll("SELECT * FROM " . tb('clientes') . " ORDER BY razao_social ASC");
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");
$hoje = date('Y-m-d');
$maxSizeMb = UPLOAD_MAX_SIZE / (1024 * 1024);
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Clientes', 'url' => '#']
]) ?>

<!-- Header com Ação Principal -->
<div class="flex items-center justify-between gap-4 mb-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Clientes & Empresas</h2>
        <p class="text-xs text-gray-500">Gestão, ordenação e cadastro de empresas clientes e contatos</p>
    </div>
    <?php if (Permissions::has('clientes.criar')): ?>
        <a href="<?= APP_URL ?>/pages/clientes/form.php" class="btn btn-primary shadow-sm text-sm">
            <i class="fas fa-plus"></i> Novo Cliente
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
                <input type="text" id="clienteSearchInput" placeholder="Digite para buscar razão social, fantasia, CNPJ, e-mail..." 
                       class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
            </div>
        </div>

        <div class="lg:col-span-4">
            <div class="flex justify-between items-center mb-1">
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider block">Status</label>
                <button type="button" id="btnResetClienteFilters" onclick="resetClienteFilters()" class="text-[11px] text-gray-400 hover:text-red-500 transition hidden">
                    <i class="fas fa-times-circle"></i> Limpar filtros
                </button>
            </div>
            <select id="clienteStatusFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todos os status</option>
                <option value="ativo">Ativo</option>
                <option value="inativo">Inativo</option>
            </select>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card animate-fadeInUp">
    <div class="table-container">
        <table class="data-table" id="clienteDataTable">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th class="hidden sm:table-cell">CNPJ</th>
                    <th class="hidden md:table-cell">E-mail</th>
                    <th class="hidden lg:table-cell">Telefone / WhatsApp</th>
                    <th>Status</th>
                    <th class="text-center" data-no-sort>Ações</th>
                </tr>
            </thead>
            <tbody>
                <!-- Linha vazia de feedback dinâmico -->
                <tr id="noClienteResultsRow" class="hidden">
                    <td colspan="6" class="text-center py-12 text-gray-400">
                        <i class="fas fa-search text-4xl mb-3 block text-gray-300"></i>
                        <p class="font-medium text-gray-600">Nenhum cliente corresponde aos filtros informados.</p>
                        <button type="button" onclick="resetClienteFilters()" class="btn btn-sm btn-secondary mt-3">
                            <i class="fas fa-times mr-1"></i> Limpar Filtros
                        </button>
                    </td>
                </tr>

                <?php if (empty($clientes)): ?>
                <tr>
                    <td colspan="6" class="text-center py-10 text-gray-400">
                        <i class="fas fa-building text-4xl mb-3 block"></i>
                        <p>Nenhum cliente encontrado.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($clientes as $cliente): ?>
                <?php 
                    $telExibicao = $cliente['celular'] ?: $cliente['telefone'];
                    $telDigits = preg_replace('/[^0-9]/', '', $telExibicao ?? '');
                ?>
                <tr class="cliente-row" data-status="<?= e($cliente['status']) ?>">
                    <td>
                        <div>
                            <p class="font-semibold text-gray-800"><?= e($cliente['nome_fantasia'] ?: $cliente['razao_social']) ?></p>
                            <?php if ($cliente['nome_fantasia']): ?>
                            <p class="text-xs text-gray-400"><?= e($cliente['razao_social']) ?></p>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="hidden sm:table-cell text-sm text-gray-600"><?= e($cliente['cnpj'] ?: $cliente['cpf'] ?: '-') ?></td>
                    <td class="hidden md:table-cell text-sm text-gray-600"><?= e($cliente['email']) ?></td>
                    <td class="hidden lg:table-cell text-sm">
                        <?php if ($telDigits): ?>
                            <a href="https://wa.me/55<?= $telDigits ?>" target="_blank" rel="noopener noreferrer" 
                               class="inline-flex items-center gap-1.5 text-green-600 hover:text-green-700 font-medium hover:underline transition" 
                               title="Conversar no WhatsApp">
                                <i class="fab fa-whatsapp text-green-500 text-base"></i>
                                <span><?= e($telExibicao) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $cliente['status'] === 'ativo' ? 'badge-success' : 'badge-danger' ?>">
                            <i class="fas fa-circle text-[6px]"></i>
                            <?= ucfirst($cliente['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="flex items-center justify-center gap-1">
                            <?php if (Permissions::has('documentos.visualizar')): ?>
                            <a href="<?= APP_URL ?>/pages/documentos/index.php?cliente_id=<?= $cliente['id'] ?>" 
                               class="btn btn-sm btn-icon text-blue-600 hover:bg-blue-50" title="Ver Documentos">
                                <i class="fas fa-folder-open text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Permissions::has('documentos.criar')): ?>
                            <button onclick="openUploadModal(<?= $cliente['id'] ?>, '<?= e(addslashes($cliente['nome_fantasia'] ?: $cliente['razao_social'])) ?>')" 
                                    class="btn btn-sm btn-icon text-proxcel hover:bg-green-50 hover:text-proxcel-dark" title="Enviar Documento">
                                <i class="fas fa-cloud-upload-alt text-xs"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (Permissions::has('clientes.editar')): ?>
                            <a href="<?= APP_URL ?>/pages/clientes/form.php?id=<?= $cliente['id'] ?>" 
                               class="btn btn-sm btn-secondary btn-icon" title="Editar">
                                <i class="fas fa-pen text-xs"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Permissions::has('clientes.excluir')): ?>
                            <button onclick="deleteCliente(<?= $cliente['id'] ?>)" 
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

<!-- Modal de Envio Rápido de Documento -->
<div id="uploadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm hidden p-4">
    <div class="card w-full max-w-lg bg-white rounded-2xl shadow-2xl overflow-hidden animate-fadeInUp">
        <div class="flex items-center justify-between p-5 border-b border-gray-100 bg-gray-50/50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-50 text-proxcel flex items-center justify-center text-lg">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-gray-800">Enviar Documento</h3>
                    <p class="text-xs text-gray-500" id="modalClienteNome">-</p>
                </div>
            </div>
            <button onclick="closeUploadModal()" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg transition">
                <i class="fas fa-times text-base"></i>
            </button>
        </div>

        <form id="modalUploadForm" onsubmit="submitModalUpload(event)" class="p-5 space-y-4">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="cliente_id" id="modalClienteId" value="">

            <div class="form-group">
                <label for="modalDocNome">Nome do Documento <span class="required">*</span></label>
                <input type="text" name="nome" id="modalDocNome" class="form-control" placeholder="Ex: Contrato de Prestação de Serviços" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="form-group">
                    <label for="modalDocCategoria">Categoria</label>
                    <select name="categoria_id" id="modalDocCategoria" class="form-control">
                        <option value="">Selecione...</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= e($cat['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalDocData">Data do Documento</label>
                    <input type="date" name="data_documento" id="modalDocData" value="<?= $hoje ?>" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="modalDocDescricao">Descrição / Observações</label>
                <input type="text" name="descricao" id="modalDocDescricao" class="form-control" placeholder="Opcional...">
            </div>

            <div class="form-group">
                <label>Arquivo <span class="required">*</span></label>
                <div class="upload-zone !py-5" id="modalUploadZone" onclick="document.getElementById('modalFileInput').click()">
                    <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-1 block"></i>
                    <p class="text-xs font-semibold text-gray-600">Clique para selecionar ou arraste o arquivo</p>
                    <p class="text-[10px] text-gray-400 mt-1">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Máx: <?= $maxSizeMb ?>MB)</p>
                    <input type="file" name="arquivo" id="modalFileInput" class="hidden" required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.csv,.txt,.zip,.rar"
                           onchange="handleModalFileSelect(this)">
                </div>
                <div id="modalFileInfo" class="hidden mt-2 p-2.5 bg-gray-50 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 truncate">
                        <i class="fas fa-file text-proxcel text-base"></i>
                        <span id="modalFileName" class="font-medium text-gray-700 truncate"></span>
                    </div>
                    <button type="button" onclick="clearModalFile()" class="text-gray-400 hover:text-red-500 ml-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <div class="form-group pt-1">
                <label class="flex items-center gap-2 cursor-pointer text-xs">
                    <input type="checkbox" name="notificar" value="1" class="w-4 h-4 text-proxcel rounded border-gray-300 focus:ring-proxcel">
                    <span class="text-gray-600">Notificar usuários do cliente por e-mail</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-100">
                <button type="button" onclick="closeUploadModal()" class="btn btn-secondary text-xs py-2 px-3">
                    Cancelar
                </button>
                <button type="submit" id="btnSubmitModalUpload" class="btn btn-primary text-xs py-2 px-4">
                    <i class="fas fa-upload mr-1"></i> Enviar Arquivo
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let clienteTable;

document.addEventListener('DOMContentLoaded', () => {
    const statusSelect = document.getElementById('clienteStatusFilter');
    const resetBtn = document.getElementById('btnResetClienteFilters');

    clienteTable = new ProxcelTable('#clienteDataTable', {
        perPage: 10,
        searchInput: '#clienteSearchInput',
        customFilter: function(row) {
            const selectedStatus = (statusSelect.value || '').toLowerCase();
            const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
            const matchStatus = !selectedStatus || rowStatus === selectedStatus;

            const isFiltered = Boolean(document.getElementById('clienteSearchInput').value || selectedStatus);
            if (resetBtn) resetBtn.classList.toggle('hidden', !isFiltered);

            return matchStatus;
        }
    });

    statusSelect.addEventListener('change', () => clienteTable.applyFilters());
});

function resetClienteFilters() {
    const searchInput = document.getElementById('clienteSearchInput');
    const statusSelect = document.getElementById('clienteStatusFilter');

    if (searchInput) searchInput.value = '';
    if (statusSelect) statusSelect.value = '';

    if (clienteTable) clienteTable.applyFilters();
}

function deleteCliente(id) {
    confirmDelete(() => {
        ajaxRequest('<?= APP_URL ?>/pages/clientes/actions.php', { action: 'delete', id: id })
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

function openUploadModal(clienteId, clienteNome) {
    document.getElementById('modalClienteId').value = clienteId;
    document.getElementById('modalClienteNome').textContent = 'Cliente: ' + clienteNome;
    document.getElementById('modalDocNome').value = '';
    document.getElementById('modalDocDescricao').value = '';
    document.getElementById('modalDocCategoria').value = '';
    document.getElementById('modalDocData').value = '<?= $hoje ?>';
    clearModalFile();
    document.getElementById('uploadModal').classList.remove('hidden');
    setTimeout(() => document.getElementById('modalDocNome').focus(), 150);
}

function closeUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
}

function handleModalFileSelect(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('modalFileName').textContent = file.name;
        document.getElementById('modalFileInfo').classList.remove('hidden');
        if (!document.getElementById('modalDocNome').value) {
            const baseName = file.name.replace(/\.[^/.]+$/, "");
            document.getElementById('modalDocNome').value = baseName;
        }
    }
}

function clearModalFile() {
    document.getElementById('modalFileInput').value = '';
    document.getElementById('modalFileInfo').classList.add('hidden');
}

function submitModalUpload(e) {
    e.preventDefault();
    const form = document.getElementById('modalUploadForm');
    const formData = new FormData(form);
    const btn = document.getElementById('btnSubmitModalUpload');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Enviando...';

    ajaxFormData('<?= APP_URL ?>/pages/documentos/actions.php', formData)
    .done(res => {
        if (res.success) {
            closeUploadModal();
            showSuccess(res.message);
        } else {
            showError(res.message);
        }
    })
    .fail(err => {
        const msg = err.responseJSON?.message || 'Erro ao enviar documento.';
        showError(msg);
    })
    .always(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload mr-1"></i> Enviar Arquivo';
    });
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
