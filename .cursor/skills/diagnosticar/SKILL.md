---
name: diagnosticar-rpv
description: Diagnostica el ambiente local DESARROLLO de RPV Construcciones y entrega un informe HTML legible con hallazgos, evidencia y backlog. Úsalo cuando el usuario pida diagnosticar o revisar el sitio en desarrollo.
---

# Diagnosticar RPV

## Alcance estricto

Diagnostica únicamente el ambiente local `DESARROLLO`.

- Inspecciona `DESARROLLO/Web`, sus recursos, scripts y configuración local.
- Para una revisión visual, usa solo `http://localhost:8000`.
- Puede iniciar el servidor de desarrollo si es necesario para observar la página.
- No abras, consultes ni evalúes `PRODUCCION`, `http://localhost:8081`,
  `rpvconstrucciones.com`, cPanel, buscadores ni competidores.
- No actualices archivos del sitio salvo que el usuario pida explícitamente
  implementar una corrección después del diagnóstico. El informe HTML indicado
  en **Salida** es la única escritura autorizada por este flujo.

## Objetivo

Detectar mejoras accionables que ayuden a que el sitio de desarrollo comunique
mejor su oferta de tinajas, calderas y estufas a pellet, y que reduzcan fricción
para cotizar por WhatsApp.

## Revisión

Evalúa solo lo que sea observable o verificable localmente:

1. Estructura, navegación, enlaces internos y recursos cargados.
2. Propuesta de valor, jerarquía visual, CTA, formularios y flujo hacia WhatsApp.
3. Fichas o secciones de producto: precio o mecanismo de cotización,
   especificaciones, instalación, despacho, garantía, preguntas frecuentes y
   productos complementarios.
4. Contenido, SEO on-page local (títulos, H1/H2, metaetiquetas, canonical,
   alt, enlazado interno y schema presente en el código).
5. UX y accesibilidad: móvil, contraste, foco visible, teclado, semántica,
   carga diferida y `prefers-reduced-motion` cuando aplique.
6. Calidad técnica local: HTML, CSS, JavaScript, PHP, rutas, errores visibles y
   analítica implementada en el código, sin inferir resultados de negocio.

Lee `references/usabilidad-actual.md` antes de valorar UX. Aplica sus criterios
como guía de revisión; no conviertas una recomendación en un defecto si la
evidencia local no permite comprobarla.

Clasifica cada punto como **Verificado**, **No verificable localmente** o
**Recomendación**. No inventes reseñas, certificaciones, precios vigentes,
ventas, tráfico, stock ni métricas de rendimiento.

## Salida

Usa `references/report-template.md` para el contenido y lee
`references/html-output.md` antes de generar el entregable. Incluye evidencia
mediante rutas y fragmentos o elementos locales, severidad, impacto (1-5),
esfuerzo (1-5), backlog y las tres acciones inmediatas. Señala de forma
explícita que el diagnóstico no cubre producción ni el sitio público.

El entregable canónico es un archivo HTML autocontenido en UTF-8:

`DESARROLLO/diagnosticos/diagnostico-rpv-AAAAMMDD-HHMM.html`

- Crea la carpeta si no existe; no guardes el informe dentro de `Web`.
- No sustituyas el HTML por Canvas, Markdown ni texto extenso en el chat, salvo
  que el usuario pida además otro formato.
- Valida que el archivo exista, no contenga marcadores pendientes y sea legible
  sin red ni recursos externos.
- Si hay vista previa o control de navegador disponible, abre el HTML resultante
  después de validarlo. En todos los casos, la respuesta final debe mostrar un
  enlace local clicable al archivo con su ruta absoluta.

El diagnóstico debe terminar siempre con un **Cuadro resumen de hallazgos**.
Es la última sección visible del HTML, sin contenido posterior, y contiene:
estado, hallazgo, evidencia local, prioridad y siguiente acción.

Debe cargar automaticamente en browser el archivo generado
