# Guía interactiva de caldera

La guía está en `DESARROLLO/Web/guia-caldera/` y abre localmente en `http://localhost:8000/guia-caldera/`. El proceso `/prod` copia automáticamente la carpeta a `PRODUCCION/Web/guia-caldera/`; su destino en cPanel es `public_html/guia-caldera/` y la URL prevista es `https://www.rpvconstrucciones.com/guia-caldera/`. No se ha publicado.

La carpeta incluye su propio manifiesto y Service Worker con alcance `/guia-caldera/`. El archivo `.htaccess` define los tipos MIME para Apache. Las 23 ilustraciones SVG están incorporadas en `index.html`; la ficha original permanece en `assets/guia-original.webp`. El progreso se guarda en el navegador mediante `localStorage` y no se sincroniza entre dispositivos.

El WhatsApp usa el número comercial `56964170206`, confirmado para esta guía. Requiere conexión. Antes de entregar la guía a clientes, el fabricante debe validar las instrucciones de operación y seguridad.

El ZIP traía un QR de muestra que apuntaba a `localhost`. No se copió a la carpeta web. Después de publicar y comprobar la URL HTTPS real, instala `qrcode` en un entorno Python de desarrollo si hace falta y ejecuta desde la raíz:

`python .\DESARROLLO\scripts\generar-qr-guia-caldera.py https://www.rpvconstrucciones.com/guia-caldera/`

El script genera `qr-cliente.svg`, activa la página `qr-clientes.html` y renueva la versión de caché. Si cambian otros recursos de la guía, actualiza también `CACHE` en `sw.js`. Verifica el código desde otro dispositivo antes de imprimirlo. Para otra URL pública, pasa la dirección real correspondiente.

Comprueba en navegador móvil y escritorio: carga inicial, 23 ilustraciones, navegación y checklist, persistencia tras recargar, instalación PWA y segunda visita sin conexión. Revisa también que `/calderas/` y la portada conserven sus funciones. El Service Worker no almacena WhatsApp ni páginas externas.