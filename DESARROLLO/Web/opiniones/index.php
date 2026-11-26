<?php
declare(strict_types=1);

// Acepta el sitio público y los servidores locales de desarrollo.
$hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\\d+$/', '', $hostHeader);
$remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$productionHosts = ['rpvconstrucciones.com', 'www.rpvconstrucciones.com'];
$isProduction = in_array($host, $productionHosts, true);
$isLocal = in_array($hostHeader, ['localhost:8000', '127.0.0.1:8000'], true)
    && in_array($remote, ['127.0.0.1', '::1'], true);
if (!$isProduction && !$isLocal) {
    http_response_code(404);
    exit('Página no disponible.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

ini_set('session.use_strict_mode', '1');
if ($isLocal) {
    $sessionPath = dirname(__DIR__, 2) . '/.sessions';
    if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) throw new RuntimeException('No se pudo iniciar la sesion local.');
    session_save_path($sessionPath);
}
session_name($isProduction ? 'RPV_OPINIONES' : 'RPV_OPINIONES_DEV');
session_set_cookie_params(['httponly' => true, 'secure' => $isProduction, 'samesite' => 'Lax', 'path' => '/']);
session_start();
if (empty($_SESSION['opiniones_csrf'])) $_SESSION['opiniones_csrf'] = bin2hex(random_bytes(32));

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html lang="es-CL">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#20382b">
  <meta name="robots" content="noindex, nofollow"><title>Opiniones de clientes · RPV Construcciones</title>
  <meta name="description" content="Comparte tu experiencia con productos RPV Construcciones.">
  <link rel="preload" href="/assets/images/backgrounds/fondo.webp" as="image" fetchpriority="high">
  <link rel="stylesheet" href="/opiniones/opiniones.css?v=20261126-2"><script src="/assets/js/hero-effects.js?v=20261112-1" defer></script><script src="/opiniones/opiniones.js?v=20261126-2" defer></script>
<script src="/assets/js/secciones-publicas.js?v=20261126-4" defer></script>
</head>
<body>
<?php require __DIR__ . '/componentes/header.php'; ?>
<main>
  <section class="intro" aria-labelledby="page-title">
    <canvas class="hero-effects" aria-hidden="true"></canvas>
    <p class="eyebrow">TU EXPERIENCIA NOS AYUDA</p>
    <h1>¿Cómo ha sido tu experiencia?</h1>
    <p>Cuéntanos qué te pareció tu producto RPV. Leemos cada opinión antes de mostrarla en el sitio.</p>
    <a class="intro-link" href="#formulario">Dejar una opinión <span aria-hidden="true">↓</span></a>
  </section>

  <div class="review-flow">
    <section class="form-card" id="formulario" aria-labelledby="form-title">
      <div class="section-heading"><span class="step">01</span><div><h2 id="form-title">Comparte tu opinión</h2><p>Los campos opcionales están identificados.</p></div></div>
      <form id="review-form" novalidate>
        <input type="hidden" name="csrf" value="<?= h($_SESSION['opiniones_csrf']) ?>">
        <div class="trap" aria-hidden="true"><input id="form_field_extra_7c39" name="form_field_extra_7c39" type="text" tabindex="-1" autocomplete="off"></div>
        <label for="name">Tu nombre</label><input id="name" name="name" type="text" minlength="2" maxlength="80" autocomplete="name" required>
        <label for="email">Correo electrónico <span class="private">Privado</span></label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required aria-describedby="email-help"><small id="email-help">Lo usamos solo para registro y contacto si hiciera falta. Nunca se publicará.</small>
        <label for="city">Ciudad <span class="optional">(opcional)</span></label><input id="city" name="city" type="text" maxlength="80" autocomplete="address-level2">
        <label for="product">Producto</label><select id="product" name="product" required><option value="">Elige un producto</option><option>Calderas</option><option>Tinajas</option><option>Estufas a pellets</option></select>
        <fieldset class="rating"><legend>Tu valoración</legend><div class="stars" role="radiogroup" aria-label="Valoración de 1 a 5 estrellas">
          <?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="rating" value="<?= $i ?>" required><span aria-hidden="true">★</span><span class="sr-only"><?= $i ?> <?= $i === 1 ? 'estrella' : 'estrellas' ?></span></label><?php endfor; ?>
        </div><small>Selecciona de 1 a 5 estrellas.</small></fieldset>
        <label for="comment">Tu comentario</label><textarea id="comment" name="comment" minlength="15" maxlength="1200" rows="5" required aria-describedby="comment-help"></textarea><small id="comment-help">Entre 15 y 1.200 caracteres.</small>
        <section class="business-promotion" aria-labelledby="business-promotion-title" aria-describedby="promotion-description promotion-cost promotion-description-app promotion-cost-app">
          <div class="business-promotion-heading">
            <span class="business-kicker">Beneficio para clientes RPV</span>
            <h3 id="business-promotion-title"><span class="desktop-only">¡Tu experiencia también puede impulsar tu negocio!</span><span class="app-only">¡Impulsa también tu negocio!</span></h3>
          </div>
          <div class="promotion-intro desktop-only">
            <strong>En RPV Construcciones queremos que nuestros clientes también crezcan.</strong>
            <p id="promotion-description">Si tienes una empresa o emprendimiento, puedes compartir su nombre y sitio web junto con tu opinión. Cuando publiquemos tu experiencia, otros visitantes podrán conocer tu negocio y visitar tu página web.</p>
            <p><strong>¡Comparte tu experiencia y dale mayor visibilidad a tu emprendimiento!</strong></p>
            <small id="promotion-cost">Participar es completamente opcional y no tiene costo. La publicación depende de la revisión administrativa.</small>
          </div>
          <div class="promotion-intro app-only">
            <p id="promotion-description-app"><strong>¿Tienes una empresa o emprendimiento?</strong> Comparte su nombre y sitio web para que podamos mostrarlos junto con tu opinión.</p>
            <small id="promotion-cost-app">Es opcional, sin costo y sujeto a revisión.</small>
          </div>
          <label for="company-name">Nombre de la empresa o emprendimiento <span class="optional">(opcional)</span></label>
          <input id="company-name" name="company_name" type="text" maxlength="120" autocomplete="off">
          <label for="company-url">Sitio web de la empresa <span class="optional">(opcional)</span></label>
          <input id="company-url" name="company_url" type="url" maxlength="2048" inputmode="url" autocomplete="off" placeholder="https://www.ejemplo.cl" aria-describedby="company-url-help">
          <small id="company-url-help">El nombre y el sitio web deben informarse juntos. Solo se aceptan enlaces HTTP o HTTPS.</small>
        </section>
        <label class="consent consent-primary"><input type="checkbox" name="consent" value="1" required><span>Autorizo a RPV Construcciones a publicar mi nombre y este comentario junto con la valoración, el producto y, si la informo, mi ciudad. Mi correo se mantendrá privado. Si informo el nombre o sitio web de mi empresa o emprendimiento, autorizo también a publicarlos junto con mi opinión.</span></label>
      </form>
    </section>

    <section class="preview-section" aria-labelledby="preview-title">
      <div class="section-heading"><span class="step">02</span><div><h2 id="preview-title">Así se verá tu comentario</h2><p>Vista previa de los datos que escribiste. El correo se mantiene privado.</p></div></div>
      <article class="preview-card" aria-live="polite" aria-atomic="true">
        <p class="preview-product" id="preview-product" hidden></p>
        <div class="preview-stars" id="preview-stars" aria-label="Valoración no seleccionada" hidden><span id="preview-stars-filled"></span><span class="unselected-stars" id="preview-stars-empty"></span></div>
        <blockquote id="preview-comment" hidden></blockquote>
        <p class="preview-name" id="preview-name" hidden></p>
        <p class="preview-meta" id="preview-meta" hidden></p>
        <div class="preview-business" id="preview-business" hidden><span>Conoce su negocio</span><strong id="preview-company-name"></strong><a id="preview-company-url" href="#" target="_blank" rel="noopener noreferrer nofollow ugc" hidden>Visitar sitio web</a></div>
      </article>
    </section>
  </div>

  <button class="submit submit-review" type="submit" form="review-form">Enviar comentario <span aria-hidden="true">→</span></button>
  <div id="form-message" class="form-message" role="status" aria-live="polite" tabindex="-1"></div>

  <dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-description">
    <img class="dialog-logo" src="/assets/images/logos/logo-rpv-construcciones.webp" alt="RPV Construcciones">
    <h2 id="confirm-title">¿Enviar este comentario?</h2>
    <p class="confirm-summary" id="confirm-summary"></p>
    <p id="confirm-description">Al confirmar, guardaremos tu opinión para revisión y es posible que la publiquemos en nuestro sitio web. Si deseas modificar algún dato, vuelve al formulario.</p>
    <div class="dialog-actions"><button class="dialog-edit" type="button" id="edit-review">Seguir editando</button><button class="submit dialog-confirm" type="button" id="confirm-review">Confirmar y enviar</button></div>
  </dialog>

</main>
<?php require __DIR__ . '/componentes/footer.php'; ?>
</body></html>
