<?php
/**
 * Proxcel - Importação Múltipla Mensal de Documentos
 */
$pageTitle = 'Importação Mensal de Documentos';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/mail_helper.php';

Permissions::requirePermission('documentos.criar');

$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

// Cálculo dos padrões: mês anterior e ano correspondente
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');

if ($currentMonth === 1) {
    $defaultMonth = 12;
    $defaultYear = $currentYear - 1;
} else {
    $defaultMonth = $currentMonth - 1;
    $defaultYear = $currentYear;
}

// Configuração dos 6 arquivos esperados
$fileConfigs = [
    'apuracao_financeira' => [
        'id' => 'apuracao_financeira',
        'nome_base' => 'Apuração Financeira',
        'tipo' => 'Excel',
        'icone' => 'fa-file-excel',
        'cor_badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cor_icon' => 'text-emerald-600',
        'accept' => '.xlsx,.xls,.csv',
        'accept_label' => 'XLSX, XLS ou CSV'
    ],
    'ponto_equilibrio' => [
        'id' => 'ponto_equilibrio',
        'nome_base' => 'Ponto de Equilíbrio',
        'tipo' => 'Excel',
        'icone' => 'fa-file-excel',
        'cor_badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cor_icon' => 'text-emerald-600',
        'accept' => '.xlsx,.xls,.csv',
        'accept_label' => 'XLSX, XLS ou CSV'
    ],
    'resumo_administracao' => [
        'id' => 'resumo_administracao',
        'nome_base' => 'Resumo de Administração',
        'tipo' => 'Excel',
        'icone' => 'fa-file-excel',
        'cor_badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cor_icon' => 'text-emerald-600',
        'accept' => '.xlsx,.xls,.csv',
        'accept_label' => 'XLSX, XLS ou CSV'
    ],
    'preco_venda' => [
        'id' => 'preco_venda',
        'nome_base' => 'Preço de Venda',
        'tipo' => 'Excel',
        'icone' => 'fa-file-excel',
        'cor_badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'cor_icon' => 'text-emerald-600',
        'accept' => '.xlsx,.xls,.csv',
        'accept_label' => 'XLSX, XLS ou CSV'
    ],
    'resumo_apuracao' => [
        'id' => 'resumo_apuracao',
        'nome_base' => 'Resumo Apuração',
        'tipo' => 'PDF',
        'icone' => 'fa-file-pdf',
        'cor_badge' => 'bg-rose-50 text-rose-700 border-rose-200',
        'cor_icon' => 'text-rose-600',
        'accept' => '.pdf',
        'accept_label' => 'Documento PDF'
    ],
    'power_bi' => [
        'id' => 'power_bi',
        'nome_base' => 'Power BI',
        'tipo' => 'PBIX',
        'icone' => 'fa-chart-pie',
        'cor_badge' => 'bg-amber-50 text-amber-700 border-amber-200',
        'cor_icon' => 'text-amber-600',
        'accept' => '.pbix',
        'accept_label' => 'Relatório Power BI (.pbix)'
    ],
];

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");

// Categoria padrão Relatórios se existir
$categoriaPadraoId = '';
foreach ($categorias as $cat) {
    if (stripos($cat['nome'], 'Relat') !== false || stripos($cat['nome'], 'Balan') !== false) {
        $categoriaPadraoId = $cat['id'];
        break;
    }
}

$errors = [];
$uploadedDocs = [];

