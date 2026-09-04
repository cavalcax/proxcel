<?php
/**
 * Proxcel - Upload e Catalogação Inteligente de Documentos
 */
$pageTitle = 'Upload de Documentos';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/mail_helper.php';

Permissions::requirePermission('documentos.criar');

/**
 * Função de catalogação de documentos pelo padrão do nome do arquivo
 */
function parseDocumentFileName(string $filename): array {
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $parts = explode('_', $baseName);
    
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];

    $friendlyNames = [
        'apuracao_financeira'   => 'Apuração Financeira',
        'ponto_equilibrio'      => 'Ponto de Equilíbrio',
        'resumo_administracao'  => 'Resumo de Administração',
        'preco_venda'           => 'Preço de Venda',
        'resumo_apuracao'       => 'Resumo Apuração',
        'powerbi'               => 'Power BI',
        'power_bi'              => 'Power BI'
    ];

    $periodRaw = $parts[0] ?? '';
    $nameParts = array_slice($parts, 1);
    $rawDocKey = strtolower(implode('_', $nameParts));
    
    if (isset($friendlyNames[$rawDocKey])) {
        $docBaseTitle = $friendlyNames[$rawDocKey];
    } elseif (!empty($nameParts)) {
        $docBaseTitle = ucwords(str_replace(['_', '-'], ' ', implode(' ', $nameParts)));
    } else {
        $docBaseTitle = ucwords(str_replace(['_', '-'], ' ', $baseName));
    }

    $periodType = null;
    $periodLabel = null;
    $dataDoc = null;
    $finalTitle = $docBaseTitle;
    $descricao = null;

    // 1. Mensal: mmaaaa (ex: 052026)
    if (preg_match('/^(\d{2})(\d{4})$/', $periodRaw, $m)) {
        $mes = (int)$m[1];
        $ano = (int)$m[2];
        if ($mes >= 1 && $mes <= 12 && $ano >= 1900 && $ano <= 2100) {
            $periodType = 'mensal';
            $nomeMes = $meses[$mes];
            $periodLabel = "{$nomeMes}/{$ano}";
            $dataDoc = date('Y-m-t', strtotime("{$ano}-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01"));
            $finalTitle = "{$docBaseTitle} - {$nomeMes} {$ano}";
            $descricao = "Demonstrativo Mensal - Referência: {$nomeMes}/{$ano}";
        }
    }

    // 2. Trimestral: 1Taaaa, 2Taaaa, 3Taaaa, 4Taaaa (ex: 1T2026)
    if (!$periodType && preg_match('/^([1-4])[Tt](\d{4})$/', $periodRaw, $m)) {
        $tri = (int)$m[1];
        $ano = (int)$m[2];
        if ($ano >= 1900 && $ano <= 2100) {
            $periodType = 'trimestral';
            $periodLabel = "{$tri}º Trimestre/{$ano}";
            $triEndDays = [1 => '03-31', 2 => '06-30', 3 => '09-30', 4 => '12-31'];
            $dataDoc = "{$ano}-{$triEndDays[$tri]}";
            $finalTitle = "{$docBaseTitle} - {$tri}º Trimestre {$ano}";
            $descricao = "Demonstrativo Trimestral - Referência: {$tri}º Trimestre/{$ano}";
        }
    }

    // 3. Anual: aaaa (ex: 2026)
    if (!$periodType && preg_match('/^(\d{4})$/', $periodRaw, $m)) {
        $ano = (int)$m[1];
        if ($ano >= 1900 && $ano <= 2100) {
            $periodType = 'anual';
            $periodLabel = "Ano {$ano}";
            $dataDoc = "{$ano}-12-31";
            $finalTitle = "{$docBaseTitle} - {$ano}";
            $descricao = "Demonstrativo Anual - Referência: {$ano}";
        }
    }

    // Fallback se não bater com nenhum prefixo de período
    if (!$periodType) {
        $periodType = 'geral';
        $periodLabel = 'Geral';
        $dataDoc = date('Y-m-d');
        $finalTitle = ucwords(str_replace(['_', '-'], ' ', $baseName));
        $descricao = "Documento importado";
    }

    return [
        'period_type'    => $periodType,
        'period_label'   => $periodLabel,
        'data_documento' => $dataDoc,
        'nome'           => $finalTitle,
        'descricao'      => $descricao,
        'base_title'     => $docBaseTitle,
        'nome_original'  => $filename
    ];
}

