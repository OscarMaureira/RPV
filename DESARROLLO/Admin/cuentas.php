<?php
declare(strict_types=1);
$adminHome = '/cuentas.php';
require __DIR__ . '/acceso.php';
require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('La sesión caducó. Recarga la página.');
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $username = trim((string) ($_POST['usuario'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        if (!in_array($action, ['create', 'update', 'delete'], true)) throw new InvalidArgumentException('Acción no válida.');
        if ($action !== 'delete' && (!preg_match('/^[A-Za-z0-9._-]{3,80}$/D', $username))) {
            throw new InvalidArgumentException('El usuario debe tener entre 3 y 80 caracteres: letras, números, punto, guion o guion bajo.');
        }
        if (($action === 'create' || $password !== '') && (textLength($password) < 7 || textLength($password) > 128)) {
            throw new InvalidArgumentException('La contraseña debe tener entre 7 y 128 caracteres.');
        }
        $pdo->beginTransaction();
        $identity = $pdo->prepare('SELECT usuario FROM admin_usuarios WHERE id = ?');
        $identity->execute([(int) $_SESSION['admin_user_id']]);
        $isMainAdmin = $identity->fetchColumn() === 'admin';
        if ($action === 'create' && $username === 'admin' && !$isMainAdmin) throw new InvalidArgumentException('Solo admin puede administrar esa cuenta.');
        if ($action === 'create') {
            $stmt = $pdo->prepare('INSERT INTO admin_usuarios (usuario, password_hash, activo) VALUES (?, ?, 1)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            adminAudit($pdo, 'cuenta_creada', 'cuenta', (string) $pdo->lastInsertId(), ['usuario' => $username, 'activo' => 1]);
            $message = 'Cuenta creada.';
        } else {
            if (!$id) throw new InvalidArgumentException('Selecciona una cuenta válida.');
            $stmt = $pdo->prepare('SELECT id, usuario, activo FROM admin_usuarios WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) throw new InvalidArgumentException('La cuenta ya no existe.');
            $targetName = $pdo->prepare('SELECT usuario FROM admin_usuarios WHERE id = ?');
            $targetName->execute([$id]);
            if (!$isMainAdmin && ($targetName->fetchColumn() === 'admin' || $username === 'admin')) {
                throw new InvalidArgumentException('Solo admin puede administrar esa cuenta.');
            }
            if ($action === 'delete' && (string) ($_POST['confirm_delete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Confirma la eliminación antes de continuar.');
            }
            $active = $action === 'update' ? (int) (($_POST['activo'] ?? '') === '1') : 0;
            if ((int) $id === (int) $_SESSION['admin_user_id'] && ($action === 'delete' || $active === 0)) {
                throw new InvalidArgumentException('No puedes eliminar ni desactivar tu propia cuenta.');
            }
            if ((int) $account['activo'] === 1 && $active === 0) {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM admin_usuarios WHERE activo = 1')->fetchColumn();
                if ($count <= 1) throw new InvalidArgumentException('Debe quedar al menos una cuenta activa.');
            }
            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM admin_usuarios WHERE id = ?')->execute([$id]);
                adminAudit($pdo, 'cuenta_eliminada', 'cuenta', (string) $id, ['usuario' => $account['usuario'], 'activo' => (int) $account['activo']]);
                $message = 'Cuenta eliminada.';
            } else {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE admin_usuarios SET usuario = ?, password_hash = ?, activo = ? WHERE id = ?');
                    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $active, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE admin_usuarios SET usuario = ?, activo = ? WHERE id = ?');
                    $stmt->execute([$username, $active, $id]);
                }
                adminAudit($pdo, 'cuenta_actualizada', 'cuenta', (string) $id, [
                    'antes' => ['usuario' => $account['usuario'], 'activo' => (int) $account['activo']],
                    'despues' => ['usuario' => $username, 'activo' => $active],
                    'clave_cambiada' => $password !== '',
                ]);
                $message = 'Cuenta actualizada.';
            }
        }
        $pdo->commit();
        notice($message);
        go('/cuentas.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException) {
            error_log('RPV cuentas: ' . $e->getMessage());
            $error = ($e->errorInfo[1] ?? null) === 1062 ? 'Ese nombre de usuario ya existe.' : 'No se pudo guardar la cuenta.';
        } else {
            $error = $e->getMessage();
        }
    }
}
$identity = $pdo->prepare('SELECT usuario FROM admin_usuarios WHERE id = ?');
$identity->execute([(int) $_SESSION['admin_user_id']]);
$isMainAdmin = $identity->fetchColumn() === 'admin';
$accounts = $pdo->query('SELECT id, usuario, activo, creado_en FROM admin_usuarios ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
if (!$isMainAdmin) $accounts = array_values(array_filter($accounts, static fn($account) => $account['usuario'] !== 'admin'));
$flash = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title>Cuentas · RPV Construcciones</title><link rel="stylesheet" href="/assets/admin.css"><script src="/assets/cuentas.js?v=2" defer></script><link rel="stylesheet" href="/assets/cuentas.css?v=3"></head><body>
<header class="topbar"><a class="brand brand-logo" href="/precios.php"><img src="/logo.php" alt="Logo RPV Construcciones"></a><nav class="top-actions" aria-label="Administración"><a class="site-link" href="/precios.php">Productos</a><form method="post" action="/cuentas.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="logout" type="submit">Salir</button></form></nav></header>
<main class="accounts-shell"><h1>Cuentas de administración</h1><p>Las contraseñas se almacenan como hash. Deja la contraseña vacía al editar para conservar la actual.</p>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="account-grid"><section class="account-card"><h2>Crear cuenta</h2><form method="post" action="/cuentas.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="create"><label>Usuario<input name="usuario" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" autocomplete="off"></label><label>Contraseña<input type="password" name="password" required minlength="7" maxlength="128" autocomplete="new-password"></label><button class="primary" type="submit">Crear cuenta</button></form></section>
<?php foreach ($accounts as $account): ?><section class="account-card"><h2><?= h($account['usuario']) ?><?= (int)$account['id'] === (int)$_SESSION['admin_user_id'] ? ' · Tu cuenta' : '' ?></h2><small>Creada: <?= h($account['creado_en']) ?></small><form method="post" action="/cuentas.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><label>Usuario<input name="usuario" required minlength="3" maxlength="80" pattern="[A-Za-z0-9._-]+" value="<?= h($account['usuario']) ?>"></label><label>Nueva contraseña<input type="password" name="password" minlength="7" maxlength="128" autocomplete="new-password" placeholder="Dejar vacía para conservar"></label><label class="check"><input type="checkbox" name="activo" value="1" <?= (int)$account['activo'] ? 'checked' : '' ?> <?= (int)$account['id'] === (int)$_SESSION['admin_user_id'] ? 'disabled' : '' ?>> Cuenta activa</label><?php if ((int)$account['id'] === (int)$_SESSION['admin_user_id']): ?><input type="hidden" name="activo" value="1"><?php endif; ?><button class="primary" type="submit">Guardar cambios</button></form>
<?php if ((int)$account['id'] !== (int)$_SESSION['admin_user_id']): ?><form class="delete-form" method="post" action="/cuentas.php" data-confirm-delete data-account="<?= h($account['usuario']) ?>"><input type="hidden" name="confirm_delete" value="0"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><button class="delete" type="submit">Eliminar cuenta</button></form><?php endif; ?></section><?php endforeach; ?></div></main></body></html>