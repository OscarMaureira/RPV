# GUÍA TÉCNICA DE IMPLEMENTACIÓN EN CPANEL
## Proyecto: Sitio Web Dual RPV Construcciones & Soluciones Térmicas
**Entorno:** Servidor Web con cPanel  
**Dominio Principal:** `rpvconstrucciones.com`

---

### 1. ESTRUCTURA DE ARCHIVOS EN PUBLIC_HTML

Para gestionar ambos rubros de forma limpia y sin que interfieran entre sí, la estructura dentro del Administrador de Archivos de cPanel debe organizarse así:

```text
public_html/
│
├── .htaccess                  # Reglas de redirección, seguridad y forzado HTTPS
├── index.html                 # Página Home Portal (Split-Screen / Acceso Dual)
├── robots.txt                 # Instrucciones para motores de búsqueda (SEO)
├── sitemap.xml                # Mapa del sitio dual
│
├── assets/                    # Recursos visuales globales
│   ├── css/
│   │   ├── style-global.css
│   │   ├── construccion.css
│   │   └── climatizacion.css
│   ├── js/
│   │   ├── main.js
│   │   └── chatbot-router.js  # Script del agente clasificador de WhatsApp/Chat
│   └── img/
│       ├── logo-rpv.png
│       ├── hero-construccion.jpg
│       ├── hero-calderas.jpg
│       └── proyectos/
│
├── construccion/              # Carpeta Rubro B2B (Ingeniería y Obras Civiles)
│   ├── index.html             # Landing institucional B2B
│   ├── retail.html            # Casos Banco BCI, Banchile, Salcobrand, París
│   ├── mineria.html           # Contratos Teck Quebrada Blanca, Collahuasi, Albemarle
│   ├── habitacional.html      # Casas Lo Curro (260 y 340 m²)
│   └── contacto-obras.html    # Formulario técnico con adjuntos (planos/cubicaciones)
│
└── climatizacion/             # Carpeta Rubro B2C (Equipamiento Térmico y Relax)
    ├── index.html             # Catálogo principal de productos
    ├── calderas-pellets.html  # Ficha técnica caldera exterior ($730.000 CLP)
    ├── estufas-pellets.html   # Ficha técnica estufa económica ($290.000 CLP)
    └── tinajas-fibra.html     # Ficha tinajas de fibra y acople con caldera
```

---

### 2. CONFIGURACIÓN DE SEGURIDAD Y SSL EN CPANEL

1. **Activar Certificado SSL Gratuito:**
   - En el menú principal de cPanel, busca **"SSL/TLS Status"**.
   - Selecciona el dominio `rpvconstrucciones.com` y los subdominios correspondientes.
   - Haz clic en **"Run AutoSSL"**. En pocos minutos se instalará el certificado de Let's Encrypt o cPanel.
2. **Forzar Redirección HTTPS y Limpieza de URLs (`.htaccess`):**
   - Entra al **Administrador de Archivos** > entra a `public_html`.
   - Si no ves el archivo `.htaccess`, ve al engrane superior derecho de **Configuración** y marca *"Mostrar archivos ocultos (dotfiles)"*.
   - Edita el archivo `.htaccess` e inserta el siguiente bloque:

```apache
# Forzar HTTPS en todo el sitio
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Evitar navegación directa por directorios
Options -Indexes

# Compresión GZIP para acelerar carga
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>
```

---

### 3. CONFIGURACIÓN DE CORREOS Y FORMULARIOS

Para evitar que los formularios caigan en la carpeta de Spam de los clientes:
1. **Cuentas de Correo Institucional:**
   - Ir a **"Cuentas de Correo Electrónico"** en cPanel.
   - Crear:
     - `rpinochetv@rpvconstrucciones.com` (Bandeja principal de Rodrigo Pinochet).
     - `ventas@rpvconstrucciones.com` o `contacto@rpvconstrucciones.com` (para formularios automáticos).
