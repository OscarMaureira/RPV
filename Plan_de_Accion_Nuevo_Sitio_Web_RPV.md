# PLAN DE ACCIÓN INTEGRAL: REDISEÑO Y ARQUITECTURA WEB
## RPV CONSTRUCCIONES & RPV SOLUCIONES TÉRMICAS
**Fecha:** Octubre 2026  
**Objetivo:** Desarrollar una nueva plataforma web bajo el dominio `rpvconstrucciones.com` con arquitectura dual (B2B Ingeniería/Construcción vs. B2C Equipamiento Térmico), optimizada para conversión, captura de leads, SEO local y soporte para integración de agentes de Inteligencia Artificial.

---

### 1. OBJETIVOS ESTRATÉGICOS
1. **Segmentación Clara de Tráfico:** Separar el público corporativo e industrial (Retail, Minería, Vivienda de alto estándar) del cliente final residencial (Calderas, Estufas y Tinajas).
2. **Optimización de Conversión (CRO):**
   - B2B: Formulario técnico para licitaciones, planos y solicitud de reuniones.
   - B2C: Botón directo "Cotizar por WhatsApp" con cálculo rápido de flete y requerimientos.
3. **Preparación para Agente IA:** Estructurar páginas y microdatos para que un agente virtual clasificador (Router) guíe a los visitantes según su necesidad.
4. **Posicionamiento Orgánico (SEO):** Atacar palabras clave diferenciadas para construcción en Chile y climatización a pellets.

---

### 2. ARQUITECTURA DE INFORMACIÓN Y ESTRUCTURA DE URLS

```
rpvconstrucciones.com/
│
├── / (Home Portal / Bienvenida Dual)
│     ├── Hero institucional con doble acceso
│     ├── Respaldo técnico (m2 construidos, años de experiencia)
│     └── Accesos a WhatsApp y contacto general
│
├── /construccion/ (Línea B2B & Obras Civiles)
│     ├── /edificacion (Habitacional, Hormigón Armado, Estructuras Metálicas)
│     ├── /ingenieria (Cálculo estructural, diseño basal, fundaciones)
│     ├── /obras-civiles (Movimiento de tierras, Piping y Geomembranas HDPE)
│     ├── /proyectos (Casos de éxito: Teck QB041, Collahuasi, Retail BCI/Banchile)
│     └── /staff (Perfiles Rodrigo Pinochet V. y Antonio Rehbein G.)
│
├── /climatizacion/ (Línea B2C Confort y Productos Térmicos)
│     ├── /calderas-pellets (Ficha técnica $730.000 CLP, uso sin luz, tinajas/piscinas)
│     ├── /estufas-pellets (Ficha técnica $290.000 CLP, calefacción económica limpia)
│     ├── /tinajas-fibra (Modelos hot tub, integración con caldera, cotizador a medida)
│     └── /combos-ahorro (Pack Tinaja + Caldera Exterior)
│
└── /contacto/
      ├── Formulario técnico B2B (adjuntar planos/cubicaciones)
      ├── Acceso directo WhatsApp comercial
      └── Datos legales (San Bernardo, Región Metropolitana)
```

---

### 3. FASES DEL PROYECTO (CRONOGRAMA DE 6 SEMANAS)

#### SEMANA 1: Definición y Plataforma Tecnológica
- **Elección Tecnológica:** 
  - Si se busca simplicidad máxima: WordPress + Elementor Pro o Webflow (para gestionar fácilmente catálogos, formularios dinámicos y enlaces SEO).
  - Si se mantiene en Google Sites: Configurar navegación multinivel y módulos de código para tracking.
- **Inventario de Activos:** Recopilar fotografías en alta definición de obras de retail, faenas mineras y fotografías de las calderas y tinajas en funcionamiento.
- **Configuración de Dominio y Certificados:** Verificación DNS y SSL en `rpvconstrucciones.com`.

#### SEMANA 2: Diseño UX/UI y Wireframes
- **Home:** Diseño estilo "Split-Screen" o dos grandes banners principales:
  - *Bloque Izquierdo:* "Ingeniería y Construcción B2B - Ejecución responsable con resultados confiables".
  - *Bloque Derecho:* "Climatización y Tinajas - Calor sustentable y descanso todo el año".