if (isPost()) {
    CSRF::validateOrDie();

    $clienteId = (int)post('cliente_id');
    $mes = (int)post('mes');
    $ano = (int)post('ano');
    $categoriaId = (int)post('categoria_id') ?: null;
    $notificar = isset($_POST['notificar']);

    if (!$clienteId) {
        $errors[] = 'Selecione a empresa/cliente.';
    }
    if (!isset($meses[$mes])) {
        $errors[] = 'Selecione um mês de referência válido.';
    }
    if ($ano < 2000 || $ano > 2100) {
        $errors[] = 'Informe um ano de referência válido.';
    }

    $nomeMes = $meses[$mes] ?? '';
    $arquivosEnviados = 0;

    // Verifica se ao menos um arquivo foi anexado
    foreach ($fileConfigs as $field => $config) {
        if (!empty($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
            $arquivosEnviados++;
        }
    }

    if ($arquivosEnviados === 0 && empty($errors)) {
        $errors[] = 'Selecione pelo menos um arquivo para realizar a importação.';
    }

    if (empty($errors)) {
        $ultimoDiaMes = date('Y-m-t', strtotime("{$ano}-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01"));
        $sucessos = 0;

        foreach ($fileConfigs as $field => $config) {
            if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $resultado = Upload::process($_FILES[$field], $clienteId);

            if ($resultado['success']) {
                $nomeGerado = "{$config['nome_base']} - {$nomeMes} {$ano}";
                $descricao = "Importação Mensal - Referência: {$nomeMes}/{$ano}";

                $docId = db()->insert(
                    "INSERT INTO " . tb('documentos') . " 
                     (cliente_id, categoria_id, nome, descricao, nome_original, nome_arquivo, mime_type, tamanho, extensao, data_documento, criado_por, criado_por_tipo) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $clienteId,
                        $categoriaId,
                        $nomeGerado,
                        $descricao,
                        $resultado['nome_original'],
                        $resultado['nome_arquivo'],
                        $resultado['mime_type'],
                        $resultado['tamanho'],
                        $resultado['extensao'],
                        $ultimoDiaMes,
                        $currentUser['id'],
                        $currentUser['tipo_acesso']
                    ]
                );

                Security::logChange('documentos', $docId, 'criar', null, ['nome' => $nomeGerado, 'cliente_id' => $clienteId, 'origem' => 'importacao_mensal']);

                $uploadedDocs[] = $nomeGerado;
                $sucessos++;
            } else {
                $errors[] = "Erro no arquivo '{$config['nome_base']}': " . $resultado['message'];
            }
        }

        if ($sucessos > 0) {
            // Notifica usuários do cliente se selecionado
            if ($notificar) {
                $usersCliente = db()->fetchAll(
                    "SELECT nome, email FROM " . tb('usuarios_clientes') . " WHERE cliente_id = ? AND status = 'ativo'",
                    [$clienteId]
                );
                foreach ($usersCliente as $uc) {
                    $listaNomes = implode(', ', $uploadedDocs);
                    MailHelper::sendDocumentNotification($uc['email'], $uc['nome'], "Lote Mensal ({$nomeMes}/{$ano}): {$listaNomes}");
                }
            }

            $msg = "Importação concluída! {$sucessos} documento(s) importado(s) com sucesso para {$nomeMes}/{$ano}.";
            setFlash('success', $msg);
            redirect(APP_URL . '/pages/documentos/index.php?cliente_id=' . $clienteId);
        }
    }
}

$maxSizeMb = UPLOAD_MAX_SIZE / (1024 * 1024);
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Documentos', 'url' => APP_URL . '/pages/documentos/index.php'],
    ['label' => 'Importação Mensal', 'url' => '#']
]) ?>

<!-- Abas de Navegação / Ações -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-2 bg-gray-200/70 p-1.5 rounded-2xl">
        <a href="<?= APP_URL ?>/pages/documentos/upload.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:text-gray-900 transition flex items-center gap-2">
            <i class="fas fa-file-upload"></i> Upload Individual
        </a>
        <a href="<?= APP_URL ?>/pages/documentos/importar_mensal.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-white text-proxcel shadow-sm transition flex items-center gap-2">
            <i class="fas fa-calendar-alt"></i> Importação Mensal
        </a>
        <a href="<?= APP_URL ?>/pages/documentos/importar_trimestral.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:text-gray-900 transition flex items-center gap-2">
            <i class="fas fa-chart-pie"></i> Importação Trimestral
        </a>
    </div>

    <a href="<?= APP_URL ?>/pages/documentos/index.php" class="btn btn-secondary text-sm">
        <i class="fas fa-arrow-left"></i> Voltar à Lista
    </a>
