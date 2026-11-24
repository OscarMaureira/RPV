---
name: cuenta
description: Consulta la cuenta de ChatGPT/Codex activa en Cursor sin exponer credenciales ni tokens. Usar únicamente cuando el usuario invoque explícitamente `$cuenta` o `/cuenta`.
disable-model-invocation: true
---

# Cuenta activa de Codex

Ejecuta:

```powershell
powershell -ExecutionPolicy Bypass -File .\DESARROLLO\scripts\cuenta.ps1
```

Muestra solamente el correo de la cuenta activa, el tipo de autenticación y el plan cuando estén disponibles. Para planes conocidos, también informa el valor mensual de referencia y el costo incremental estimado de los tokens incluidos.

No presenta una conversión ficticia de USD por token: Codex mide la cuota según modelo y tarea, y OpenAI no publica una cantidad fija de tokens incluida por plan. Mientras haya cuota incluida, el costo incremental estimado es USD 0.00; los créditos adicionales deben revisarse en el panel de uso de la cuenta. La consulta usa el endpoint local compatible `account/read` de Codex App Server; nunca lee ni imprime tokens, cookies, contraseñas ni archivos de autenticación.

Si Codex no está autenticado o el correo no está disponible, informa el estado y orienta al usuario a iniciar sesión o revisar el perfil de Codex/Cursor.
