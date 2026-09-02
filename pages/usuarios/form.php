<?php
/**
 * Proxcel - Formulário de Usuário
 */
$pageTitle = 'Novo Usuário';
require_once __DIR__ . '/../../templates/header.php';

$id = (int)get('id');
$usuario = null;

if ($id) {
    Permissions::requirePermission('usuarios.editar');
    $usuario = db()->fetch("SELECT * FROM " . tb('usuarios') . " WHERE id = ?", [$id]);
    if (!$usuario) { setFlash('error', 'Usuário não encontrado.'); redirect(APP_URL . '/pages/usuarios/index.php'); }
    $pageTitle = 'Editar Usuário';
} else {
    Permissions::requirePermission('usuarios.criar');
}

if (isPost()) {
    CSRF::validateOrDie();
    
    $dados = [
        'nome' => post('nome'),
        'email' => post('email'),
        'tipo' => post('tipo', 'operador'),
        'telefone' => post('telefone'),
        'status' => post('status', 'ativo'),
    ];

    $senha = $_POST['senha'] ?? '';
    $errors = [];

    if (empty($dados['nome'])) $errors[] = 'Nome é obrigatório.';
    if (empty($dados['email'])) $errors[] = 'E-mail é obrigatório.';
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    
    // Senha obrigatória para novo, opcional para edição
    if (!$id && empty($senha)) {
        $errors[] = 'Senha é obrigatória.';
    }
    if (!empty($senha)) {
        $passErrors = Auth::validatePassword($senha);
        $errors = array_merge($errors, $passErrors);
    }

    // Verifica e-mail duplicado
    $exists = db()->fetch(
        "SELECT id FROM " . tb('usuarios') . " WHERE email = ? AND id != ?",
        [$dados['email'], $id]
    );
    if ($exists) $errors[] = 'Este e-mail já está cadastrado.';

    if (empty($errors)) {
        try {
            if ($id) {
                $anterior = $usuario;
                $sets = [];
                $params = [];
                foreach ($dados as $key => $value) {
                    $sets[] = "`{$key}` = ?";
                    $params[] = $value;
                }
                if (!empty($senha)) {
                    $sets[] = "`senha` = ?";
                    $params[] = Auth::hashPassword($senha);
                }
                $params[] = $id;
                db()->update("UPDATE " . tb('usuarios') . " SET " . implode(', ', $sets) . " WHERE id = ?", $params);
                Security::logChange('usuarios', $id, 'editar', $anterior, $dados);
                setFlash('success', 'Usuário atualizado com sucesso!');
            } else {
                $dados['senha'] = Auth::hashPassword($senha);
                $fields = array_keys($dados);
                $placeholders = array_fill(0, count($fields), '?');
                $newId = db()->insert(
                    "INSERT INTO " . tb('usuarios') . " (`" . implode('`,`', $fields) . "`) VALUES (" . implode(',', $placeholders) . ")",
                    array_values($dados)
                );
                Security::logChange('usuarios', $newId, 'criar', null, $dados);
                setFlash('success', 'Usuário criado com sucesso!');
            }
            redirect(APP_URL . '/pages/usuarios/index.php');
        } catch (\Exception $e) {
            $errors[] = APP_DEBUG ? $e->getMessage() : 'Erro ao salvar.';
        }
    }

    if (!empty($errors)) { $usuario = $dados; $usuario['id'] = $id; }
}
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Usuários', 'url' => APP_URL . '/pages/usuarios/index.php'],
    ['label' => $id ? 'Editar' : 'Novo', 'url' => '#']
]) ?>

<div class="card animate-fadeInUp">
    <div class="p-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">
            <i class="fas fa-<?= $id ? 'edit' : 'user-plus' ?> text-proxcel mr-1"></i>
            <?= $id ? 'Editar Usuário' : 'Novo Usuário' ?>
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
                <label>Nome <span class="required">*</span></label>
                <input type="text" name="nome" class="form-control" value="<?= e($usuario['nome'] ?? '') ?>" required>
            </div>
            <div class="form-group col-6">
                <label>E-mail <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= e($usuario['email'] ?? '') ?>" required>
            </div>
            <div class="form-group col-4">
                <label>Tipo <span class="required">*</span></label>
                <select name="tipo" class="form-control">
                    <option value="operador" <?= ($usuario['tipo'] ?? 'operador') === 'operador' ? 'selected' : '' ?>>Operador</option>
                    <option value="admin" <?= ($usuario['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrador</option>
                </select>
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
                    <option value="bloqueado" <?= ($usuario['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
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

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/usuarios/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $id ? 'Atualizar' : 'Salvar' ?></button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