</div>

<div class="card animate-fadeInUp">
    <!-- Header do Card -->
    <div class="p-5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3 bg-gradient-to-r from-emerald-50/50 via-white to-white">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
                    <i class="fas fa-calendar-check"></i>
                </span>
                <h3 class="text-lg font-bold text-gray-800">Importação de Arquivos em Lote — Mensal</h3>
            </div>
            <p class="text-xs text-gray-500 mt-0.5 ml-10">
                Selecione a empresa, o mês de referência e anexe os arquivos. Os nomes oficiais serão gerados automaticamente.
            </p>
        </div>
        <div class="flex items-center gap-2 text-xs bg-emerald-100 text-emerald-800 px-3 py-1.5 rounded-full font-medium">
            <i class="fas fa-info-circle"></i> Padrão de Nomenclatura: [Arquivo] - [Mês] [Ano]
        </div>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" id="formImportacaoMensal" class="p-6">
        <?= CSRF::field() ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-6 animate-shake">
            <div class="flex items-center gap-2 text-red-700 font-semibold text-sm mb-1">
                <i class="fas fa-exclamation-triangle"></i> Atenção aos erros encontrados:
            </div>
            <ul class="text-red-600 text-sm list-disc list-inside space-y-1 ml-1">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Bloco de Seleção de Parâmetros -->
        <div class="bg-gray-50/80 border border-gray-200/80 rounded-2xl p-5 mb-6 shadow-inner">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <i class="fas fa-sliders-h text-proxcel"></i> Parâmetros da Importação
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Cliente -->
                <div class="md:col-span-5 form-group !mb-0">
                    <label class="font-semibold text-gray-700 text-xs">Empresa / Cliente <span class="text-red-500">*</span></label>
                    <select name="cliente_id" id="clienteSelect" class="form-control select2" required data-placeholder="Selecione a empresa...">
                        <option value="">Selecione a empresa...</option>
                        <?php foreach ($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($_POST['cliente_id']) && $_POST['cliente_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= e($c['nome_fantasia'] ?: $c['razao_social']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Mês de Referência -->
                <div class="md:col-span-3 form-group !mb-0">
                    <label class="font-semibold text-gray-700 text-xs">Mês de Referência <span class="text-red-500">*</span></label>
                    <select name="mes" id="mesSelect" class="form-control font-medium text-gray-800" required>
                        <?php foreach ($meses as $numMes => $labelMes): ?>
                        <option value="<?= $numMes ?>" <?= ((isset($_POST['mes']) ? (int)$_POST['mes'] : $defaultMonth) === $numMes) ? 'selected' : '' ?>>
                            <?= $numMes ?> - <?= $labelMes ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ano de Referência -->
                <div class="md:col-span-2 form-group !mb-0">
                    <label class="font-semibold text-gray-700 text-xs">Ano <span class="text-red-500">*</span></label>
                    <select name="ano" id="anoSelect" class="form-control font-medium text-gray-800" required>
                        <?php 
                        $anoBase = isset($_POST['ano']) ? (int)$_POST['ano'] : $defaultYear;
                        for ($y = $currentYear + 2; $y >= $currentYear - 5; $y--): 
                        ?>
                        <option value="<?= $y ?>" <?= $anoBase === $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Categoria -->
                <div class="md:col-span-2 form-group !mb-0">
                    <label class="font-semibold text-gray-700 text-xs">Categoria</label>
                    <select name="categoria_id" class="form-control">
                        <option value="">Automática / Geral</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= ($categoriaPadraoId == $cat['id']) ? 'selected' : '' ?>>
                            <?= e($cat['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Grade com os 6 Cards de Arquivos -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h4 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-layer-group text-proxcel"></i> Arquivos do Lote
                    </h4>
                    <p class="text-xs text-gray-400">Anexe os arquivos desejados. Você pode enviar todos ou apenas os disponíveis no momento.</p>
                </div>
                <span id="selectedCountBadge" class="text-xs font-semibold px-3 py-1 bg-gray-100 text-gray-600 rounded-full">
                    0 de 6 selecionados
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php 
                $i = 1;
                foreach ($fileConfigs as $field => $config): 
                ?>
                <div class="file-card border-2 border-dashed border-gray-200 hover:border-proxcel rounded-2xl p-4 bg-white hover:bg-emerald-50/20 transition-all duration-200 flex flex-col justify-between relative group" id="card_<?= $field ?>">
                    <!-- Header do Card -->
                    <div>
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 rounded-lg bg-gray-100 text-gray-700 flex items-center justify-center font-bold text-xs">
                                    <?= $i ?>
                                </span>
                                <h5 class="text-sm font-bold text-gray-800"><?= e($config['nome_base']) ?></h5>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border <?= $config['cor_badge'] ?>">
                                <?= $config['tipo'] ?>
                            </span>
                        </div>

                        <!-- Preview do Nome Automático -->
                        <div class="bg-gray-50 border border-gray-100 rounded-xl p-2.5 mb-3">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-0.5">Nome no Sistema:</span>
                            <p class="text-xs font-semibold text-gray-700 truncate name-preview" data-base="<?= e($config['nome_base']) ?>">
                                <?= e($config['nome_base']) ?> - <span class="preview-mes">Janeiro</span> <span class="preview-ano">2026</span>
                            </p>
                        </div>
                    </div>

                    <!-- Área de Arquivo / Dropzone Compacta -->
                    <div>
                        <div class="drop-target border border-gray-200 rounded-xl p-3 text-center cursor-pointer hover:bg-gray-50 transition" onclick="triggerFileInput('<?= $field ?>')">
                            <i class="fas <?= $config['icone'] ?> text-2xl <?= $config['cor_icon'] ?> mb-1 block"></i>
                            <span class="text-xs font-semibold text-gray-700 block drop-text">Clique para selecionar</span>
                            <span class="text-[11px] text-gray-400 block"><?= $config['accept_label'] ?></span>
                        </div>

                        <input type="file" name="<?= $field ?>" id="input_<?= $field ?>" class="hidden" 
                               accept="<?= $config['accept'] ?>" onchange="handleFileSelected('<?= $field ?>', this)">

                        <!-- Informações do Arquivo Selecionado -->
                        <div id="info_<?= $field ?>" class="hidden mt-2 p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <i class="fas fa-check-circle text-emerald-600 text-sm"></i>
                                <div class="truncate">
                                    <p class="text-xs font-bold text-emerald-900 truncate file-selected-name"></p>
                                    <p class="text-[10px] text-emerald-600 file-selected-size"></p>
                                </div>
                            </div>
                            <button type="button" onclick="clearFileInput('<?= $field ?>')" class="text-gray-400 hover:text-red-500 p-1 transition" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php 
                $i++;
                endforeach; 
                ?>
            </div>
        </div>

        <!-- Opções Extras e Notificação -->
        <div class="bg-gray-50/50 border border-gray-100 rounded-2xl p-4 mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <label class="flex items-center gap-2.5 cursor-pointer">
                <input type="checkbox" name="notificar" value="1" class="w-4 h-4 text-proxcel rounded border-gray-300 focus:ring-proxcel">
                <span class="text-xs font-semibold text-gray-700">Notificar os contatos ativos desta empresa por e-mail após a importação</span>
            </label>
            <span class="text-xs text-gray-400">
                Tamanho máximo por arquivo: <?= $maxSizeMb ?>MB
            </span>
        </div>

        <!-- Botões de Ação -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/documentos/index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" id="btnSubmitImport" class="btn btn-primary px-6">
                <i class="fas fa-cloud-upload-alt"></i> Importar Arquivos Selecionados
            </button>
        </div>
    </form>
</div>

<script>
const mesesNomes = <?= json_encode($meses) ?>;

function updateNamePreviews() {
    const mesVal = document.getElementById('mesSelect').value;
    const anoVal = document.getElementById('anoSelect').value;
    const nomeMes = mesesNomes[mesVal] || '';

    document.querySelectorAll('.name-preview').forEach(el => {
        const base = el.getAttribute('data-base');
        el.textContent = `${base} - ${nomeMes} ${anoVal}`;
    });
}

function triggerFileInput(fieldId) {
    document.getElementById('input_' + fieldId).click();
}

function handleFileSelected(fieldId, input) {
    const card = document.getElementById('card_' + fieldId);
    const info = document.getElementById('info_' + fieldId);
    const dropTarget = card.querySelector('.drop-target');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSize = <?= UPLOAD_MAX_SIZE ?>;

        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'Arquivo muito grande',
                text: `O arquivo ${file.name} excede o limite de <?= $maxSizeMb ?>MB.`
            });
            clearFileInput(fieldId);
            return;
        }

        info.querySelector('.file-selected-name').textContent = file.name;
        info.querySelector('.file-selected-size').textContent = formatBytes(file.size);
        info.classList.remove('hidden');
        dropTarget.classList.add('hidden');
        card.classList.add('!border-emerald-400', 'bg-emerald-50/10');
    } else {
        clearFileInput(fieldId);
    }
    updateSelectedCount();
}

