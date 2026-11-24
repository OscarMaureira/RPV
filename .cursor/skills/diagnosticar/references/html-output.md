# Salida HTML del diagnóstico

Genera un único documento HTML autocontenido, fácil de leer en navegador y al
imprimir. El HTML es el entregable principal del diagnóstico.

## Ubicación y nombre

- Guarda en `DESARROLLO/diagnosticos/`.
- Usa `diagnostico-rpv-AAAAMMDD-HHMM.html`, con fecha y hora locales.
- No escribas el informe dentro de `DESARROLLO/Web` para evitar publicarlo.

## Estructura mínima

- `<!doctype html>`, `<html lang="es">`, `<meta charset="utf-8">` y viewport.
- Título descriptivo con fecha del diagnóstico.
- `<main>` centrado con ancho de lectura cómodo.
- Las secciones y el orden de `report-template.md`.
- Tablas semánticas con `<caption>`, encabezados `<th scope="col">` y un
  contenedor con desplazamiento horizontal para pantallas estrechas.
- Evidencias locales en `<code>` y estados expresados con texto; el color nunca
  debe ser la única señal.
- CSS incluido en `<style>`, con contraste legible, foco visible y reglas
  `@media print` y `@media (max-width: ...)`.

## Portabilidad y seguridad

- No uses JavaScript, fuentes remotas, CDN, imágenes externas ni solicitudes de
  red. No incluyas credenciales, cookies, consultas SQL sensibles ni datos
  personales innecesarios.
- Escapa el contenido derivado de archivos, respuestas o datos antes de
  insertarlo en HTML.
- Usa UTF-8 y evita texto corrupto o entidades innecesarias.

## Validación y presentación

Antes de entregar:

1. Confirma que el archivo existe y tiene contenido.
2. Comprueba que no quedan `[FECHA]`, `TODO`, `{{...}}` ni secciones vacías.
3. Verifica que el título, las tablas y el **Cuadro resumen de hallazgos** estén
   presentes; este cuadro debe ser la última sección visible del `<main>`.
4. Abre el archivo en una vista previa local cuando la herramienta esté
   disponible y revisa que no exista desplazamiento horizontal de página.
5. Muestra siempre un enlace absoluto y clicable al HTML en la respuesta final.
