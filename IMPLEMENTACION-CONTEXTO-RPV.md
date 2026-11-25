# Implementación del Gestor de Contexto RPV

Fecha: 25 de noviembre de 2026.

## Diagnóstico inicial

- `DESARROLLO/Web` es la fuente de trabajo y `PRODUCCION/Web` es el artefacto publicable. El flujo documentado del repositorio es Desarrollo → validación → Producción → cPanel.
- La portada utilizaba `assets/js/asistente-rpv.js`. La página anterior de calderas había dejado de cargar su controlador durante la renovación del catálogo; se reincorporó el asistente sin reemplazar el diseño editorial.
- Los dos controladores contenían implementaciones duplicadas de saludos, catálogo, selección de tinajas y repetición. La portada además mantenía personas, litros y m² en variables sueltas; el controlador de calderas no tenía esos flujos.
- Ambos asistentes consultan `assets/data/conocimiento-rpv.md`, generado desde el catálogo administrable. Los precios y condiciones no deben mantenerse manualmente en el nuevo gestor.
- El envío a WhatsApp, la solicitud voluntaria de GPS/comuna y el POST a `/api/guardar-ubicacion.php` ya estaban separados del buscador de respuestas. Se conservaron el contrato, los identificadores de captura y el guardado automático.
- Cada formulario tenía un único listener, pero dos envíos muy rápidos podían ejecutar cargas y respuestas en paralelo. Ahora se serializan mediante una cola.

## Arquitectura implementada

`assets/js/contexto-rpv.js` es un módulo JavaScript nativo, compatible tanto con navegador como con CommonJS para las pruebas. Expone:

- `create(options)`: crea una sesión conversacional aislada.
- `process(message, { knowledge })`: normaliza, reconoce contexto, produce una sola respuesta y actualiza el estado.
- `reset()` y `getState()`: limpian o consultan el estado.
- `createKnowledgeBase(markdown)`: analiza las secciones del Markdown y realiza búsquedas acotadas por producto e intención.
- Utilidades de normalización, detección de producto/intención, números escritos y unidades explícitas.

El estado conserva producto, modelo, intención, pregunta pendiente, unidad esperada, datos no sensibles, historial semántico limitado, última respuesta y repeticiones. Se guarda durante la pestaña en `sessionStorage` con versión de esquema. No se guardan coordenadas, ubicación manual, identificadores de captura ni texto crudo del usuario. Una versión incompatible se descarta.

El orden efectivo es: normalización; saludo con consulta; cambio explícito de producto; catálogo; pregunta pendiente; respuestas cortas; números y unidades; continuaciones; intenciones comerciales; consulta del Markdown; repetición; actualización del estado. Una consulta explícita de otro producto cancela la pregunta pendiente anterior.

## Comportamiento relevante

- Una cifra se interpreta exclusivamente con la unidad pendiente. Se reconocen cifras y palabras de uno a veinte, además de expresiones como “unas seis personas”.
- Una unidad explícita contradictoria solicita aclaración y no guarda el dato.
- Tinajas: orienta entre 4 y 7 personas y recuerda el modelo elegido.
- Calderas: guarda litros y solicita el tipo de uso sin extrapolar rendimientos ni prometer compatibilidad.
- Estufas: guarda m² y solicita datos de aislación, dejando claro que la cobertura es referencial.
- “Sí” solo continúa una oferta pendiente, nunca confirma compra, condiciones ni tratamiento de datos.
- “La grande”, “la pequeña”, “la segunda”, precio, despacho y cambios de producto utilizan el contexto vigente.
- Las reformulaciones se detectan mediante intención, producto y modelo. Se vuelve a entregar información y luego se ofrece aclarar un aspecto concreto.
- Precio, especificaciones, despacho y condiciones se obtienen del Markdown; el gestor no contiene montos comerciales.
- Precio y despacho solicitados juntos se entregan en una sola respuesta principal.

## Archivos modificados

- `DESARROLLO/Web/assets/js/contexto-rpv.js`: nuevo gestor central.
- `DESARROLLO/Web/assets/js/asistente-rpv.js`: integración, limpieza del estado y cola de envíos.
- `DESARROLLO/Web/assets/js/asistente-rpv-calderas.js`: misma integración con alcance inicial de calderas.
- `DESARROLLO/Web/index.html`: carga ordenada del gestor y el controlador.
- `DESARROLLO/Web/calderas/index.html`: reincorporación del asistente y carga de sus recursos.
- `DESARROLLO/Web/assets/css/asistente-rpv.css`: estilos aislados y responsive para páginas interiores.
- `DESARROLLO/tests/contexto-rpv.test.cjs`: pruebas automatizadas sin dependencias.
- `DESARROLLO/tests/contexto_web.py`: prueba integral con PHP local, Playwright y Edge.
- Los equivalentes en `PRODUCCION/Web` se generan mediante `preparar-produccion.ps1` después de validar.

