<?php
/**
 * Proxcel - Formulário de Usuário de Cliente
 */
$pageTitle = 'Novo Usuário de Cliente';
require_once __DIR__ . '/../../templates/header.php';

$id = (int)get('id');
$usuario = null;

if ($id) {
    Permissions::requirePermission('usuarios_clientes.editar');
    $usuario = db()->fetch("SELECT * FROM " . tb('usuarios_clientes') . " WHERE id = ?", [$id]);
    if (!$usuario) { setFlash('error', 'Usuário não encontrado.'); redirect(APP_URL . '/pages/usuarios_clientes/index.php'); }
    $pageTitle = 'Editar Usuário de Cliente';
} else {
    Permissions::requirePermission('usuarios_clientes.criar');
}

$clientes = db()->fetchAll("SELECT id, razao_social, nome_fantasia FROM " . tb('clientes') . " WHERE status = 'ativo' ORDER BY razao_social");

if (isPost()) {
    CSRF::validateOrDie();
    
    $dados = [
        'cliente_id' => (int)post('cliente_id'),
        'nome' => post('nome'),
        'email' => post('email'),
        'telefone' => post('telefone'),
        'status' => post('status', 'ativo'),
    ];

    $senha = $_POST['senha'] ?? '';
    $enviarEmail = isset($_POST['enviar_email']);
    $errors = [];

    if (empty($dados['nome'])) $errors[] = 'Nome é obrigatório.';
    if (empty($dados['email'])) $errors[] = 'E-mail é obrigatório.';
    if (!$dados['cliente_id']) $errors[] = 'Selecione um cliente.';
    if (!$id && empty($senha)) $errors[] = 'Senha é obrigatória.';
    if (!empty($senha)) {
        $passErrors = Auth::validatePassword($senha);
        $errors = array_merge($errors, $passErrors);
    }

    $exists = db()->fetch("SELECT id FROM " . tb('usuarios_clientes') . " WHERE email = ? AND id != ?", [$dados['email'], $id]);
    if ($exists) $errors[] = 'E-mail já cadastrado.';

    // Also check system users table
    $existsSys = db()->fetch("SELECT id FROM " . tb('usuarios') . " WHERE email = ?", [$dados['email']]);
    if ($existsSys && (!$id || $existsSys['id'] != $id)) $errors[] = 'E-mail já em uso por usuário do sistema.';

    if (empty($errors)) {
        try {
            if ($id) {
                $anterior = $usuario;
                $sets = []; $params = [];
                foreach ($dados as $key => $value) { $sets[] = "`{$key}` = ?"; $params[] = $value; }
                if (!empty($senha)) { $sets[] = "`senha` = ?"; $params[] = Auth::hashPassword($senha); }
                $params[] = $id;
                db()->update("UPDATE " . tb('usuarios_clientes') . " SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                Security::logChange('usuarios_clientes', $id, 'editar', $anterior, $dados);
                setFlash('success', 'Usuário atualizado!');
            } else {
                $dados['senha'] = Auth::hashPassword($senha);
                $fields = array_keys($dados);
                $placeholders = array_fill(0, count($fields), '?');
                $newId = db()->insert(
                    "INSERT INTO " . tb('usuarios_clientes') . " (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', $placeholders) . ")",
                    array_values($dados)
                );
                Security::logChange('usuarios_clientes', $newId, 'criar', null, $dados);
                
                // Envia e-mail de boas-vindas
                if ($enviarEmail) {
                    require_once __DIR__ . '/../../includes/mail_helper.php';
                    MailHelper::sendWelcome($dados['email'], $dados['nome'], $senha);
                }
                
                setFlash('success', 'Usuário criado com sucesso!');
            }
            redirect(APP_URL . '/pages/usuarios_clientes/index.php');
        } catch (\Exception $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Erro ao salvar.';
        }
    }
    if (!empty($errors)) { $usuario = $dados; $usuario['id'] = $id; }
}
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Usuários de Clientes', 'url' => APP_URL . '/pages/usuarios_clientes/index.php'],
    ['label' => $id ? 'Editar' : 'Novo', 'url' => '#']
]) ?>

<div class="card animate-fadeInUp">
    <div class="p-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">
            <i class="fas fa-<?= $id ? 'edit' : 'user-plus' ?> text-proxcel mr-1"></i>
            <?= $id ? 'Editar Usuário' : 'Novo Usuário de Cliente' ?>
        </h3>
    </div>

    <form method="POST" action="" class="p-5">
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
                    <option value="<?= $c['id'] ?>" <?= ($usuario['cliente_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['nome_fantasia'] ?: $c['razao_social']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-6">
                <label>Nome <span class="required">*</span></label>
                <input type="text" name="nome" class="form-control" value="<?= e($usuario['nome'] ?? '') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>E-mail <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= e($usuario['email'] ?? '') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Telefone</label>
                <input type="text" name="telefone" class="form-control" data-mask="phone" value="<?= e($usuario['telefone'] ?? '') ?>">
            </div>
            <div class="form-group col-4">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="ativo" <?= ($usuario['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= ($usuario['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
        </div>

        <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4">Senha</h4>
        <div class="form-grid mb-6">
            <div class="form-group col-6">
                <label>Senha <?= !$id ? '<span class="required">*</span>' : '(deixe em branco para manter)' ?></label>
                <input type="password" id="senha" name="senha" class="form-control" <?= !$id ? 'required' : '' ?>>
                <div id="senha-feedback" class="flex flex-wrap gap-2 mt-1"></div>
            </div>
            <div class="form-group col-6">
                <label>Confirmar Senha</label>
                <input type="password" name="confirmar_senha" class="form-control">
            </div>
        </div>

        <?php if (!$id): ?>
        <div class="form-grid mb-6">
            <div class="form-group col-12">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="enviar_email" value="1" checked class="w-4 h-4 text-proxcel rounded border-gray-300 focus:ring-proxcel">
                    <span>Enviar e-mail de boas-vindas com dados de acesso</span>
                </label>
            </div>
        </div>
        <?php endif; ?>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/usuarios_clientes/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $id ? 'Atualizar' : 'Salvar' ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
