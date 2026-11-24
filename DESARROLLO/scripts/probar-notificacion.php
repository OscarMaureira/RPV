<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/modulos/notificaciones/Notificador.php';
require_once dirname(__DIR__) . '/modulos/notificaciones/Plantillas/LayoutCorreo.php';

$texto = "Esta es una prueba técnica del sistema de notificaciones por correo de RPV.\n"
    . "No se creó ni modificó ningún comentario de cliente.";
$html = \RPV\Modulos\Notificaciones\Plantillas\LayoutCorreo::html(
    'Prueba de conexión y envío SMTP.',
    '<h1 style="margin:0 0 14px;color:#213c2c;font-size:24px">Prueba de notificaciones</h1>'
        . '<p style="margin:0">La conexión SMTP de RPV aceptó este correo de prueba. No se creó ni modificó ningún comentario de cliente.</p>'
);
$textoConMarca = \RPV\Modulos\Notificaciones\Plantillas\LayoutCorreo::textoPlano($texto);

\RPV\Modulos\Notificaciones\Notificador::enviar(
    'contacto@rpvconstrucciones.com',
    'Prueba de notificaciones | RPV Construcciones',
    $textoConMarca,
    $html,
    'prueba_smtp'
);

fwrite(STDOUT, "El servidor SMTP aceptó el correo de prueba para contacto@rpvconstrucciones.com.\n");
