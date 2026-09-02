<?php
/**
 * Proxcel - Formulário de Cliente
 */
$pageTitle = 'Novo Cliente';
require_once __DIR__ . '/../../templates/header.php';

$id = (int)get('id');
$cliente = null;

if ($id) {
    Permissions::requirePermission('clientes.editar');
    $cliente = db()->fetch("SELECT * FROM " . tb('clientes') . " WHERE id = ?", [$id]);
    if (!$cliente) {
        setFlash('error', 'Cliente não encontrado.');
        redirect(APP_URL . '/pages/clientes/index.php');
    }
    $pageTitle = 'Editar Cliente';
} else {
    Permissions::requirePermission('clientes.criar');
}

// Processa formulário
if (isPost()) {
    CSRF::validateOrDie();
    
    $dados = [
        'razao_social' => post('razao_social'),
        'nome_fantasia' => post('nome_fantasia'),
        'cnpj' => post('cnpj'),
        'cpf' => post('cpf'),
        'email' => post('email'),
        'telefone' => post('telefone'),
        'celular' => post('celular'),
        'cep' => post('cep'),
        'endereco' => post('endereco'),
        'numero' => post('numero'),
        'complemento' => post('complemento'),
        'bairro' => post('bairro'),
        'cidade' => post('cidade'),
        'estado' => post('estado'),
        'observacoes' => post('observacoes'),
        'status' => post('status', 'ativo'),
    ];

    // Validações
    $errors = [];
    if (empty($dados['razao_social'])) $errors[] = 'Razão social é obrigatória.';
    if (empty($dados['email'])) $errors[] = 'E-mail é obrigatório.';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';

    if (empty($errors)) {
        try {
            if ($id) {
                $dadosAnteriores = $cliente;
                $dados['atualizado_por'] = $currentUser['id'];
                
                $sets = [];
                $params = [];
                foreach ($dados as $key => $value) {
                    $sets[] = "`{$key}` = ?";
                    $params[] = $value;
                }
                $params[] = $id;
                
                db()->update("UPDATE " . tb('clientes') . " SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                Security::logChange('clientes', $id, 'editar', $dadosAnteriores, $dados);
                setFlash('success', 'Cliente atualizado com sucesso!');
            } else {
                $dados['criado_por'] = $currentUser['id'];
                
                $fields = array_keys($dados);
                $placeholders = array_fill(0, count($fields), '?');
                
                $newId = db()->insert(
                    "INSERT INTO " . tb('clientes') . " (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', $placeholders) . ")",
                    array_values($dados)
                );
                Security::logChange('clientes', $newId, 'criar', null, $dados);
                setFlash('success', 'Cliente cadastrado com sucesso!');
            }
            redirect(APP_URL . '/pages/clientes/index.php');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $errors[] = 'CNPJ ou CPF já cadastrado.';
            } else {
                $errors[] = APP_DEBUG ? $e->getMessage() : 'Erro ao salvar. Tente novamente.';
            }
        }
    }

    if (!empty($errors)) {
        $cliente = $dados;
        $cliente['id'] = $id;
    }
}

$estados = estadosBR();
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Clientes', 'url' => APP_URL . '/pages/clientes/index.php'],
    ['label' => $id ? 'Editar' : 'Novo', 'url' => '#']
]) ?>

<div class="card animate-fadeInUp">
    <div class="p-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">
            <i class="fas fa-<?= $id ? 'edit' : 'plus-circle' ?> text-proxcel mr-1"></i>
            <?= $id ? 'Editar Cliente' : 'Novo Cliente' ?>
        </h3>
    </div>

    <form method="POST" action="" class="p-5">
        <?= CSRF::field() ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
            <p class="text-red-600 text-sm font-semibold mb-1"><i class="fas fa-exclamation-circle"></i> Erro(s):</p>
            <ul class="text-red-600 text-sm list-disc list-inside">
                <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <!-- Dados Principais -->
        <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4">Dados da Empresa</h4>
        <div class="form-grid mb-6">
            <div class="form-group col-8">
                <label>Razão Social <span class="required">*</span></label>
                <input type="text" name="razao_social" class="form-control" value="<?= e($cliente['razao_social'] ?? '') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="ativo" <?= ($cliente['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($cliente['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="form-group col-6">
                <label>Nome Fantasia</label>
                <input type="text" name="nome_fantasia" class="form-control" value="<?= e($cliente['nome_fantasia'] ?? '') ?>">
            </div>
            <div class="form-group col-3">
                <label>CNPJ</label>
                <input type="text" name="cnpj" class="form-control" data-mask="cnpj" value="<?= e($cliente['cnpj'] ?? '') ?>">
            </div>
            <div class="form-group col-3">
                <label>CPF</label>
                <input type="text" name="cpf" class="form-control" data-mask="cpf" value="<?= e($cliente['cpf'] ?? '') ?>">
            </div>
        </div>

        <!-- Contato -->
        <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4">Contato</h4>
        <div class="form-grid mb-6">
            <div class="form-group col-6">
                <label>E-mail <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= e($cliente['email'] ?? '') ?>" required>
            </div>
            <div class="form-group col-3">
                <label>Telefone</label>
                <input type="text" name="telefone" class="form-control" data-mask="phone" value="<?= e($cliente['telefone'] ?? '') ?>">
            </div>
            <div class="form-group col-3">
                <label>Celular</label>
                <input type="text" name="celular" class="form-control" data-mask="phone" value="<?= e($cliente['celular'] ?? '') ?>">
            </div>
        </div>

        <!-- Endereço -->
        <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4">Endereço</h4>
        <div class="form-grid mb-6">
            <div class="form-group col-2">
                <label>CEP</label>
                <input type="text" name="cep" class="form-control" data-mask="cep" value="<?= e($cliente['cep'] ?? '') ?>">
            </div>
            <div class="form-group col-6">
                <label>Endereço</label>
                <input type="text" name="endereco" class="form-control" value="<?= e($cliente['endereco'] ?? '') ?>">
            </div>
            <div class="form-group col-2">
                <label>Número</label>
                <input type="text" name="numero" class="form-control" value="<?= e($cliente['numero'] ?? '') ?>">
            </div>
            <div class="form-group col-2">
                <label>Complemento</label>
                <input type="text" name="complemento" class="form-control" value="<?= e($cliente['complemento'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Bairro</label>
                <input type="text" name="bairro" class="form-control" value="<?= e($cliente['bairro'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Cidade</label>
                <input type="text" name="cidade" class="form-control" value="<?= e($cliente['cidade'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Estado</label>
                <select name="estado" class="form-control select2">
                    <option value="">Selecione...</option>
                    <?php foreach ($estados as $uf => $nome): ?>
                    <option value="<?= $uf ?>" <?= ($cliente['estado'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?> - <?= $nome ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Observações -->
        <div class="form-grid mb-6">
            <div class="form-group col-12">
                <label>Observações</label>
                <textarea name="observacoes" class="form-control" rows="3"><?= e($cliente['observacoes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/clientes/index.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $id ? 'Atualizar' : 'Salvar' ?>
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