2. **Verificación de Entregabilidad (SPF y DKIM):**
   - En cPanel, abre **"Email Deliverability"**.
   - Verifica que el estado de `rpvconstrucciones.com` marque **"Valid"**. Si dice "Problems exist", pulsa "Repair" o "Install Local SPF/DKIM" para que cPanel cree los registros DNS automáticamente.

---

### 4. SCRIPT ENRUTADOR DEL AGENTE PARA CPANEL (HTML/JS)

Inserta este bloque antes de cerrar la etiqueta `</body>` en tu plantilla web general para que el botón flotante reconozca en qué sección está el cliente:

```html
<!-- Botón Flotante Inteligente RPV -->
<div id="rpv-chat-widget" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;">
  <a id="whatsapp-link" href="#" target="_blank" style="display: flex; align-items: center; background-color: #25D366; color: white; padding: 12px 18px; border-radius: 50px; text-decoration: none; font-family: Arial, sans-serif; font-size: 14px; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
    <span id="chat-text">Consultar por WhatsApp</span>
  </a>
</div>

<script>
  (function() {
    const phone = "56964170206";
    const currentPath = window.location.pathname;
    let message = "";

    if (currentPath.includes("climatizacion") || currentPath.includes("calderas") || currentPath.includes("tinajas") || currentPath.includes("estufas")) {
      message = encodeURIComponent("¡Hola Rodrigo! Estoy visitando la sección de Climatización y me gustaría cotizar una caldera a pellets / tinaja de fibra.");
    } else {
      message = encodeURIComponent("¡Hola Rodrigo! Visito la web de RPV Construcciones y requiero información técnica sobre un proyecto de construcción / obras civiles.");
    }

    document.getElementById("whatsapp-link").href = `https://wa.me/${phone}?text=${message}`;
  })();
</script>
```

---

### 5. CHECKLIST RÁPIDO PARA SUBIR EL SITIO A CPANEL

1. **Subida:** Comprime los archivos en un `.zip` y usa la opción **"Cargar"** (Upload) en el Administrador de Archivos de cPanel dentro de `public_html`.
2. **Extracción:** Clic derecho sobre el `.zip` > **"Extract"**.
3. **Comprobación:** Entrar a `https://www.rpvconstrucciones.com/construccion/` y `https://www.rpvconstrucciones.com/climatizacion/` verificando enlaces y navegación móvil.

## Catálogo comercial administrado localmente

Los precios y condiciones se administran en `http://localhost:8002/precios.php`, reutilizando el acceso privado local. Cada guardado sincroniza la fuente `DESARROLLO/catalogo/productos.json` con los precios y condiciones de las fichas HTML, `DESARROLLO/Web/assets/data/catalogo-rpv.json` y `DESARROLLO/Web/assets/data/conocimiento-rpv.md`. Ambos asistentes usan el mismo catálogo y leen el conocimiento incluido en esa misma revisión JSON.

Antes de preparar la versión, `prod.ps1` y `preparar-produccion.ps1` ejecutan `sincronizar-catalogo.php --check`; se detienen si hay diferencias. Este control no cambia los archivos. La regeneración se hace desde el panel o mediante `C:\xampp\php\php.exe DESARROLLO/scripts/sincronizar-catalogo.php`.

El paquete web conserva las páginas, `assets/js/catalogo-rpv.js`, `assets/css/catalogo-rpv.css` y `assets/data` (incluido su `.htaccess` para evitar caché de los datos comerciales). No requiere nuevas tablas, configuración privada ni módulos PHP de catálogo en cPanel. `DESARROLLO/Admin`, `DESARROLLO/catalogo`, los historiales y las pruebas permanecen fuera de `public_html` y no se incorporan al paquete.

Actualizar el panel modifica solo desarrollo. Para llevar esos valores al sitio público hay que ejecutar explícitamente `/prod` y publicar después el paquete correspondiente. La vigencia de precios se evalúa en la visualización y las respuestas del catálogo; los precios vencidos pasan a consulta. Los archivos HTML y Markdown son instantáneas de la última sincronización, por lo que el control previo a publicar exige regenerarlos cuando venza un valor.