if (Auth::isCliente()) {
    $clienteIdLogado = (int)$currentUser['cliente_id'];
    $clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE id = ? AND status = 'ativo'", [$clienteIdLogado]);
} else {
    $clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
}
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");

// Identifica categoria padrão 'Demonstrativos Financeiros'
$defaultCategoriaId = null;
foreach ($categorias as $cat) {
    if (mb_strtolower($cat['nome'], 'UTF-8') === 'demonstrativos financeiros') {
        $defaultCategoriaId = $cat['id'];
        break;
    }
}

$errors = [];
$sucessos = [];

if (isPost()) {
    CSRF::validateOrDie();
    
    // Se for cliente, força o cliente_id da sessão sem aceitar manipulação via POST
    if (Auth::isCliente()) {
        $clienteId = (int)$currentUser['cliente_id'];
    } else {
        $clienteId = (int)post('cliente_id');
    }

    $categoriaId = (int)post('categoria_id') ?: null;
    $notificar = isset($_POST['notificar']);

    if (!$clienteId) {
        $errors[] = 'Selecione um cliente para prosseguir com o upload.';
    }

    // Reorganiza arquivos múltiplos
    $arquivosProcessar = [];
    if (!empty($_FILES['arquivos']['name'])) {
        if (is_array($_FILES['arquivos']['name'])) {
            $totalFiles = count($_FILES['arquivos']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['arquivos']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $arquivosProcessar[] = [
                    'name'     => $_FILES['arquivos']['name'][$i],
                    'type'     => $_FILES['arquivos']['type'][$i],
                    'tmp_name' => $_FILES['arquivos']['tmp_name'][$i],
                    'error'    => $_FILES['arquivos']['error'][$i],
                    'size'     => $_FILES['arquivos']['size'][$i]
                ];
            }
        } else {
            if ($_FILES['arquivos']['error'] !== UPLOAD_ERR_NO_FILE) {
                $arquivosProcessar[] = $_FILES['arquivos'];
            }
        }
    }

    if (empty($arquivosProcessar)) {
        $errors[] = 'Selecione pelo menos um arquivo para importar.';
    }

    if (empty($errors)) {
        $nomesImportados = [];

        foreach ($arquivosProcessar as $arq) {
            $originalName = $arq['name'];
            $resultado = Upload::process($arq, $clienteId);

            if ($resultado['success']) {
                $parsed = parseDocumentFileName($originalName);

                $docId = db()->insert(
                    "INSERT INTO " . tb('documentos') . " 
                     (cliente_id, categoria_id, nome, descricao, nome_original, nome_arquivo, mime_type, tamanho, extensao, data_documento, criado_por, criado_por_tipo) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $clienteId,
                        $categoriaId,
                        $parsed['nome'],
                        $parsed['descricao'],
                        $originalName, // Preserva exatamente o nome original do arquivo
                        $resultado['nome_arquivo'],
                        $resultado['mime_type'],
                        $resultado['tamanho'],
                        $resultado['extensao'],
                        $parsed['data_documento'],
                        $currentUser['id'],
                        $currentUser['tipo_acesso']
                    ]
                );

                Security::logChange('documentos', $docId, 'criar', null, [
                    'nome' => $parsed['nome'],
                    'cliente_id' => $clienteId,
                    'nome_original' => $originalName,
                    'origem' => 'upload_multiplo'
                ]);

                $nomesImportados[] = $parsed['nome'];
                $sucessos[] = [
                    'original' => $originalName,
                    'catalogado' => $parsed['nome'],
                    'periodo' => $parsed['period_label']
                ];
            } else {
                $errors[] = "Erro no arquivo '{$originalName}': " . $resultado['message'];
            }
        }

        if (!empty($sucessos)) {
            // Notifica usuários do cliente se habilitado
            if ($notificar) {
                $usersCliente = db()->fetchAll(
                    "SELECT nome, email FROM " . tb('usuarios_clientes') . " WHERE cliente_id = ? AND status = 'ativo'",
                    [$clienteId]
                );
                $resumoNomes = implode(', ', array_slice($nomesImportados, 0, 3));
                if (count($nomesImportados) > 3) {
                    $resumoNomes .= ' e mais ' . (count($nomesImportados) - 3) . ' documento(s)';
                }
                foreach ($usersCliente as $uc) {
                    MailHelper::sendDocumentNotification($uc['email'], $uc['nome'], "Lote de Documentos: {$resumoNomes}");
                }
            }

            $qtd = count($sucessos);
            setFlash('success', "{$qtd} documento(s) importado(s) e catalogado(s) com sucesso!");
            redirect(APP_URL . '/pages/documentos/index.php?cliente_id=' . $clienteId);
        }
    }
}

