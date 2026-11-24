# RPV Construcciones — Prototipo con asistente Markdown

## Archivos
- `index.html`: página y asistente.
- `conocimiento-rpv.md`: base de conocimiento editable.
- `assets/`: fotografías WebP optimizadas.

## Cómo probarlo
No abras el HTML directamente con doble clic: los navegadores bloquean normalmente `fetch()` a archivos locales con `file://`.

Abre una terminal dentro de esta carpeta y ejecuta:

```bash
python3 -m http.server 8000
```

Después visita `http://localhost:8000` y abre el asistente. Verás `✓ Conectado a conocimiento-rpv.md`.

## Editar información
Edita `conocimiento-rpv.md` usando secciones Markdown con títulos `##`, por ejemplo `## Tinajas`. Guarda y recarga la página. Cada visitante leerá la versión que esté publicada.

## Alcances y limitaciones
Este prototipo utiliza búsqueda local basada en palabras y sinónimos sobre el archivo Markdown, no inteligencia artificial generativa. No inventa cotizaciones y recomienda WhatsApp si no encuentra una respuesta. Para comprensión avanzada de preguntas, se puede añadir posteriormente un backend con un modelo de IA y búsqueda documental (RAG), sin publicar claves API en el navegador.

## WhatsApp
Destino de derivaciones: `+56 9 9748 5882`. El botón dentro del asistente indica que el usuario inició la consulta en el chat y añade su última pregunta.

## Animación de escritura (nueva mejora)
Las respuestas del asistente se presentan progresivamente, con un breve retraso inicial y un cursor visual. Las preguntas de los clientes aparecen inmediatamente. Los botones Sí / No de WhatsApp se muestran al finalizar la respuesta. Si llega una nueva pregunta durante la animación, la respuesta anterior se completa inmediatamente y comienza la nueva; Limpiar cancela la animación y reinicia el chat. Para usuarios con reducción de movimiento activada, la respuesta aparece de inmediato.
