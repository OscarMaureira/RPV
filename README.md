# RPV Construcciones — ambientes

- `DESARROLLO/`: fuente de trabajo y pruebas locales con XAMPP.
- `PRODUCCION/`: versión validada que se publica en cPanel.
- `respaldos/`: ubicación local para copias de código y bases de datos; no se publica.

El flujo es siempre **DESARROLLO → validación → PRODUCCION → cPanel**.

Para generar una publicación local, ejecuta:

`powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\preparar-produccion.ps1 -ConfirmarPreparacion`

Para revisar el resultado visual final sin publicar, ejecuta:

`powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\vista-produccion.ps1`

O abre `VER_VISTA_PRODUCCION.cmd`. La vista debe cargarse desde
`http://localhost:8081`; abrir un HTML directamente desde el explorador impide
que se carguen los recursos ubicados en `/assets/`.