$maxSizeMb = UPLOAD_MAX_SIZE / (1024 * 1024);
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Documentos', 'url' => APP_URL . '/pages/documentos/index.php'],
    ['label' => 'Upload de Documentos', 'url' => '#']
]) ?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">
            <i class="fas fa-cloud-upload-alt text-proxcel mr-2"></i> Importação e Catalogação de Documentos
        </h2>
        <p class="text-xs text-gray-500">Envie múltiplos arquivos simultaneamente. O sistema reconhece o período e cataloga automaticamente.</p>
    </div>

    <a href="<?= APP_URL ?>/pages/documentos/index.php" class="btn btn-secondary text-sm">
        <i class="fas fa-arrow-left"></i> Voltar à Lista
    </a>
</div>

<!-- Card de Ajuda / Padrão de Nomenclatura -->
<div class="card bg-gradient-to-r from-emerald-50/70 via-white to-blue-50/70 border border-emerald-100/80 p-4 mb-6 rounded-2xl shadow-sm">
    <div class="flex items-start gap-3">
        <div class="w-9 h-9 rounded-xl bg-proxcel/10 text-proxcel flex items-center justify-center shrink-0 mt-0.5">
            <i class="fas fa-magic text-base"></i>
        </div>
        <div class="text-xs text-gray-600 flex-1">
            <p class="font-bold text-gray-800 text-sm mb-1">Catalogação Automática Inteligente por Nome de Arquivo</p>
            <p class="mb-2">O sistema extrai o período e o título através da convenção <code class="bg-white px-1.5 py-0.5 rounded border border-gray-200 text-proxcel font-mono font-bold">PERIODO_Nome_Do_Arquivo.ext</code>:</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                <div class="bg-white/80 p-2 rounded-lg border border-gray-100">
                    <span class="font-semibold text-emerald-700 block"><i class="fas fa-calendar-alt mr-1"></i> Mensal: <code>mmaaaa_...</code></span>
                    <span class="text-gray-500">Ex: <code>052026_Apuracao_Financeira.xlsx</code></span>
                </div>
                <div class="bg-white/80 p-2 rounded-lg border border-gray-100">
                    <span class="font-semibold text-blue-700 block"><i class="fas fa-chart-pie mr-1"></i> Trimestral: <code>[1-4]Taaaa_...</code></span>
                    <span class="text-gray-500">Ex: <code>1T2026_Resumo_Apuracao.pdf</code></span>
                </div>
                <div class="bg-white/80 p-2 rounded-lg border border-gray-100">
                    <span class="font-semibold text-amber-700 block"><i class="fas fa-calendar mr-1"></i> Anual: <code>aaaa_...</code></span>
                    <span class="text-gray-500">Ex: <code>2026_PowerBI.pbix</code></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card animate-fadeInUp">
    <form method="POST" action="" enctype="multipart/form-data" id="formUploadDocumentos" class="p-6">
        <?= CSRF::field() ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <div class="flex items-center gap-2 text-red-700 font-semibold text-sm mb-1">
                <i class="fas fa-exclamation-circle"></i> Não foi possível concluir o upload:
            </div>
            <ul class="text-red-600 text-sm list-disc list-inside space-y-0.5">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Seletores Principais -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-5 mb-6">
            <!-- Seletor de Cliente com Select2 e Filtro de Busca -->
            <div class="md:col-span-7">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                    Cliente / Empresa <span class="text-red-500">*</span>
                </label>
                <select name="cliente_id" id="clienteSelect" class="form-control select2 w-full" required data-placeholder="Busque e selecione o cliente...">
                    <option value="">Selecione o cliente...</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>">
                        <?= e($c['nome_fantasia'] ? $c['nome_fantasia'] . ' (' . $c['razao_social'] . ')' : $c['razao_social']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-gray-400 mt-1">Digite a razão social ou nome fantasia para filtrar rapidamente.</p>
            </div>

            <!-- Seletor de Categoria com Demonstrativos Financeiros por Default -->
            <div class="md:col-span-5">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                    Categoria
                </label>
                <select name="categoria_id" id="categoriaSelect" class="form-control select2 w-full">
                    <option value="">Sem categoria específica</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $defaultCategoriaId) ? 'selected' : '' ?>>
                        <?= e($cat['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[11px] text-emerald-600 mt-1"><i class="fas fa-check-circle"></i> Categoria "Demonstrativos Financeiros" selecionada por padrão.</p>
            </div>
        </div>

        <!-- Zona de Upload Múltiplo -->
        <div class="mb-6">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                Arquivos para Importação <span class="text-red-500">*</span>
            </label>
            
            <div class="upload-zone !p-8 border-2 border-dashed border-gray-300 hover:border-proxcel rounded-2xl bg-gray-50/50 hover:bg-emerald-50/20 transition cursor-pointer text-center" id="uploadZone">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-100/70 text-proxcel flex items-center justify-center mb-3">
                    <i class="fas fa-cloud-upload-alt text-2xl"></i>
                </div>
                <p class="text-gray-800 font-bold text-base mb-1">Arraste e solte seus arquivos aqui</p>
                <p class="text-gray-500 text-xs mb-3">ou clique para selecionar múltiplos arquivos simultaneamente</p>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white border border-gray-200 text-xs text-gray-500 shadow-sm">
                    <i class="fas fa-paperclip text-proxcel"></i> XLSX, XLS, PDF, PBIX, CSV, DOC, DOCX, Imagens &bull; Máx: <?= $maxSizeMb ?>MB por arquivo
                </div>
                
                <input type="file" name="arquivos[]" id="fileInput" class="hidden" 
                       multiple 
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.pbix,.jpg,.jpeg,.png,.gif,.webp,.csv,.txt,.zip,.rar">
            </div>
        </div>

        <!-- Painel de Arquivos Selecionados e Catalogação em Tempo Real -->
        <div id="selectedFilesContainer" class="hidden mb-6">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-200">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-proxcel animate-pulse"></span>
                    <h4 class="font-bold text-sm text-gray-800">
                        Arquivos Selecionados (<span id="filesCount">0</span>)
                    </h4>
                    <span class="text-xs text-gray-400" id="filesTotalSize"></span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="text-xs font-semibold text-proxcel hover:underline flex items-center gap-1">
                        <i class="fas fa-plus"></i> Adicionar mais
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" onclick="clearAllFiles()" class="text-xs font-semibold text-red-500 hover:underline flex items-center gap-1">
                        <i class="fas fa-trash-alt"></i> Limpar lista
                    </button>
                </div>
            </div>

            <!-- Lista / Tabela de Arquivos com Preview de Catalogação -->
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-600 font-bold border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-3">Arquivo Original</th>
                            <th class="py-2.5 px-3">Título Catalogado</th>
                            <th class="py-2.5 px-3">Período Detectado</th>
                            <th class="py-2.5 px-3">Tamanho</th>
                            <th class="py-2.5 px-3 text-center w-12">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="filesTableBody" class="divide-y divide-gray-100">
                        <!-- Renderizado dinamicamente via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Opções Adicionais -->
        <div class="bg-gray-50/80 rounded-xl p-4 mb-6 border border-gray-100">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="notificar" value="1" checked class="w-4 h-4 text-proxcel rounded border-gray-300 focus:ring-proxcel">
                <span class="text-xs font-semibold text-gray-700">Notificar usuários ativos do cliente por e-mail após a importação</span>
            </label>
        </div>

        <!-- Botões de Ação -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/documentos/index.php" class="btn btn-secondary text-sm">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" id="btnSubmit" class="btn btn-primary text-sm shadow-md flex items-center gap-2">
                <i class="fas fa-upload"></i> <span id="btnSubmitText">Iniciar Importação</span>
            </button>
        </div>
    </form>
</div>

<script>
let currentFilesList = [];
const MAX_FILE_SIZE = <?= UPLOAD_MAX_SIZE ?>;
const MAX_FILE_SIZE_MB = <?= $maxSizeMb ?>;

const MESES = {
    1: 'Janeiro', 2: 'Fevereiro', 3: 'Março', 4: 'Abril',
    5: 'Maio', 6: 'Junho', 7: 'Julho', 8: 'Agosto',
    9: 'Setembro', 10: 'Outubro', 11: 'Novembro', 12: 'Dezembro'
};

const FRIENDLY_NAMES = {
    'apuracao_financeira': 'Apuração Financeira',
    'ponto_equilibrio': 'Ponto de Equilíbrio',
    'resumo_administracao': 'Resumo de Administração',
    'preco_venda': 'Preço de Venda',
    'resumo_apuracao': 'Resumo Apuração',
    'powerbi': 'Power BI',
    'power_bi': 'Power BI'
};

document.addEventListener('DOMContentLoaded', () => {
    const zone = document.getElementById('uploadZone');
    const input = document.getElementById('fileInput');

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.classList.add('border-proxcel', 'bg-emerald-50/40');
    });

    zone.addEventListener('dragleave', () => {
        zone.classList.remove('border-proxcel', 'bg-emerald-50/40');
    });

    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.classList.remove('border-proxcel', 'bg-emerald-50/40');
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleNewFiles(e.dataTransfer.files);
        }
    });

    input.addEventListener('change', () => {
        if (input.files && input.files.length > 0) {
            handleNewFiles(input.files);
        }
    });

    // Form submit loading
    document.getElementById('formUploadDocumentos').addEventListener('submit', function(e) {
        if (currentFilesList.length === 0) {
            e.preventDefault();
            alert('Por favor, selecione pelo menos um arquivo para importar.');
            return;
        }

        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando ' + currentFilesList.length + ' arquivo(s)...';
    });
});

