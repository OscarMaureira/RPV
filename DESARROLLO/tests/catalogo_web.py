"""Prueba integral en una copia temporal, sin credenciales ni precios reales del panel."""
from pathlib import Path
import contextlib, http.cookiejar, json, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
from playwright.sync_api import sync_playwright
DEV = Path(__file__).resolve().parents[1]
PHP = Path('C:/xampp/php/php.exe')
CHECKS = 0

def expect(value, message):
    global CHECKS
    if not value: raise AssertionError(message)
    CHECKS += 1

def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0)); return s.getsockname()[1]

with tempfile.TemporaryDirectory(prefix='rpv-catalogo-web-') as temporary:
    temp = Path(temporary); dev = temp / 'DESARROLLO'
    # Copias acotadas: nunca copiar BaseDatos ni secretos reales.
    for directory in ['Admin', 'catalogo', 'modulos/catalogo', 'Web']:
        def excluded(folder, names): return [n for n in names if n in ['BKP','docs','historial','.catalogo.lock']]
        shutil.copytree(DEV / directory, dev / directory, ignore=excluded)
    (dev / 'BaseDatos').mkdir()
    password = 'Fixture-catalogo-2026-privado'
    digest = subprocess.check_output([str(PHP), '-r', "echo password_hash($argv[1], PASSWORD_DEFAULT);", password], text=True)
    (dev / 'BaseDatos/admin_testimonios.local.php').write_text("<?php return " + repr(digest) + ";", encoding='utf-8')
    admin_port, web_port = free_port(), free_port()
    guard = dev / 'Admin/acceso.php'
    # El único ajuste de entorno de la copia es el puerto del guard local.
    guard.write_text(guard.read_text(encoding='utf-8').replace(':8002', ':' + str(admin_port)), encoding='utf-8')
    log = (temp / 'php.log').open('w', encoding='utf-8')
    servers = [subprocess.Popen([str(PHP), '-S', f'127.0.0.1:{admin_port}', '-t', str(dev / 'Admin')], stdout=log, stderr=log), subprocess.Popen([str(PHP), '-S', f'127.0.0.1:{web_port}', '-t', str(dev / 'Web')], stdout=log, stderr=log)]
    try:
        admin_url = f'http://127.0.0.1:{admin_port}/precios.php'; web_url = f'http://127.0.0.1:{web_port}'
        jar = http.cookiejar.CookieJar(); http = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        def request(data=None):
            encoded = urllib.parse.urlencode(data).encode() if data is not None else None
            try:
                with http.open(urllib.request.Request(admin_url, encoded), timeout=5) as r: return r.status, r.read().decode('utf-8')
            except urllib.error.HTTPError as r: return r.code, r.read().decode('utf-8')
        for attempt in range(30):
            try: status, html = request(); break
            except OSError: time.sleep(.1)
        expect('name="password"' in html and 'price-form' not in html, 'El panel debe exigir ingreso.')
        expect(request({'action':'save_product'})[0] == 403, 'Escritura sin ingreso aceptada.')
        status, html = request(); csrf = re.search(r'name="csrf" value="([^"]+)"', html)[1]
        status, html = request({'action':'login','csrf':csrf,'password':password})
        expect(status == 200 and 'price-form' in html, 'Falló la sesión de prueba.')
        csrf = re.search(r'name="csrf" value="([^"]+)"', html)[1]
        before = json.loads((dev / 'catalogo/productos.json').read_text(encoding='utf-8'))
        fields = dict(before['productos'][0]); fields.update(action='save_product',product_id='caldera-tipo-a',revision=before['revision'],csrf=csrf,aprobar='1',precio_clp='1234567',incluye='Kit aprobado para prueba integral.')
        bad = dict(fields, csrf='incorrecto')
        expect(request(bad)[0] == 403, 'CSRF inválido aceptado.')
        expect(json.loads((dev / 'catalogo/productos.json').read_text(encoding='utf-8'))['revision'] == before['revision'], 'Se modificó con CSRF inválido.')
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(channel='msedge', headless=True)
            context = browser.new_context(viewport={'width':1440,'height':1000}, reduced_motion='reduce')
            context.route('**/*', lambda route: route.continue_() if urllib.parse.urlparse(route.request.url).hostname in ['127.0.0.1','localhost'] or route.request.url.startswith('data:') else route.abort())
            context.add_cookies([{'name':cookie.name,'value':cookie.value,'domain':'127.0.0.1','path':cookie.path} for cookie in jar])
            admin_page = context.new_page(); admin_page.goto(admin_url, wait_until='networkidle')
            expect(admin_page.locator('.price-form').count() == 6, 'Faltan formularios de productos.')
            for width in [1440,768,390,320]:
                admin_page.set_viewport_size({'width':width,'height':1000})
                expect(not admin_page.evaluate('document.documentElement.scrollWidth > innerWidth'), f'El panel desborda a {width}.')
                if width in [1440,390]: admin_page.screenshot(path=str(Path(tempfile.gettempdir()) / f'rpv-panel-precios-{width}.png'))
            tabs = []
            for relative in ['/', '/calderas/']:
                page = context.new_page(); page.goto(web_url + relative, wait_until='networkidle'); page.locator('#launcher').click()
                tabs.append(page)
                for letter, amount in [('A','1.150.000'),('B','830.000'),('C','730.000')]:
                    # El catálogo real puede cambiar: comparar con el producto de la copia.
                    product = next(p for p in before['productos'] if p['id'] == 'caldera-tipo-' + letter.lower())
                    amount = f"{product['precio_clp']:,}".replace(',','.') if product['precio_clp'] is not None else 'Precio a consultar'
                    page.locator('#question').fill('Cuanto cuesta la caldera tipo ' + letter); page.locator('#form button').click()
                    page.wait_for_function("expected => Array.from(document.querySelectorAll('#messages .msg:not(.user)')).at(-1).textContent.includes(expected)", arg=amount)
                    expect(amount in page.locator('#messages .msg:not(.user)').last.inner_text(), 'Precio de modelo incorrecto.')
            status, html = request(fields)
            expect(status == 200 and 'Producto actualizado.' in html, 'Falló el guardado autenticado.')
            expect('$1.234.567' in (dev / 'Web/assets/data/conocimiento-rpv.md').read_text(encoding='utf-8'), 'No se actualizó el archivo de conocimiento.')
            expect('Kit aprobado para prueba integral.' in (dev / 'Web/calderas/index.html').read_text(encoding='utf-8'), 'No se actualizaron condiciones en HTML.')
            for page in tabs:
                page.locator('#question').fill('Precio caldera tipo A'); page.locator('#form button').click()
                page.wait_for_function("() => Array.from(document.querySelectorAll('#messages .msg:not(.user)')).at(-1).textContent.includes('$1.234.567')")
                expect('Kit aprobado para prueba integral.' in page.locator('#messages .msg:not(.user)').last.inner_text(), 'El asistente abierto conserva condiciones anteriores.')
                page.locator('#question').fill('Precio modelo D'); page.locator('#form button').click()
                page.wait_for_function("() => Array.from(document.querySelectorAll('#messages .msg:not(.user)')).at(-1).textContent.includes('correspondencia aprobada')")
                expect('$' not in page.locator('#messages .msg:not(.user)').last.inner_text(), 'Se inventó un precio para un modelo desconocido.')
            status, html = request(fields)
            expect('otra ventana' in html, 'No se rechazó una revisión anterior.')
            browser.close()
        print(f'OK: {CHECKS} controles integrales de acceso, CSRF, guardado, ambos asistentes abiertos y diseño móvil.')
    finally:
        for server in servers:
            server.terminate()
            try: server.wait(timeout=5)
            except subprocess.TimeoutExpired: server.kill(); server.wait()
        log.close()
