#!/usr/bin/env python3
"""Genera el QR después de comprobar la URL pública HTTPS de la guía."""
import hashlib
import html
import re
import sys
from pathlib import Path
from urllib.parse import urlsplit


def main() -> None:
    if len(sys.argv) != 2:
        raise SystemExit('Uso: python generar-qr-guia-caldera.py https://DOMINIO/guia-caldera/')
    url = sys.argv[1]
    parts = urlsplit(url)
    if (parts.scheme != 'https' or not parts.hostname or
            parts.hostname in {'localhost', '127.0.0.1'} or
            parts.path != '/guia-caldera/' or parts.query or parts.fragment):
        raise SystemExit('Se requiere la URL HTTPS pública exacta de /guia-caldera/.')

    import qrcode
    import qrcode.image.svg

    guide = Path(__file__).resolve().parent.parent / 'Web' / 'guia-caldera'
    target = guide / 'qr-cliente.svg'
    qr = qrcode.make(url, image_factory=qrcode.image.svg.SvgPathImage, box_size=10, border=4)
    qr.save(str(target))

    page_path = guide / 'qr-clientes.html'
    page = page_path.read_text(encoding='utf-8')
    page = re.sub(r'(?<=<!-- QR_CONTENT_START -->).*?(?=<!-- QR_CONTENT_END -->)',
                  '\n<img src="qr-cliente.svg" alt="Código QR de acceso a la guía">\n<p>Abre <a href="' +
                  html.escape(url, quote=True) + '">' + html.escape(url) +
                  '</a>. Comprueba el QR en otro dispositivo antes de imprimirlo.</p>\n',
                  page, flags=re.S)
    page = re.sub(r'(?<=<!-- QR_PRINT_START -->).*?(?=<!-- QR_PRINT_END -->)',
                  '\n<button type="button" onclick="print()">Imprimir</button>\n', page, flags=re.S)
    page_path.write_text(page, encoding='utf-8')

    sw_path = guide / 'sw.js'
    sw = sw_path.read_text(encoding='utf-8')
    digest = hashlib.sha256(target.read_bytes()).hexdigest()[:10]
    sw = re.sub(r"const CACHE = 'rpv-guia-caldera-[^']+';",
                "const CACHE = 'rpv-guia-caldera-qr-" + digest + "';", sw)
    if "./qr-cliente.svg" not in sw:
        sw = sw.replace("  './qr-clientes.html'", "  './qr-clientes.html',\n  './qr-cliente.svg'")
    sw_path.write_text(sw, encoding='utf-8')
    print('QR generado en', target, 'para', url)


if __name__ == '__main__':
    main()