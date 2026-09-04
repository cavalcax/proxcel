<?php
/**
 * Proxcel - Lista de Documentos
 */
$pageTitle = 'Documentos';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/upload.php';

Permissions::requirePermission('documentos.visualizar');

/**
 * Extrai competência estruturada (tipo, mês, trimestre, ano) do documento
 */
function getDocumentCompetencia(array $doc): array {
    $nomeOriginal = $doc['nome_original'] ?? '';
    $nome = $doc['nome'] ?? '';
    $descricao = $doc['descricao'] ?? '';
    $dataDoc = $doc['data_documento'] ?? $doc['criado_em'] ?? '';

    $baseName = pathinfo($nomeOriginal, PATHINFO_FILENAME);
    $parts = explode('_', $baseName);
    $periodRaw = $parts[0] ?? '';

    $tipo = null;
    $ano = null;
    $mes = null;
    $trimestre = null;
    $label = null;

    $mesesNomes = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];

    // 1. Mensal mmaaaa (ex: 052026)
    if (preg_match('/^(\d{2})(\d{4})$/', $periodRaw, $m)) {
        $mNum = (int)$m[1];
        $yNum = (int)$m[2];
        if ($mNum >= 1 && $mNum <= 12 && $yNum >= 1900 && $yNum <= 2100) {
            $tipo = 'mensal';
            $mes = $mNum;
            $ano = $yNum;
            $label = $mesesNomes[$mes] . '/' . $ano;
        }
    }
    // 2. Trimestral: 1Taaaa..4Taaaa
    elseif (preg_match('/^([1-4])[Tt](\d{4})$/', $periodRaw, $m)) {
        $tNum = (int)$m[1];
        $yNum = (int)$m[2];
        if ($yNum >= 1900 && $yNum <= 2100) {
            $tipo = 'trimestral';
            $trimestre = $tNum;
            $ano = $yNum;
            $label = $trimestre . 'º Trimestre/' . $ano;
        }
    }
    // 3. Anual: aaaa
    elseif (preg_match('/^(\d{4})$/', $periodRaw, $m)) {
        $yNum = (int)$m[1];
        if ($yNum >= 1900 && $yNum <= 2100) {
            $tipo = 'anual';
            $ano = $yNum;
            $label = 'Ano ' . $ano;
        }
    }

    // Fallback: busca por padrões no nome, descrição ou data
    if (!$tipo) {
        if (stripos($descricao, 'Trimestral') !== false || stripos($nome, 'Trimestre') !== false) {
            $tipo = 'trimestral';
            if (preg_match('/([1-4])º?\s*Trimestre/i', $nome . ' ' . $descricao, $tm)) {
                $trimestre = (int)$tm[1];
            }
            if (preg_match('/\b(20\d{2}|19\d{2})\b/', $nome . ' ' . $descricao . ' ' . $dataDoc, $ym)) {
                $ano = (int)$ym[1];
            }
            $label = ($trimestre ? "{$trimestre}º Trimestre" : "Trimestral") . ($ano ? "/{$ano}" : "");
        } elseif (stripos($descricao, 'Anual') !== false) {
            $tipo = 'anual';
            if ($dataDoc) {
                $ano = (int)date('Y', strtotime($dataDoc));
            }
            $label = "Ano " . ($ano ?: '');
        } else {
            if ($dataDoc) {
                $tipo = 'mensal';
                $mes = (int)date('n', strtotime($dataDoc));
                $ano = (int)date('Y', strtotime($dataDoc));
                $label = $mesesNomes[$mes] . '/' . $ano;
            }
        }
    }

    if (!$ano && $dataDoc) {
        $ano = (int)date('Y', strtotime($dataDoc));
    }

    return [
        'tipo'      => $tipo ?: 'outro',
        'mes'       => $mes,
        'trimestre' => $trimestre,
        'ano'       => $ano,
        'label'     => $label
    ];
}

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

$clientes = Auth::isSistema() 
    ? db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social")
    : [];

$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");
$filtroClienteId = (int) get('cliente_id');

// Anos disponíveis nos documentos
$anosDisponiveis = [];
foreach ($documentos as $d) {
    $comp = getDocumentCompetencia($d);
    if (!empty($comp['ano'])) {
        $anosDisponiveis[$comp['ano']] = true;
    }
}
$anosDisponiveis = array_keys($anosDisponiveis);
rsort($anosDisponiveis);
if (empty($anosDisponiveis)) {
    $currentYear = (int)date('Y');
    $anosDisponiveis = [$currentYear, $currentYear - 1, $currentYear - 2];
}
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Documentos', 'url' => '#']
]) ?>