No se modificaron `conocimiento-rpv.md`, el catálogo fuente, MySQL ni `/api/guardar-ubicacion.php`.

## Pruebas

Ejecutar desde la raíz con el Node incluido en el entorno de Codex/Cursor o cualquier Node moderno:

```powershell
node .\DESARROLLO\tests\contexto-rpv.test.cjs
node --check .\DESARROLLO\Web\assets\js\contexto-rpv.js
node --check .\DESARROLLO\Web\assets\js\asistente-rpv.js
node --check .\DESARROLLO\Web\assets\js\asistente-rpv-calderas.js
```

La batería cubre saludo único, saludo con consulta, error ortográfico, catálogo, selección de producto, personas, litros, m², cantidad, contradicción de unidad, “sí”, modelo grande, despacho, precio reformulado, cambio de tema, número ambiguo, mensaje vacío, varias intenciones, fallo de conocimiento, limpieza, recuperación de sesión, límite de datos persistidos, cola de envíos, reglas móviles y carga real del Markdown.

Ejecutar también la prueba integral:

```powershell
python .\DESARROLLO\tests\contexto_web.py
```

Resultado de esta implementación: 23/23 pruebas unitarias aprobadas; prueba integral aprobada en portada y `/calderas/` con viewport móvil, recarga, limpieza y fallo simulado del Markdown; los tres archivos JavaScript pasan `node --check`.

## Prueba en localhost

1. Iniciar el servidor documentado con `powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\iniciar-servidor.ps1`.
2. Abrir `http://localhost:8000/` y `http://localhost:8000/calderas/`; no abrir los HTML mediante `file:///`.
3. Abrir el asistente y probar, en conversaciones limpias: `Hola`; `Quiero saber de los productos` → `Tinajas` → `6` → `Sí`; `Calderas` → `1500` → `uso frecuente` → `¿Y el precio?`; `Estufas` → `65`; y `¿Y el despacho?`.
4. Recargar después de ingresar un dato y comprobar que el contexto de la pestaña continúa. Pulsar “Limpiar” y comprobar que desaparece.
5. En las herramientas del navegador, probar anchos de 360, 390, 768 y escritorio. Confirmar que solo aparece una respuesta por envío y que el chat se puede cerrar.
6. Para simular el fallo del Markdown, bloquear temporalmente `/assets/data/conocimiento-rpv.md` en DevTools: el estado no debe romperse y el asistente debe derivar sin inventar información.

## Instalación en cPanel

1. Respaldar `public_html` y la base de datos antes de publicar.
2. Ejecutar las pruebas y el verificador del catálogo.
3. Preparar `PRODUCCION` con el flujo del repositorio. Esto crea un ZIP de respaldo antes de sincronizar.
4. Subir el contenido de `PRODUCCION/Web` a la raíz de `public_html`, conservando rutas `/assets/js`, `/assets/css` y `/assets/data`.
5. No subir configuraciones privadas ni reemplazar `/home/rpvconst/conexion_rpv.php`.
6. Purgar caché de navegador/CDN y verificar portada, `/calderas/`, Markdown, WhatsApp y guardado real de ubicación.

> Estado al 25 de noviembre de 2026: el verificador previo a publicación informa `Catálogo sin sincronizar: productos.json`. El catálogo fuente contiene valores que parecen de prueba y no se regeneró ni se copió a Producción para evitar publicar información comercial no validada. Antes de ejecutar el paso 3, un responsable debe corregir o confirmar esos valores desde el administrador de precios y volver a ejecutar `sincronizar-catalogo.php --check`.

## Reversión

1. Conservar el ZIP automático creado en `respaldos/codigo/produccion-*.zip` al preparar Producción.
2. Si aparece un error, restaurar únicamente los archivos web del ZIP previo en `public_html`.
3. Purgar caché y volver a comprobar portada y `/calderas/`.
4. No se requiere reversión de base de datos: esta implementación no incorpora migraciones ni cambia el contrato PHP.

## Limitaciones y recomendaciones futuras

- El procesamiento es determinista y basado en reglas; expresiones muy alejadas del vocabulario previsto pueden requerir aclaración.
- Los números escritos se reconocen hasta veinte. Capacidades mayores deben expresarse con cifras, como `1500` o `1.500`.
- La recomendación técnica permanece deliberadamente conservadora: el gestor no calcula potencia ni garantiza rendimiento.
- La interfaz visual completa debe revisarse en navegadores móviles reales antes de publicar.
- En una etapa futura conviene retirar del todo las funciones conversacionales antiguas que ahora permanecen inactivas en los controladores, una vez confirmada la estabilidad en producción, y centralizar también el adaptador de interfaz para reducir más duplicación.
