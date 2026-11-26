---
name: qa
description: Valida el ambiente DESARROLLO de RPV Construcciones, incluyendo el sitio, el Panel de Administración, PHP, base local, recursos visuales y API. Siempre inicia y abre solo los módulos de DESARROLLO. Usar cuando el usuario escriba /qa, pida QA, pruebas, validación funcional o revisión técnica del sitio.
disable-model-invocation: true
---

# QA — RPV Construcciones

Ejecuta el comando desde la raíz del proyecto. QA siempre inicia y abre en el
navegador predeterminado únicamente estos módulos del ambiente de desarrollo:

- Sitio RPV: `http://localhost:8000`.
- Panel de Administración RPV: `http://localhost:8002/`.

```powershell
powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\qa.ps1
```

## Alcance

- Inicia y abre `DESARROLLO/Web` en `http://localhost:8000`.
- Inicia y abre el Panel de Administración desde `DESARROLLO/Admin` en
  `http://localhost:8002/`.
- Valida `DESARROLLO/Web` y la paridad con `PRODUCCION/Web`, sin iniciar,
  abrir ni consultar un servidor de producción.
- Nunca usa credenciales de producción ni modifica cPanel.
- No modifica ni prepara `PRODUCCION`; solo la ejecución explícita de `/prod`
  puede actualizar esa carpeta.
- No abre archivos HTML con `file:///`.
- Nunca usa `http://localhost:8081` durante QA.

## Resultado esperado

Reporta en español: estado general, pruebas aprobadas, advertencias y acciones
pendientes. Si falla una prueba, indica el archivo o servicio afectado y no
afirmes que está listo para publicar.