<!-- Header com Ação Principal -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Gestão de Documentos</h2>
        <p class="text-xs text-gray-500">Visualize, ordene, filtre por competência e faça o download seguro dos arquivos</p>
    </div>
    <?php if (Permissions::has('documentos.criar')): ?>
        <div class="flex items-center gap-2">
            <a href="<?= APP_URL ?>/pages/documentos/upload.php" class="btn btn-primary shadow-sm text-sm">
                <i class="fas fa-cloud-upload-alt"></i> Novo Upload
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Card de Filtros Dinâmicos -->
<div class="filter-card mb-6">
    <!-- Linha 1: Categoria, Competência, Mês/Trimestre (dinâmico) e Ano -->
    <div class="flex flex-wrap items-end gap-3 w-full mb-3">
        <!-- 1. Categoria -->
        <div class="flex-1 min-w-[180px]">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                <i class="fas fa-tags text-proxcel"></i> Categoria
            </label>
            <select id="docCategoriaFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>">
                        <?= e($cat['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- 2. Competência -->
        <div class="flex-1 min-w-[160px]">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                <i class="fas fa-filter text-proxcel"></i> Competência
            </label>
            <select id="docTipoCompetenciaFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todas as competências</option>
                <option value="mensal">Mensal</option>
                <option value="trimestral">Trimestral</option>
                <option value="anual">Anual</option>
            </select>
        </div>

        <!-- 3. Mês / Trimestre (quando houver) -->
        <div id="subPeriodoContainer" class="hidden flex-1 min-w-[160px] transition-all">
            <label id="subPeriodoLabel" class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                <i class="fas fa-calendar-alt text-proxcel"></i> Mês
            </label>
            <select id="docSubPeriodoFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todos</option>
            </select>
        </div>

        <!-- 4. Ano -->
        <div class="flex-1 min-w-[140px]">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                <i class="fas fa-calendar text-proxcel"></i> Ano
            </label>
            <select id="docAnoFilter" class="form-control !py-2 text-sm w-full">
                <option value="">Todos os anos</option>
                <?php foreach ($anosDisponiveis as $anoItem): ?>
                    <option value="<?= $anoItem ?>"><?= $anoItem ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Linha 2: Busca (e Empresa / Cliente se admin) -->
    <div class="flex flex-wrap items-end gap-3 w-full pt-3 border-t border-gray-100">
        <?php if (Auth::isSistema()): ?>
            <!-- Filtro Empresa / Cliente -->
            <div class="flex-1 min-w-[240px]">
                <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                    <i class="fas fa-building text-proxcel"></i> Empresa / Cliente
                </label>
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

        <!-- Campo Busca -->
        <div class="<?= Auth::isSistema() ? 'flex-[2]' : 'flex-1' ?> min-w-[280px]">
            <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-1 block flex items-center gap-1">
                <i class="fas fa-search text-proxcel"></i> Buscar
            </label>
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="docSearchInput" placeholder="Digite para buscar título, nome original do arquivo..."
                    class="form-control !py-2 text-sm w-full" style="padding-left: 2.5rem;">
            </div>
        </div>

        <!-- Botão Limpar Filtros -->
        <div class="shrink-0 flex items-center pb-0.5">
            <button type="button" id="btnResetFilters" onclick="resetAllDocFilters()"
                class="btn btn-secondary !py-2 px-3 text-xs text-gray-500 hover:text-red-500 hover:border-red-200 transition hidden flex items-center gap-1.5 shadow-sm">
                <i class="fas fa-times-circle text-red-500"></i> Limpar filtros
            </button>
        </div>
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
                    <th class="hidden md:table-cell">Competência / Data</th>
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
                        <?php $comp = getDocumentCompetencia($doc); ?>
                        <tr class="document-row" 
                            data-cliente-id="<?= $doc['cliente_id'] ?>"
                            data-categoria-id="<?= $doc['categoria_id'] ?? '' ?>"
                            data-tipo-competencia="<?= $comp['tipo'] ?>"
                            data-ano="<?= $comp['ano'] ?? '' ?>"
                            data-mes="<?= $comp['mes'] ?? '' ?>"
                            data-trimestre="<?= $comp['trimestre'] ?? '' ?>">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gray-50 flex items-center justify-center shrink-0 border border-gray-100">
                                        <i class="fas <?= Upload::getIcon($doc['extensao']) ?> text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm"><?= e($doc['nome']) ?></p>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs text-gray-400 font-mono truncate max-w-[200px]" title="<?= e($doc['nome_original']) ?>">
                                                <?= e($doc['nome_original']) ?>
                                            </span>
                                        </div>
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
                            <td class="hidden md:table-cell text-sm">
                                <?php if ($comp['label']): ?>
                                    <span class="font-semibold text-gray-800 block text-xs">
                                        <?= e($comp['label']) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="text-xs text-gray-400">
                                    <?= dateBr($doc['data_documento'] ?: $doc['criado_em']) ?>
                                </span>
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

    const mesesOptions = `
        <option value="">Todos os meses</option>
        <option value="1">01 - Janeiro</option>
        <option value="2">02 - Fevereiro</option>
        <option value="3">03 - Março</option>
        <option value="4">04 - Abril</option>
        <option value="5">05 - Maio</option>
        <option value="6">06 - Junho</option>
        <option value="7">07 - Julho</option>
        <option value="8">08 - Agosto</option>
        <option value="9">09 - Setembro</option>
        <option value="10">10 - Outubro</option>
        <option value="11">11 - Novembro</option>
        <option value="12">12 - Dezembro</option>
    `;

    const trimestresOptions = `
        <option value="">Todos os trimestres</option>
        <option value="1">1º Trimestre (Jan - Mar)</option>
        <option value="2">2º Trimestre (Abr - Jun)</option>
        <option value="3">3º Trimestre (Jul - Set)</option>
        <option value="4">4º Trimestre (Out - Dez)</option>
    `;

    document.addEventListener('DOMContentLoaded', () => {
        const categoriaSelect = document.getElementById('docCategoriaFilter');
        const clienteSelect = $('#docClienteFilter');
        const tipoCompSelect = document.getElementById('docTipoCompetenciaFilter');
        const subPeriodoContainer = document.getElementById('subPeriodoContainer');
        const subPeriodoLabel = document.getElementById('subPeriodoLabel');
        const subPeriodoSelect = document.getElementById('docSubPeriodoFilter');
        const anoSelect = document.getElementById('docAnoFilter');
        const resetBtn = document.getElementById('btnResetFilters');

        function updateSubPeriodoSelector() {
            const tipo = tipoCompSelect.value;
            if (tipo === 'mensal') {
                subPeriodoLabel.innerHTML = '<i class="fas fa-calendar-alt text-proxcel"></i> Mês';
                subPeriodoSelect.innerHTML = mesesOptions;
                subPeriodoContainer.classList.remove('hidden');
            } else if (tipo === 'trimestral') {
                subPeriodoLabel.innerHTML = '<i class="fas fa-chart-pie text-proxcel"></i> Trimestre';
                subPeriodoSelect.innerHTML = trimestresOptions;
                subPeriodoContainer.classList.remove('hidden');
            } else {
                // Se for 'anual' ou vazio '', o seletor some!
                subPeriodoContainer.classList.add('hidden');
                subPeriodoSelect.value = '';
            }
        }

        docTable = new ProxcelTable('#docDataTable', {
            perPage: 10,
            searchInput: '#docSearchInput',
            customFilter: function (row) {
                const selectedCategoria = categoriaSelect.value || '';
                const selectedCliente = clienteSelect.length ? (clienteSelect.val() || '') : '';
                const selectedTipoComp = tipoCompSelect.value || '';
                const selectedSubPeriodo = subPeriodoSelect.value || '';
                const selectedAno = anoSelect.value || '';

                const rowCliente = row.getAttribute('data-cliente-id') || '';
                const rowCategoria = row.getAttribute('data-categoria-id') || '';
                const rowTipoComp = row.getAttribute('data-tipo-competencia') || '';
                const rowAno = row.getAttribute('data-ano') || '';
                const rowMes = row.getAttribute('data-mes') || '';
                const rowTrimestre = row.getAttribute('data-trimestre') || '';

                const matchCategoria = !selectedCategoria || rowCategoria === selectedCategoria;
                const matchCliente = !selectedCliente || rowCliente === selectedCliente;
                const matchTipoComp = !selectedTipoComp || rowTipoComp === selectedTipoComp;
                const matchAno = !selectedAno || rowAno === selectedAno;

                let matchSubPeriodo = true;
                if (selectedTipoComp === 'mensal' && selectedSubPeriodo) {
                    matchSubPeriodo = (rowMes === selectedSubPeriodo);
                } else if (selectedTipoComp === 'trimestral' && selectedSubPeriodo) {
                    matchSubPeriodo = (rowTrimestre === selectedSubPeriodo);
                }

                const isFiltered = Boolean(
                    document.getElementById('docSearchInput').value || 
                    selectedCategoria || 
                    selectedCliente || 
                    selectedTipoComp || 
                    selectedSubPeriodo || 
                    selectedAno
                );

                if (resetBtn) resetBtn.classList.toggle('hidden', !isFiltered);

                return matchCategoria && matchCliente && matchTipoComp && matchAno && matchSubPeriodo;
            }
        });

        categoriaSelect.addEventListener('change', () => docTable.applyFilters());

        tipoCompSelect.addEventListener('change', () => {
            updateSubPeriodoSelector();
            docTable.applyFilters();
        });

        subPeriodoSelect.addEventListener('change', () => docTable.applyFilters());
        anoSelect.addEventListener('change', () => docTable.applyFilters());

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
        const tipoCompSelect = document.getElementById('docTipoCompetenciaFilter');
        const subPeriodoSelect = document.getElementById('docSubPeriodoFilter');
        const subPeriodoContainer = document.getElementById('subPeriodoContainer');
        const anoSelect = document.getElementById('docAnoFilter');

        if (searchInput) searchInput.value = '';
        if (categoriaSelect) categoriaSelect.value = '';
        if (clienteSelect.length) clienteSelect.val('').trigger('change');
        if (tipoCompSelect) tipoCompSelect.value = '';
        if (subPeriodoSelect) subPeriodoSelect.value = '';
        if (subPeriodoContainer) subPeriodoContainer.classList.add('hidden');
        if (anoSelect) anoSelect.value = '';

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