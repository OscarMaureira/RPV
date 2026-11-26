# Plantilla de solicitud para un nuevo modulo administrativo

Implementa el modulo **[IDENTIFICADOR]** en el panel RPV, solo en `DESARROLLO`.

## Alcance

- Nombre visible: **[NOMBRE]**
- Objetivo: **[OBJETIVO]**
- Fuente de datos existente: **[TABLAS/ARCHIVOS/API VERIFICADOS]**
- Operaciones: **[VER/CREAR/EDITAR/PUBLICAR/OTRAS]**
- Permisos granulares: **[modulo.ver, modulo.editar, ...]**

## Reglas obligatorias

1. Verificar el esquema y las rutas reales; no inventar campos.
2. Registrar el modulo en `config/modulos.php` con ruta fija, icono, permiso y orden.
3. Proteger cada operacion en backend con `Permissions::require()` y CSRF.
4. Usar consultas PDO preparadas, escape `h()` y `adminAudit()` sin secretos.
5. Reutilizar `componentes/layout.php` y `assets/panel.css`; mantener responsive.
6. Crear una migracion versionada si es necesaria, sin ejecutarla en produccion.
7. Agregar pruebas de permisos, validacion, mutaciones y regresion publica.
8. Documentar instalacion, reversión, dependencias y riesgos.
9. No modificar `PRODUCCION`, publicar en cPanel ni alterar datos productivos.

## Criterios de aceptacion

- El sidebar y dashboard muestran el modulo solo con el permiso correspondiente.
- El acceso directo sin permiso devuelve HTTP 403.
- Las operaciones criticas quedan en `admin_log`.
- No se exponen datos privados en HTML, JavaScript, URLs ni auditoria.
- Las pruebas ejecutadas y sus resultados quedan informados con precisión.
