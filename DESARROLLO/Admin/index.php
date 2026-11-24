<?php
declare(strict_types=1);
require __DIR__ . '/acceso.php';

$categories = ['General', 'Tinajas', 'Calderas', 'Estufas a pellets', 'Instalación'];
$form = [
    'id' => '', 'nombre_publico' => '', 'ciudad' => '', 'empresa_pyme' => '', 'categoria' => 'General',
    'calificacion' => '', 'comentario' => '', 'consentimiento_publicacion' => false,
];
$errors = [];
$pendingProductReviews = 0;
$flash = $_SESSION['notice'] ?? null;
unset($_SESSION['notice']);

try {
    require dirname(__DIR__) . '/BaseDatos/conexion_rpv.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals((string) $_SESSION['csrf'], $token)) {
            http_response_code(403);
            exit('La sesión caducó. Actualiza la página e inténtalo nuevamente.');
        }

        $action = (string) ($_POST['action'] ?? '');
        $idRaw = (string) ($_POST['id'] ?? '');
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;

        if ($action === 'save') {
            $form = [
                'id' => $id > 0 ? (string) $id : '',
                'nombre_publico' => cleanText((string) ($_POST['nombre_publico'] ?? '')),
                'ciudad' => cleanText((string) ($_POST['ciudad'] ?? '')),
                'empresa_pyme' => cleanText((string) ($_POST['empresa_pyme'] ?? '')),
                'categoria' => (string) ($_POST['categoria'] ?? ''),
                'calificacion' => (string) ($_POST['calificacion'] ?? ''),
                'comentario' => cleanText((string) ($_POST['comentario'] ?? '')),
                'consentimiento_publicacion' => (string) ($_POST['consentimiento_publicacion'] ?? '') === '1',
            ];

            if (textLength($form['nombre_publico']) < 2 || textLength($form['nombre_publico']) > 80) {
                $errors[] = 'El nombre público debe tener entre 2 y 80 caracteres.';
            }
            if (textLength($form['ciudad']) > 80) {
                $errors[] = 'La ciudad debe tener como máximo 80 caracteres.';
            }
            if (textLength($form['empresa_pyme']) > 120) {
                $errors[] = 'La empresa o pyme debe tener como máximo 120 caracteres.';
            }
            if (!in_array($form['categoria'], $categories, true)) {
                $errors[] = 'Selecciona una categoría válida.';
            }
            $rating = null;
            if ($form['calificacion'] !== '') {
                $rating = filter_var($form['calificacion'], FILTER_VALIDATE_INT);
                if ($rating === false || $rating < 1 || $rating > 5) {
                    $errors[] = 'La calificación debe ser de 1 a 5 estrellas.';
                }
            }
            if (textLength($form['comentario']) < 15 || textLength($form['comentario']) > 700) {
                $errors[] = 'El comentario debe tener entre 15 y 700 caracteres.';
            }
            if (!$form['consentimiento_publicacion']) {
                $errors[] = 'Debes confirmar la autorización del cliente para registrar el comentario.';
            }
            if ((string) ($_POST['confirmed'] ?? '') !== '1') {
                $errors[] = 'Confirma el registro en la ventana de revisión antes de guardar.';
            }

            if (!$errors) {
                $consent = $form['consentimiento_publicacion'] ? 1 : 0;
                if ($id > 0) {
                    $lookup = $pdo->prepare('SELECT estado FROM testimonios WHERE id_testimonio = ?');
                    $lookup->execute([$id]);
                    $previous = $lookup->fetch(PDO::FETCH_ASSOC);
                    if (!$previous) {
                        $errors[] = 'El testimonio que intentas editar ya no existe.';
                    } else {
                        $status = $consent && $previous['estado'] === 'publicado' ? 'publicado' : 'borrador';
                        $stmt = $pdo->prepare('UPDATE testimonios SET nombre_publico = ?, ciudad = ?, empresa_pyme = ?, categoria = ?, calificacion = ?, comentario = ?, consentimiento_publicacion = ?, estado = ?, publicado_en = CASE WHEN ? = 1 THEN COALESCE(publicado_en, CURRENT_TIMESTAMP) ELSE NULL END WHERE id_testimonio = ?');
                        $stmt->execute([
                            $form['nombre_publico'], $form['ciudad'] ?: null, $form['empresa_pyme'] ?: null, $form['categoria'], $rating,
                            $form['comentario'], $consent, $status, $status === 'publicado' ? 1 : 0, $id,
                        ]);
                        notice('Testimonio actualizado.');
                        go('/#guardados');
                    }
                } else {
                    $stmt = $pdo->prepare('INSERT INTO testimonios (nombre_publico, ciudad, empresa_pyme, categoria, calificacion, comentario, consentimiento_publicacion) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $form['nombre_publico'], $form['ciudad'] ?: null, $form['empresa_pyme'] ?: null, $form['categoria'], $rating,
                        $form['comentario'], $consent,
                    ]);
                    notice('Testimonio guardado como borrador.');
                    go('/#guardados');
                }
            }
        } elseif (in_array($action, ['publish', 'hide'], true) && $id > 0) {
            if ($action === 'publish') {
                $stmt = $pdo->prepare("UPDATE testimonios SET estado = 'publicado', publicado_en = COALESCE(publicado_en, CURRENT_TIMESTAMP) WHERE id_testimonio = ? AND consentimiento_publicacion = 1");
                $stmt->execute([$id]);
                notice($stmt->rowCount() ? 'Testimonio listo para mostrarse en la web.' : 'Para publicar, confirma antes la autorización del cliente.', $stmt->rowCount() ? 'ok' : 'error');
            } else {
                $stmt = $pdo->prepare("UPDATE testimonios SET estado = 'borrador', publicado_en = NULL WHERE id_testimonio = ?");
                $stmt->execute([$id]);
                notice('Testimonio ocultado y conservado como borrador.');
            }
            go('/#guardados');
        } else {
            http_response_code(400);
            $errors[] = 'La acción solicitada no es válida.';
        }
    } elseif (isset($_GET['editar'])) {
        $idRaw = (string) $_GET['editar'];
        $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT id_testimonio, nombre_publico, ciudad, empresa_pyme, categoria, calificacion, comentario, consentimiento_publicacion FROM testimonios WHERE id_testimonio = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $form = [
                    'id' => (string) $row['id_testimonio'],
                    'nombre_publico' => $row['nombre_publico'],
                    'ciudad' => $row['ciudad'] ?? '',
                    'empresa_pyme' => $row['empresa_pyme'] ?? '',
                    'categoria' => $row['categoria'],
                    'calificacion' => $row['calificacion'] === null ? '' : (string) $row['calificacion'],
                    'comentario' => $row['comentario'],
                    'consentimiento_publicacion' => (bool) $row['consentimiento_publicacion'],
                ];
            } else {
                $errors[] = 'No se encontró el testimonio para editar.';
            }
        }
    }

    $testimonials = $pdo->query('SELECT id_testimonio, nombre_publico, correo_privado, ciudad, empresa_pyme, categoria, calificacion, comentario, consentimiento_publicacion, estado, creado_en FROM testimonios ORDER BY id_testimonio DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    $counts = ['borrador' => 0, 'publicado' => 0];
    foreach ($pdo->query('SELECT estado, COUNT(*) AS total FROM testimonios GROUP BY estado') as $row) {
        $counts[$row['estado']] = (int) $row['total'];
    }
    $pendingProductReviews = (int) $pdo->query("SELECT COUNT(*) FROM testimonios WHERE correo_privado IS NOT NULL AND estado = 'borrador'")->fetchColumn();
} catch (Throwable $e) {
    error_log('RPV testimonios local: ' . $e->getMessage());
    http_response_code(503);
    $errors[] = 'No se pudo abrir la base local. Inicia MariaDB 11.4 y ejecuta el inicializador.';
    $testimonials = [];
    $pendingProductReviews = 0;
    $counts = ['borrador' => 0, 'publicado' => 0];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Testimonios de clientes · RPV Construcciones</title>
  <link rel="stylesheet" href="/assets/admin.css">
  <script src="/assets/admin.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand brand-logo" href="/"><img src="/logo.php" alt="Logo RPV Construcciones"></a>
    <div class="top-actions"><a class="site-link" href="/precios.php">Administrar precios</a><a class="site-link" href="/cuentas.php">Cuentas</a><a class="site-link" href="http://localhost:8000/" target="_blank" rel="noopener"><span class="site-link-full">Ver sitio de desarrollo</span><span class="site-link-short">Ver sitio</span> <span aria-hidden="true">↗</span></a><form method="post" action="/"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="logout"><button class="logout" type="submit">Cerrar sesión</button></form></div>
  </header>

  <main class="shell">
    <section class="intro" aria-labelledby="page-title">
      <div>
        <span class="eyebrow">CONTENIDO · DESARROLLO</span>
        <h1 id="page-title">La voz de tus clientes,<br><em>bien presentada.</em></h1>
        <p>Registra comentarios reales, revísalos y decide cuáles quedan listos para mostrar en la web.</p>
      </div>
      <div class="metrics" aria-label="Resumen de testimonios">
        <div><strong><?= $counts['borrador'] ?></strong><span>Borradores</span></div>
        <div><strong><?= $counts['publicado'] ?></strong><span>Listos para web</span></div>
      </div>
    </section>

    <?php if ($flash): ?><div class="alert <?= $flash['kind'] === 'error' ? 'alert-error' : 'alert-success' ?>" role="status"><?= h($flash['message']) ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><strong>Revisa lo siguiente:</strong><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="workarea">
      <section class="panel form-panel" aria-labelledby="form-title">
        <div class="panel-heading"><span class="step">01</span><div><h2 id="form-title"><?= $form['id'] ? 'Editar testimonio' : 'Nuevo testimonio' ?></h2><p>Los campos marcados con * son obligatorios.</p></div></div>
        <form method="post" action="/#form-title" id="testimonial-form">
          <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= h($form['id']) ?>">
          <input type="hidden" name="confirmed" id="save-confirmed" value="0">

          <div class="field-grid">
            <label>Nombre para mostrar *<input name="nombre_publico" id="nombre_publico" required maxlength="80" minlength="2" autocomplete="off" value="<?= h($form['nombre_publico']) ?>" placeholder="Ej.: Camila S."></label>
            <label>Ciudad o comuna<input name="ciudad" id="ciudad" maxlength="80" autocomplete="off" value="<?= h($form['ciudad']) ?>" placeholder="Ej.: Pucón"></label>
          </div>
          <label>Empresa o pyme del cliente <span class="optional">(opcional)</span><input name="empresa_pyme" id="empresa_pyme" maxlength="120" autocomplete="organization" value="<?= h($form['empresa_pyme']) ?>" placeholder="Ej.: Cabañas del Lago"></label>
          <div class="field-grid">
            <label>Producto o experiencia<select name="categoria" id="categoria"><?php foreach ($categories as $category): ?><option value="<?= h($category) ?>" <?= $form['categoria'] === $category ? 'selected' : '' ?>><?= h($category) ?></option><?php endforeach; ?></select></label>
            <fieldset class="rating-field"><legend>Calificación del cliente <span class="optional">(opcional)</span></legend>
              <div class="rating-control" id="rating-control" aria-describedby="rating-hint">
                <?php for ($stars = 1; $stars <= 5; $stars++): ?><label class="rating-choice"><input type="radio" name="calificacion" value="<?= $stars ?>" <?= $form['calificacion'] === (string) $stars ? 'checked' : '' ?>><span class="rating-star" aria-hidden="true">★</span><span class="sr-only"><?= $stars ?> <?= $stars === 1 ? 'estrella' : 'estrellas' ?></span></label><?php endfor; ?>
              </div>
              <div class="rating-meta"><span id="rating-hint">Sin calificación</span><button type="button" class="rating-clear" id="rating-clear">Quitar</button></div>
            </fieldset>
          </div>
          <label>Comentario del cliente *<textarea name="comentario" id="comentario" required minlength="15" maxlength="700" rows="6" placeholder="Escribe el comentario tal como quieres presentarlo, respetando lo que dijo el cliente."><?= h($form['comentario']) ?></textarea></label>
          <div class="field-foot"><span>Entre 15 y 700 caracteres</span><output id="character-count" for="comentario">0 / 700</output></div>

          <label class="consent"><input type="checkbox" name="consentimiento_publicacion" value="1" required <?= $form['consentimiento_publicacion'] ? 'checked' : '' ?>><span>Confirmo que el cliente autorizó usar este comentario y el nombre mostrado en la web. Si indiqué una empresa o pyme, también autorizó mostrarla.</span></label>
          <p class="consent-help">Esta confirmación es obligatoria para registrar el comentario.</p>

          <div class="form-actions"><button class="primary" type="submit"><?= $form['id'] ? 'Guardar cambios' : 'Guardar borrador' ?> <span aria-hidden="true">→</span></button><?php if ($form['id']): ?><a class="text-link" href="/">Cancelar edición</a><?php endif; ?></div>
        </form>
      </section>

      <aside class="preview-column" aria-labelledby="preview-title">
        <div class="preview-head"><span class="step">02</span><div><h2 id="preview-title">Vista previa</h2><p>Así podría verse en tu sitio.</p></div></div>
        <div class="testimonial-preview">
          <div class="quote-mark" aria-hidden="true">“</div>
          <p class="preview-stars" id="preview-stars" aria-label="Sin calificación"></p>
          <blockquote id="preview-comment">El comentario de tu cliente aparecerá aquí mientras lo escribes.</blockquote>
          <div class="preview-person"><span class="avatar" id="preview-avatar" aria-hidden="true">C</span><div><strong id="preview-name">Nombre del cliente</strong><small id="preview-detail">Producto · Ciudad · Empresa</small></div></div>
        </div>
        <p class="preview-note"><span aria-hidden="true">✦</span> Solo los comentarios autorizados y marcados como listos podrán mostrarse públicamente.</p>
      </aside>
    </div>

    <section class="saved" id="guardados" aria-labelledby="saved-title">
      <div class="saved-heading"><div><span class="eyebrow">TU BIBLIOTECA</span><h2 id="saved-title">Comentarios guardados</h2><p><?= $pendingProductReviews ?> opinión<?= $pendingProductReviews === 1 ? '' : 'es' ?> de productos pendiente<?= $pendingProductReviews === 1 ? '' : 's' ?> de revisión. Usa «Listo para web» para aprobar; los correos privados no se publican.</p></div><a class="top-link" href="#form-title">Agregar comentario ↑</a></div>
      <?php if (!$testimonials): ?>
        <div class="empty"><span aria-hidden="true">✦</span><h3>Aún no hay testimonios</h3><p>El primero que guardes aparecerá aquí como borrador.</p></div>
      <?php else: ?>
        <div class="saved-grid">
          <?php foreach ($testimonials as $item): ?>
            <article class="saved-card">
              <div class="saved-card-top"><span class="badge <?= $item['estado'] === 'publicado' ? 'badge-published' : 'badge-draft' ?>"><?= $item['estado'] === 'publicado' ? 'Listo para web' : (!empty($item['correo_privado']) ? 'Pendiente de revisión' : 'Borrador') ?></span><time datetime="<?= h(substr($item['creado_en'], 0, 10)) ?>"><?= h(date('d/m/Y', strtotime($item['creado_en']))) ?></time></div>
              <?php if ($item['calificacion'] !== null): ?><div class="saved-stars" aria-label="<?= (int) $item['calificacion'] ?> de 5 estrellas"><?= str_repeat('★', (int) $item['calificacion']) ?></div><?php endif; ?>
              <p class="saved-quote">“<?= h($item['comentario']) ?>”</p>
              <div class="saved-author"><strong><?= h($item['nombre_publico']) ?></strong><span><?= h(implode(' · ', array_filter([$item['categoria'], $item['ciudad'], $item['empresa_pyme']], static fn($value) => $value !== null && $value !== ''))) ?></span></div>
              <div class="saved-actions"><a href="/?editar=<?= (int) $item['id_testimonio'] ?>#form-title">Editar</a>
                <form method="post" action="/#guardados"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int) $item['id_testimonio'] ?>"><input type="hidden" name="action" value="<?= $item['estado'] === 'publicado' ? 'hide' : 'publish' ?>"><button type="submit" <?= $item['estado'] === 'borrador' && !$item['consentimiento_publicacion'] ? 'disabled title="Confirma la autorización antes de publicar"' : '' ?>><?= $item['estado'] === 'publicado' ? 'Ocultar' : 'Marcar listo' ?></button></form>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
  <dialog class="confirm-dialog" id="save-dialog" aria-labelledby="save-dialog-title" aria-describedby="save-dialog-description">
    <div class="dialog-icon" aria-hidden="true">✦</div>
    <h2 id="save-dialog-title">Confirma el registro</h2>
    <p id="save-dialog-description">El comentario y el nombre mostrado se guardarán con la autorización del cliente. Una vez registrado, <strong>solo el administrador del sitio podrá editarlo si es necesario.</strong></p>
    <div class="dialog-actions"><button type="button" class="dialog-cancel" id="cancel-save">Volver a revisar</button><button type="button" class="primary" id="confirm-save">Confirmar y guardar <span aria-hidden="true">→</span></button></div>
  </dialog>
  <footer class="footer"><span>RPV Construcciones · Gestión local de testimonios</span><span>Los datos se guardan en MariaDB 11.4 · puerto 4444</span></footer>
</body>
</html>