- **Plantillas Internas B2B:** Estilo sobrio, azul corporativo/gris, foco en especificaciones, métricas de obras y credenciales de clientes.
- **Plantillas Internas B2C:** Estilo cálido, verde/naranja, fotos de tinajas al atardecer, fuego de pellet, insignias destacadas ("Sin electricidad", "Ahorro garantizado").

#### SEMANA 3: Redacción de Contenidos y SEO On-Page
- **Páginas B2B:** Integrar textos con palabras clave: *"empresa de construcción retail en Santiago"*, *"obras civiles minería Chile"*, *"impermeabilización HDPE"*, *"inspección técnica de obras ITO"*.
- **Páginas B2C:** Redacción persuasiva y directa con llamadas de acción (CTA): *"comprar caldera a pellet para tinaja"*, *"estufas a pellet económicas Santiago"*, *"tinajas de fibra de vidrio resistentes"*.
- **Metadatos:** Títulos y meta descripciones únicas de 150-160 caracteres por página.

#### SEMANA 4: Desarrollo Funcional y Formularios
- Creación de formularios diferenciados:
  1. *Formulario Construcción:* Nombre, Empresa, Tipo de Obra, Comuna, Presupuesto estimado, Adjuntar archivo (PDF/DWG).
  2. *Formulario Productos:* Modelo de interés, ¿Cuenta con tinaja o requiere completa?, Comuna de despacho.
- Botones flotantes de WhatsApp configurados con mensajes predeterminados distintos según la URL:
  - Desde `/construccion/*`: *"Hola Rodrigo, me gustaría consultar por un proyecto de construcción..."*
  - Desde `/climatizacion/*`: *"Hola, me interesa solicitar cotización de la caldera/estufa a pellets..."*

#### SEMANA 5: Integración del Agente IA y Base de Conocimiento
- Carga del archivo Markdown institucional y las directrices en la plataforma del agente (ej. Landbot, Typebot, Voiceflow o Widget web personalizado).
- Configuración del mensaje inicial con botones de selección (Construcción vs. Productos).
- Pruebas del agente verificando:
  - Respuestas exactas de precios ($730.000 caldera / $290.000 estufa).
  - Derivación formal a `rpinochetv@rpvconstrucciones.com` o `+56 9 6417 0206`.

#### SEMANA 6: Pruebas, Analítica y Lanzamiento
- Instalación de Google Analytics 4 (GA4) y Google Search Console.
- Vinculación con Google Business Profile (ficha local en San Bernardo).
- Pruebas de velocidad de carga en móvil y escritorio (Core Web Vitals).
- Pase a producción y publicación oficial.

---

### 4. MATRIZ DE RESPONSABILIDADES Y ROLES

| Área | Responsable | Entregable Clave |
| :--- | :--- | :--- |
| **Dirección General** | Rodrigo Pinochet V. | Aprobación de presupuestos, portafolio y validación comercial |
| **Operaciones y Obras** | Antonio Rehbein G. | Fichas técnicas de obras civiles y minería |
| **Diseño y Desarrollo Web** | Desarrollador / Agencia | Maquetación web, UX/UI, formularios y optimización móvil |
| **Contenidos y SEO** | Especialista Digital | Redacción orientada a conversión y posicionamiento local |
| **Configuración Agente IA** | Administrador de Sistema | Conexión de API, base de conocimiento y reglas de derivación |

---

### 5. CHECKLIST DE SALIDA A PRODUCCIÓN
- [ ] Doble acceso en portada funcionando y visible en pantallas móviles.
- [ ] Enlace directo de llamada y WhatsApp a Rodrigo Pinochet Velozo (+56 9 6417 0206).
- [ ] Fichas de precios de calderas y estufas visibles con llamada clara a cotizar flete.
- [ ] Páginas de retail, habitacional y minería con respaldo de clientes (BCI, Teck, Collahuasi).
- [ ] Agente IA entrenado respondiendo según la intención del visitante.
- [ ] Certificado SSL activo (candado seguro HTTPS).
