<?php
/**
 * Proxcel - Upload de Documentos
 */
$pageTitle = 'Upload de Documentos';
require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../includes/upload.php';
require_once __DIR__ . '/../../includes/mail_helper.php';

Permissions::requirePermission('documentos.criar');

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");
$categorias = db()->fetchAll("SELECT * FROM " . tb('categorias_documentos') . " WHERE status = 'ativo' ORDER BY nome");

$errors = [];
$success = false;

if (isPost()) {
    CSRF::validateOrDie();
    
    $clienteId = (int)post('cliente_id');
    $categoriaId = (int)post('categoria_id') ?: null;
    $nome = post('nome');
    $descricao = post('descricao');
    $dataDoc = post('data_documento');
    $notificar = isset($_POST['notificar']);

    if (empty($nome)) $errors[] = 'Nome do documento é obrigatório.';
    if (!$clienteId) $errors[] = 'Selecione um cliente.';
    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Selecione um arquivo.';
    }

    if (empty($errors)) {
        $resultado = Upload::process($_FILES['arquivo'], $clienteId);
        
        if ($resultado['success']) {
            $docId = db()->insert(
                "INSERT INTO " . tb('documentos') . " 
                 (cliente_id, categoria_id, nome, descricao, nome_original, nome_arquivo, mime_type, tamanho, extensao, data_documento, criado_por, criado_por_tipo) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $clienteId,
                    $categoriaId,
                    $nome,
                    $descricao,
                    $resultado['nome_original'],
                    $resultado['nome_arquivo'],
                    $resultado['mime_type'],
                    $resultado['tamanho'],
                    $resultado['extensao'],
                    $dataDoc ?: null,
                    $currentUser['id'],
                    $currentUser['tipo_acesso']
                ]
            );

            Security::logChange('documentos', $docId, 'criar', null, ['nome' => $nome, 'cliente_id' => $clienteId]);

            // Notifica usuários do cliente
            if ($notificar) {
                $usersCliente = db()->fetchAll(
                    "SELECT nome, email FROM " . tb('usuarios_clientes') . " WHERE cliente_id = ? AND status = 'ativo'",
                    [$clienteId]
                );
                foreach ($usersCliente as $uc) {
                    MailHelper::sendDocumentNotification($uc['email'], $uc['nome'], $nome);
                }
            }

            setFlash('success', 'Documento enviado com sucesso!');
            redirect(APP_URL . '/pages/documentos/index.php');
        } else {
            $errors[] = $resultado['message'];
        }
    }
}

$maxSizeMb = UPLOAD_MAX_SIZE / (1024 * 1024);
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Documentos', 'url' => APP_URL . '/pages/documentos/index.php'],
    ['label' => 'Upload Individual', 'url' => '#']
]) ?>

<!-- Abas de Navegação / Ações -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-2 bg-gray-200/70 p-1.5 rounded-2xl">
        <a href="<?= APP_URL ?>/pages/documentos/upload.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-white text-proxcel shadow-sm transition flex items-center gap-2">
            <i class="fas fa-file-upload"></i> Upload Individual
        </a>
        <a href="<?= APP_URL ?>/pages/documentos/importar_mensal.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-gray-600 hover:text-gray-900 transition flex items-center gap-2">
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
    <div class="p-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">
            <i class="fas fa-cloud-upload-alt text-proxcel mr-1"></i> Upload Individual de Documento
        </h3>
    </div>

    <form method="POST" action="" enctype="multipart/form-data" class="p-5">
        <?= CSRF::field() ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <ul class="text-red-600 text-sm list-disc list-inside">
                <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="form-grid mb-6">
            <div class="form-group col-6">
                <label>Cliente <span class="required">*</span></label>
                <select name="cliente_id" class="form-control select2" required>
                    <option value="">Selecione o cliente...</option>
                    <?php foreach ($clientes as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-6">
                <label>Nome do Documento <span class="required">*</span></label>
                <input type="text" name="nome" class="form-control" placeholder="Ex: Contrato de Prestação de Serviços" required>
            </div>
            <div class="form-group col-4">
                <label>Categoria</label>
                <select name="categoria_id" class="form-control select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= e($cat['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-4">
                <label>Data do Documento</label>
                <input type="date" name="data_documento" class="form-control">
            </div>
            <div class="form-group col-4">
                <label>Descrição</label>
                <input type="text" name="descricao" class="form-control" placeholder="Breve descrição...">
            </div>
        </div>

        <!-- Upload Zone -->
        <div class="mb-6">
            <label class="block text-sm font-semibold text-gray-500 mb-2">Arquivo <span class="required">*</span></label>
            <div class="upload-zone" id="uploadZone">
                <i class="fas fa-cloud-upload-alt upload-icon block"></i>
                <p class="text-gray-600 font-semibold mb-1">Arraste e solte o arquivo aqui</p>
                <p class="text-gray-400 text-sm mb-3">ou clique para selecionar</p>
                <p class="text-xs text-gray-400">
                    PDF, DOC, DOCX, XLS, XLSX, PBIX, JPG, PNG, CSV, TXT, ZIP, RAR<br>
                    Máximo: <?= $maxSizeMb ?>MB
                </p>
                <input type="file" name="arquivo" id="fileInput" class="hidden" 
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.pbix,.jpg,.jpeg,.png,.gif,.webp,.csv,.txt,.zip,.rar" required>
            </div>
            <div id="fileInfo" class="hidden mt-3 bg-gray-50 rounded-xl p-4 flex items-center gap-3">
                <i class="fas fa-file text-2xl text-proxcel" id="fileIcon"></i>
                <div>
                    <p class="font-semibold text-gray-800 text-sm" id="fileName"></p>
                    <p class="text-xs text-gray-400" id="fileSize"></p>
                </div>
                <button type="button" onclick="removeFile()" class="ml-auto text-gray-400 hover:text-red-500 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Notificar -->
        <div class="form-grid mb-6">
            <div class="form-group col-12">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="notificar" value="1" class="w-4 h-4 text-proxcel rounded border-gray-300 focus:ring-proxcel">
                    <span>Notificar usuários do cliente por e-mail</span>
                </label>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/documentos/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Enviar Documento</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initUploadZone('#uploadZone', '#fileInput');
    
    document.getElementById('fileInput').addEventListener('change', function() {
        if (this.files.length > 0) {
            const file = this.files[0];
            const maxSize = <?= UPLOAD_MAX_SIZE ?>;
            
            if (file.size > maxSize) {
                showError('Arquivo excede o tamanho máximo de <?= $maxSizeMb ?>MB.');
                this.value = '';
                return;
            }
            
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            document.getElementById('fileInfo').classList.remove('hidden');
            document.getElementById('uploadZone').style.display = 'none';
        }
    });
});

function removeFile() {
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').classList.add('hidden');
    document.getElementById('uploadZone').style.display = '';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