function clearFileInput(fieldId) {
    const input = document.getElementById('input_' + fieldId);
    const card = document.getElementById('card_' + fieldId);
    const info = document.getElementById('info_' + fieldId);
    const dropTarget = card.querySelector('.drop-target');

    input.value = '';
    info.classList.add('hidden');
    dropTarget.classList.remove('hidden');
    card.classList.remove('!border-emerald-400', 'bg-emerald-50/10');
    updateSelectedCount();
}

function updateSelectedCount() {
    let count = 0;
    document.querySelectorAll('input[type="file"]').forEach(inp => {
        if (inp.files && inp.files.length > 0) count++;
    });

    const badge = document.getElementById('selectedCountBadge');
    if (badge) {
        badge.textContent = `${count} de 6 selecionados`;
        if (count > 0) {
            badge.className = 'text-xs font-bold px-3 py-1 bg-emerald-100 text-emerald-800 rounded-full animate-pulse';
        } else {
            badge.className = 'text-xs font-semibold px-3 py-1 bg-gray-100 text-gray-600 rounded-full';
        }
    }
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('mesSelect').addEventListener('change', updateNamePreviews);
    document.getElementById('anoSelect').addEventListener('change', updateNamePreviews);
    updateNamePreviews();
    updateSelectedCount();

    // Drag & Drop nos cards
    document.querySelectorAll('.file-card').forEach(card => {
        const fieldId = card.id.replace('card_', '');
        const input = document.getElementById('input_' + fieldId);

        ['dragenter', 'dragover'].forEach(eventName => {
            card.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                card.classList.add('!border-proxcel', 'scale-[1.01]');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            card.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                card.classList.remove('!border-proxcel', 'scale-[1.01]');
            }, false);
        });

        card.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            if (dt.files && dt.files.length > 0) {
                input.files = dt.files;
                handleFileSelected(fieldId, input);
            }
        });
    });

    // Submissão com feedback
    document.getElementById('formImportacaoMensal').addEventListener('submit', function(e) {
        let count = 0;
        document.querySelectorAll('input[type="file"]').forEach(inp => {
            if (inp.files && inp.files.length > 0) count++;
        });

        if (count === 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Nenhum arquivo anexado',
                text: 'Por favor, selecione pelo menos um arquivo antes de importar.'
            });
            return;
        }

        const btn = document.getElementById('btnSubmitImport');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando importação...';
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
