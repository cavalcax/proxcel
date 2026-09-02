<?php
/**
 * Proxcel - Permissões Individuais de Usuário
 */
$pageTitle = 'Permissões do Usuário';
require_once __DIR__ . '/../../templates/header.php';

Permissions::requirePermission('usuarios.editar');

$id = (int)get('id');
$tipoAcesso = get('tipo_acesso', 'sistema');

if ($tipoAcesso === 'cliente') {
    $usuario = db()->fetch("SELECT * FROM " . tb('usuarios_clientes') . " WHERE id = ?", [$id]);
} else {
    $usuario = db()->fetch("SELECT * FROM " . tb('usuarios') . " WHERE id = ?", [$id]);
}

if (!$usuario) {
    setFlash('error', 'Usuário não encontrado.');
    redirect(APP_URL . '/pages/usuarios/index.php');
}

$userTipo = $usuario['tipo'] ?? 'cliente';

// Processa salvar
if (isPost()) {
    CSRF::validateOrDie();
    
    $permissoes = $_POST['permissoes'] ?? [];
    $overrides = [];
    
    foreach ($permissoes as $permId => $valor) {
        if ($valor !== 'default') {
            $overrides[$permId] = ($valor === '1');
        }
    }
    
    Permissions::saveUserPermissions($id, $tipoAcesso, $overrides);
    Security::logChange('usuario_permissoes', $id, 'editar', null, ['overrides' => $overrides]);
    setFlash('success', 'Permissões atualizadas com sucesso!');
    
    $returnUrl = $tipoAcesso === 'cliente' 
        ? APP_URL . '/pages/usuarios_clientes/index.php'
        : APP_URL . '/pages/usuarios/index.php';
    redirect($returnUrl);
}

$allPermissions = Permissions::getUserPermissions($id, $userTipo, $tipoAcesso);

// Agrupa por módulo
$grouped = [];
foreach ($allPermissions as $perm) {
    $grouped[$perm['modulo']][] = $perm;
}
?>

<?= breadcrumb([
    ['label' => 'Dashboard', 'url' => APP_URL . '/pages/dashboard.php'],
    ['label' => 'Usuários', 'url' => APP_URL . '/pages/usuarios/index.php'],
    ['label' => 'Permissões', 'url' => '#']
]) ?>

<div class="card animate-fadeInUp">
    <div class="p-5 border-b border-gray-100">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-proxcel to-proxcel-dark flex items-center justify-center text-white font-bold">
                <?= getInitials($usuario['nome']) ?>
            </div>
            <div>
                <h3 class="text-lg font-bold text-gray-800"><?= e($usuario['nome']) ?></h3>
                <p class="text-sm text-gray-500"><?= e($usuario['email']) ?> · Tipo: <strong><?= ucfirst($userTipo) ?></strong></p>
            </div>
        </div>
    </div>

    <form method="POST" action="" class="p-5">
        <?= CSRF::field() ?>

        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <p class="text-blue-700 text-sm">
                <i class="fas fa-info-circle mr-1"></i>
                <strong>Herança:</strong> O usuário herda permissões do tipo "<strong><?= ucfirst($userTipo) ?></strong>". 
                Use "Customizado" para conceder ou revogar permissões individuais.
            </p>
        </div>

        <?php foreach ($grouped as $modulo => $perms): ?>
        <div class="mb-6">
            <h4 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-puzzle-piece text-proxcel"></i> <?= ucfirst(e($modulo)) ?>
            </h4>
            <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                <?php foreach ($perms as $perm): ?>
                <div class="flex items-center justify-between gap-4 bg-white rounded-lg p-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?= e($perm['descricao']) ?></p>
                        <p class="text-xs text-gray-400"><?= e($perm['chave']) ?></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <?php if ($perm['from_tipo']): ?>
                            <span class="text-xs text-green-600"><i class="fas fa-check-circle"></i> Do tipo</span>
                        <?php else: ?>
                            <span class="text-xs text-gray-400"><i class="fas fa-minus-circle"></i> Não herdada</span>
                        <?php endif; ?>
                        <select name="permissoes[<?= $perm['id'] ?>]" class="form-control !py-1.5 !text-xs !w-32">
                            <option value="default" <?= $perm['override'] === null ? 'selected' : '' ?>>Padrão (tipo)</option>
                            <option value="1" <?= $perm['override'] === true ? 'selected' : '' ?>>✅ Conceder</option>
                            <option value="0" <?= $perm['override'] === false ? 'selected' : '' ?>>❌ Revogar</option>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?= APP_URL ?>/pages/usuarios/index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Permissões</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
