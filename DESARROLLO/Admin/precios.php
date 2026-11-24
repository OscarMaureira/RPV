<?php
declare(strict_types=1);
$adminHome = '/precios.php';
require __DIR__ . '/acceso.php';
require dirname(__DIR__) . '/modulos/catalogo/Catalogo.php';
$error = '';
$ready = false;
try {
    $catalogo = new \RPV\Catalogo\Catalogo();
    $data = $catalogo->leer();
    $ready = true;
} catch (Throwable $e) {
    error_log('RPV catálogo: ' . $e->getMessage());
    $data = json_decode((string) file_get_contents(dirname(__DIR__) . '/catalogo/productos.json'), true, 512, JSON_THROW_ON_ERROR);
}
$draftId = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('La sesión caducó. Recarga la página.');
    }
    try {
        if (!$ready) throw new RuntimeException('El catálogo no está disponible para edición.');
        if (($_POST['action'] ?? '') !== 'save_price') throw new InvalidArgumentException('Acción no válida.');
        $draftId = (string) ($_POST['product_id'] ?? '');
        $product = array_values(array_filter($data['productos'], static fn($p) => $p['id'] === $draftId))[0] ?? null;
        if (!$product) throw new InvalidArgumentException('El producto no existe.');
        $input = $product;
        $input['precio_clp'] = (string) ($_POST['precio_clp'] ?? '');
        $input['modo_precio'] = (string) ($_POST['modo_precio'] ?? '');
        $input['link_pago'] = (string) ($_POST['link_pago'] ?? '');
        $input['aprobar'] = '1';
        $catalogo->guardar($draftId, $input, (string) ($_POST['revision'] ?? ''), static function (PDO $pdo, array $before, array $after) use ($draftId): void {
            adminAudit($pdo, 'producto_actualizado', 'producto', $draftId, [
                'antes' => ['precio_clp' => $before['precio_clp'], 'estado' => $before['modo_precio'], 'link_pago' => $before['link_pago']],
                'despues' => ['precio_clp' => $after['precio_clp'], 'estado' => $after['modo_precio'], 'link_pago' => $after['link_pago']],
            ]);
        });
        notice('Precio actualizado en desarrollo.');
        go('/precios.php#' . rawurlencode($draftId));
    } catch (Throwable $e) {
        error_log('RPV precio: ' . $e->getMessage());
        $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo guardar el precio.';
    }
}
$flash = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);
$disabled = $ready ? '' : 'disabled';
?>
<!doctype html><html lang="es-CL"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><title>Precios de productos · RPV Construcciones</title><link rel="stylesheet" href="/assets/admin.css"><link rel="stylesheet" href="/assets/precios.css?v=4"><script src="/assets/precios.js?v=3" defer></script></head>
<body><a class="skip" href="#contenido">Ir a los precios</a>
<header class="topbar"><a class="brand brand-logo" href="/precios.php"><img src="/logo.php" alt="Logo RPV Construcciones"></a><nav class="top-actions" aria-label="Administración"><a class="site-link" href="/cuentas.php">Cuentas</a><form method="post" action="/precios.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="logout" type="submit">Salir</button></form></nav></header>
<main class="prices-shell" id="contenido"><span class="eyebrow">CATÁLOGO · DESARROLLO</span><h1>Precios de productos</h1><p class="intro">Edita el precio, su estado y el link de pago. Guarda cada producto por separado.</p>
<?php if (!$ready): ?><div class="alert alert-error" role="alert">El catálogo todavía no está disponible para edición.</div><?php endif; ?>
<?php if ($flash): ?><div class="alert alert-success" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
<div class="prices-table-wrap"><table class="prices-table"><caption class="sr-only">Precios y estados de productos RPV</caption><thead><tr><th scope="col">Producto</th><th scope="col">Precio CLP</th><th scope="col">Estado</th><th scope="col">Link de pago</th><th scope="col">Acción</th></tr></thead><tbody>
<?php foreach ($data['productos'] as $stored): $p = $stored; if ($error && $draftId === $p['id']) foreach (['precio_clp','modo_precio','link_pago'] as $field) if (isset($_POST[$field]) && is_string($_POST[$field])) $p[$field] = $_POST[$field]; $formId='price-'.$p['id']; ?>
<tr id="<?= h($p['id']) ?>"><th scope="row"><?= h($p['nombre']) ?></th>
<td data-label="Precio CLP"><input form="<?= h($formId) ?>" class="price-input" type="text" name="precio_clp" inputmode="numeric" pattern="[0-9.]+" required value="<?= h(is_numeric($p['precio_clp']) ? number_format((float)$p['precio_clp'], 0, ',', '.') : (string)($p['precio_clp'] ?? '')) ?>" aria-label="Precio en pesos de <?= h($p['nombre']) ?>" <?= $disabled ?>></td>
<td data-label="Estado"><select form="<?= h($formId) ?>" name="modo_precio" aria-label="Estado del precio de <?= h($p['nombre']) ?>" <?= $disabled ?>><option value="referencial" <?= $p['modo_precio']==='referencial'?'selected':'' ?>>Referencial</option><option value="vigente" <?= $p['modo_precio']==='vigente'?'selected':'' ?>>Vigente</option><option value="consultar" <?= $p['modo_precio']==='consultar'?'selected':'' ?>>A consultar</option></select></td>
<td data-label="Link de pago"><input form="<?= h($formId) ?>" type="url" name="link_pago" maxlength="2048" pattern="https://.+" placeholder="https://..." value="<?= h((string)($p['link_pago'] ?? '')) ?>" aria-label="Link de pago de <?= h($p['nombre']) ?>" <?= $disabled ?>></td>
<td class="row-actions"><form id="<?= h($formId) ?>" class="price-form" method="post" action="/precios.php#<?= h($p['id']) ?>"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="revision" value="<?= h($data['revision']) ?>"><input type="hidden" name="product_id" value="<?= h($p['id']) ?>"><input type="hidden" name="action" value="save_price"><button class="primary" type="submit" <?= $disabled ?>>Guardar</button></form></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="table-help">Precio obligatorio entre $1 y $2.000.000 CLP. El link de pago es opcional y debe comenzar con https://.</p>
</main></body></html>