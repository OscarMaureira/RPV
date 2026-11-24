<?php
declare(strict_types=1);

namespace RPV\Modulos\Notificaciones\Plantillas;

final class LayoutCorreo
{
    private const SITE_URL = 'https://www.rpvconstrucciones.com';

    public static function html(string $preheader, string $contenido): string
    {
        $siteUrl = self::SITE_URL;
        $logoUrl = self::SITE_URL . '/assets/images/logos/logo-rpv-construcciones.png';
        $cabecera = require __DIR__ . '/componentes/cabecera.php';
        $pie = require __DIR__ . '/componentes/pie.php';
        $textoPreheader = htmlspecialchars($preheader, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!doctype html><html lang="es-CL"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="x-apple-disable-message-reformatting"><title>RPV Construcciones</title></head>'
            . '<body style="margin:0;padding:0;background:#f4f1eb;color:#24382c;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">' . $textoPreheader . '</div>'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f1eb">'
            . '<tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;border:1px solid #e3e7df;border-radius:14px;overflow:hidden;background:#fffdf8">'
            . $cabecera
            . '<tr><td style="padding:30px 30px 34px;background:#fffdf8;font:15px/1.65 Arial,Helvetica,sans-serif;color:#34483a">'
            . $contenido
            . '</td></tr>'
            . $pie
            . '</table></td></tr></table></body></html>';
    }

    public static function textoPlano(string $contenido): string
    {
        return "RPV CONSTRUCCIONES\nhttps://www.rpvconstrucciones.com\n\n"
            . trim($contenido)
            . "\n\nRPV Construcciones · Bienestar y calidez para tus espacios.\n"
            . "https://www.rpvconstrucciones.com\n";
    }
}