function handleNewFiles(fileList) {
    let hasOversize = false;

    for (let i = 0; i < fileList.length; i++) {
        const file = fileList[i];
        if (file.size > MAX_FILE_SIZE) {
            hasOversize = true;
            continue;
        }

        // Evita duplicatas exatas na lista
        const exists = currentFilesList.some(f => f.name === file.name && f.size === file.size);
        if (!exists) {
            currentFilesList.push(file);
        }
    }

    if (hasOversize) {
        alert('Um ou mais arquivos foram ignorados por excederem o tamanho máximo de ' + MAX_FILE_SIZE_MB + 'MB.');
    }

    syncFileInput();
    renderFilesList();
}

function removeFileByIndex(index) {
    currentFilesList.splice(index, 1);
    syncFileInput();
    renderFilesList();
}

function clearAllFiles() {
    currentFilesList = [];
    syncFileInput();
    renderFilesList();
}

function syncFileInput() {
    const dataTransfer = new DataTransfer();
    currentFilesList.forEach(file => dataTransfer.items.add(file));
    document.getElementById('fileInput').files = dataTransfer.files;
}

function parseClientFileName(filename) {
    const dotIdx = filename.lastIndexOf('.');
    const baseName = dotIdx !== -1 ? filename.substring(0, dotIdx) : filename;
    const ext = dotIdx !== -1 ? filename.substring(dotIdx + 1).toLowerCase() : '';
    const parts = baseName.split('_');

    const periodRaw = parts[0] || '';
    const nameParts = parts.slice(1);
    const rawKey = nameParts.join('_').toLowerCase();

    let docBaseTitle = FRIENDLY_NAMES[rawKey] || null;
    if (!docBaseTitle) {
        if (nameParts.length > 0) {
            docBaseTitle = nameParts.join(' ').replace(/[-_]/g, ' ');
            docBaseTitle = ucWords(docBaseTitle);
        } else {
            docBaseTitle = ucWords(baseName.replace(/[-_]/g, ' '));
        }
    }

    let periodType = null;
    let periodLabel = null;
    let finalTitle = docBaseTitle;

    // 1. Mensal: mmaaaa (ex: 052026)
    const matchMensal = periodRaw.match(/^(\d{2})(\d{4})$/);
    if (matchMensal) {
        const mes = parseInt(matchMensal[1], 10);
        const ano = parseInt(matchMensal[2], 10);
        if (mes >= 1 && mes <= 12 && ano >= 1900 && ano <= 2100) {
            periodType = 'mensal';
            const nomeMes = MESES[mes];
            periodLabel = `${nomeMes}/${ano}`;
            finalTitle = `${docBaseTitle} - ${nomeMes} ${ano}`;
        }
    }

    // 2. Trimestral: 1Taaaa..4Taaaa
    const matchTri = !periodType ? periodRaw.match(/^([1-4])[Tt](\d{4})$/) : null;
    if (matchTri) {
        const tri = matchTri[1];
        const ano = matchTri[2];
        periodType = 'trimestral';
        periodLabel = `${tri}º Trimestre/${ano}`;
        finalTitle = `${docBaseTitle} - ${tri}º Trimestre ${ano}`;
    }

    // 3. Anual: aaaa
    const matchAno = !periodType ? periodRaw.match(/^(\d{4})$/) : null;
    if (matchAno) {
        const ano = matchAno[1];
        periodType = 'anual';
        periodLabel = `Ano ${ano}`;
        finalTitle = `${docBaseTitle} - ${ano}`;
    }

    if (!periodType) {
        periodType = 'geral';
        periodLabel = 'Geral';
        finalTitle = ucWords(baseName.replace(/[-_]/g, ' '));
    }

    return {
        ext: ext,
        periodType: periodType,
        periodLabel: periodLabel,
        finalTitle: finalTitle
    };
}

