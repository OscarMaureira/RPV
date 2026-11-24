<?php
declare(strict_types=1);

namespace RPV\Modulos\Notificaciones\Plantillas;

use InvalidArgumentException;

final class OpinionRegistrada
{
    /** @return array{asunto:string,texto:string,html:string} */
    public static function generar(string $nombre, string $producto, int $calificacion, string $comentario): array
    {
        if ($calificacion < 1 || $calificacion > 5) {
            throw new InvalidArgumentException('La valoración debe estar entre 1 y 5.');
        }

        $nombreHtml = htmlspecialchars($nombre, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $productoHtml = htmlspecialchars($producto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $comentarioHtml = nl2br(htmlspecialchars($comentario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        $contenidoHtml = '<h1 style="margin:0 0 14px;color:#213c2c;font-size:25px;line-height:1.2">¡Gracias por compartir tu opinión!</h1>'
            . '<p style="margin:0 0 14px">Hola ' . $nombreHtml . ',</p>'
            . '<p style="margin:0 0 20px">Recibimos tu comentario correctamente. Quedó guardado para revisión y es posible que lo publiquemos en nuestro sitio web. Tu correo se mantendrá privado.</p>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border:1px solid #e4e9e1;border-radius:10px;background:#f5f8f3">'
            . '<tr><td style="padding:14px 16px;color:#657468;font-size:12px;text-transform:uppercase;letter-spacing:.7px">Producto</td>'
            . '<td style="padding:14px 16px;text-align:right;color:#24382c;font-size:14px;font-weight:bold">' . $productoHtml . '</td></tr>'
            . '<tr><td style="padding:0 16px 14px;color:#657468;font-size:12px;text-transform:uppercase;letter-spacing:.7px">Tu valoración</td>'
            . '<td style="padding:0 16px 14px;text-align:right;color:#b87927;font-size:17px;font-weight:bold">' . str_repeat('★', $calificacion) . '<span style="color:#dcd8d0">' . str_repeat('★', 5 - $calificacion) . '</span> <span style="color:#526357;font-size:12px">' . $calificacion . '/5</span></td></tr>'
            . '</table>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 20px;border:1px solid #e4e9e1;border-radius:10px;background:#fffdf8">'
            . '<tr><td style="padding:13px 16px 5px;color:#657468;font-size:12px;text-transform:uppercase;letter-spacing:.7px;font-weight:bold">Tu comentario</td></tr>'
            . '<tr><td style="padding:4px 16px 16px;color:#34483a;font-size:14px;line-height:1.6">' . $comentarioHtml . '</td></tr>'
            . '</table>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0;border:1px solid #e0e8dc;border-left:4px solid #d8ad75;border-radius:10px;background:#f3f7f1">'
            . '<tr><td style="padding:17px 18px;text-align:center;color:#213c2c;font-size:18px;line-height:1.4;font-weight:bold">Gracias por confiar en RPV Construcciones.</td></tr>'
            . '</table>';

        $texto = "Hola {$nombre},\n\n"
            . "Recibimos tu comentario correctamente. Quedó guardado para revisión y es posible que lo publiquemos en nuestro sitio web. Tu correo se mantendrá privado.\n\n"
            . "Producto: {$producto}\nValoración: {$calificacion}/5 estrellas\n\n"
            . "Tu comentario:\n{$comentario}\n\n"
            . 'Gracias por confiar en RPV Construcciones.';

        return [
            'asunto' => 'Recibimos tu opinión | RPV Construcciones',
            'texto' => LayoutCorreo::textoPlano($texto),
            'html' => LayoutCorreo::html('Recibimos correctamente tu comentario y quedó guardado para revisión.', $contenidoHtml),
        ];
    }
}
