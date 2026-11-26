# Informe de implementacion — Panel administrativo RPV

## Diagnostico

El repositorio estaba en `main`, alineado con `origin/main` y sin cambios locales.
El panel anterior estaba fuera de `Web`, servido como raiz en `localhost:8002`,
con restriccion rígida de host. Opiniones, cuentas y precios compartian
`admin_usuarios`; `admin_log` ya auditaba parte de los accesos y mutaciones. La
web publica consulta `testimonios` y el catalogo usa un JSON con revision y
sincronizacion propia. El script productivo actual solo replica `Web` y el modulo
de notificaciones, por lo que no publica el panel.

## Resultado implementado

Se creo un contenedor modular con sidebar responsive, dashboard de cuatro
indicadores reales, registro central de modulos y layout comun. Se integraron
opiniones, usuarios, precios y auditoria. RBAC declara superadministrador,
administrador, comercial y auditor, con permisos por operacion. La seguridad
incluye cookies endurecidas, expiracion, revocacion central, CSRF, consultas
preparadas, rate limiting persistente, auditoria de fallos y TOTP cifrado con
codigos de recuperacion de un uso.

Todas las tablas nuevas usan el prefijo obligatorio `AdmPanel_`. Se conservan
`admin_usuarios` y `admin_log` porque son tablas heredadas con datos y contratos
ya utilizados por el proyecto; no son estructuras nuevas del panel.

## Compatibilidad

No se modifico `PRODUCCION`, la API publica de opiniones ni la mecanica de
sincronizacion del catalogo. Antes de aplicar la migracion, login y dashboard
mantienen un modo compatible con las cuentas existentes. Las funciones que
dependen de roles, sesiones persistentes y estados nuevos requieren la migracion.

## Validacion

Usar:

```powershell
C:\xampp\php\php.exe DESARROLLO\tests\admin_panel.php
Get-ChildItem DESARROLLO\Admin -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

La prueba integral con base requiere aplicar primero la migracion con una cuenta
DDL local. No se ejecutaron pruebas contra produccion ni se prepararon artefactos
en `PRODUCCION`.

Resultados obtenidos el 2026-11-25:

- 22 archivos PHP del panel: sintaxis valida.
- 10 controles unitarios de TOTP, cifrado y presencia de estructuras: aprobados.
- Respuesta HTTP local, redireccion a login, cabeceras CSP y cookie `HttpOnly`/
  `SameSite=Strict`: aprobadas.
- Login incorrecto con CSRF: rechazado con mensaje generico, sin revelar cuenta.
- Verificador CSP actualizado al layout comun: aprobado.
- Conexion a MariaDB de desarrollo, puerto 4444: aprobada.
- Sincronizacion Git: `main` quedo 0 commits adelante y 0 atras de `origin/main`.
- Migracion local: no aplicada. El preflight confirmó que la cuenta PDO web no
  posee `CREATE`, `ALTER` ni `REFERENCES`; no se intentó elevar sus privilegios.
- Pruebas heredadas de catalogo: no aprobadas. `catalogo.php` no copia una
  conexion PDO a su fixture temporal y `catalogo-js.cjs` falla en su escenario
  de vigencia expirada. Son fallos reproducibles fuera del panel y no se marcaron
  como aprobados.
- Cambios en `PRODUCCION`: ninguno.