function ucWords(str) {
    return str.replace(/\b[a-z]/g, letter => letter.toUpperCase());
}

function getFileIcon(ext) {
    switch (ext) {
        case 'xlsx':
        case 'xls':
        case 'csv':
            return '<i class="fas fa-file-excel text-emerald-600 text-base"></i>';
        case 'pdf':
            return '<i class="fas fa-file-pdf text-rose-600 text-base"></i>';
        case 'pbix':
            return '<i class="fas fa-chart-pie text-amber-600 text-base"></i>';
        case 'doc':
        case 'docx':
            return '<i class="fas fa-file-word text-blue-600 text-base"></i>';
        case 'zip':
        case 'rar':
            return '<i class="fas fa-file-archive text-yellow-600 text-base"></i>';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp':
            return '<i class="fas fa-file-image text-purple-600 text-base"></i>';
        default:
            return '<i class="fas fa-file text-gray-400 text-base"></i>';
    }
}

function renderFilesList() {
    const container = document.getElementById('selectedFilesContainer');
    const tbody = document.getElementById('filesTableBody');
    const countSpan = document.getElementById('filesCount');
    const totalSizeSpan = document.getElementById('filesTotalSize');
    const btnText = document.getElementById('btnSubmitText');

    if (currentFilesList.length === 0) {
        container.classList.add('hidden');
        btnText.textContent = 'Iniciar Importação';
        return;
    }

    container.classList.remove('hidden');
    countSpan.textContent = currentFilesList.length;

    let totalBytes = 0;
    tbody.innerHTML = '';

    currentFilesList.forEach((file, index) => {
        totalBytes += file.size;
        const info = parseClientFileName(file.name);

        let badgeClass = 'bg-gray-100 text-gray-700 border-gray-200';
        let badgeIcon = 'fa-tag';
        if (info.periodType === 'mensal') {
            badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            badgeIcon = 'fa-calendar-alt';
        } else if (info.periodType === 'trimestral') {
            badgeClass = 'bg-blue-50 text-blue-700 border-blue-200';
            badgeIcon = 'fa-chart-pie';
        } else if (info.periodType === 'anual') {
            badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
            badgeIcon = 'fa-calendar';
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-gray-50/80 transition';
        tr.innerHTML = `
            <td class="py-2.5 px-3">
                <div class="flex items-center gap-2">
                    ${getFileIcon(info.ext)}
                    <span class="font-mono text-gray-700 truncate max-w-[220px]" title="${escapeHtml(file.name)}">
                        ${escapeHtml(file.name)}
                    </span>
                </div>
            </td>
            <td class="py-2.5 px-3">
                <span class="font-bold text-gray-800">${escapeHtml(info.finalTitle)}</span>
            </td>
            <td class="py-2.5 px-3">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold border ${badgeClass}">
                    <i class="fas ${badgeIcon}"></i> ${escapeHtml(info.periodLabel)}
                </span>
            </td>
            <td class="py-2.5 px-3 text-gray-500 font-mono">
                ${formatFileSize(file.size)}
            </td>
            <td class="py-2.5 px-3 text-center">
                <button type="button" onclick="removeFileByIndex(${index})" class="text-gray-400 hover:text-red-500 transition p-1" title="Remover este arquivo">
                    <i class="fas fa-times"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    totalSizeSpan.textContent = `• ${formatFileSize(totalBytes)}`;
    btnText.textContent = `Importar ${currentFilesList.length} Arquivo(s)`;
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
