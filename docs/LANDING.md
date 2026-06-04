# Landing Builder — Basic UI Render

Basado en la arquitectura modular monolith + Flux UI/Livewire definida en los documentos. 

---

## Objetivo UX

No construir un clon de Elementor.

El builder debe sentirse:

* rápido
* limpio
* opinionated
* seguro
* orientado a contenido

No freedom hell.

---

# Layout General

```text
┌──────────────────────────────────────────────────────────────┐
│ Top Toolbar                                                 │
├──────────────┬──────────────────────────────┬───────────────┤
│ Block Library│ Canvas / Page Preview        │ Properties    │
│              │                              │ Panel         │
│              │                              │               │
│              │                              │               │
├──────────────┴──────────────────────────────┴───────────────┤
│ Bottom Status / Responsive Preview                          │
└──────────────────────────────────────────────────────────────┘
```

---

# 1. Top Toolbar

## Objetivo

Acciones globales de la página.

---

## Render

```text
┌──────────────────────────────────────────────────────────────┐
│ Logo | Landing Builder                          Draft ▼      │
│--------------------------------------------------------------│
│ [+ Add Block] [Templates] [Theme] [Preview] [Publish]        │
└──────────────────────────────────────────────────────────────┘
```

---

## Acciones

### Add Block

Abre modal/sidebar.

### Templates

Cambiar template base.

### Theme

Editar:

* colores
* tipografía
* spacing
* radius

### Preview

Desktop / tablet / mobile.

### Publish

Publicar landing.

---

# 2. Left Sidebar — Block Library

## Objetivo

Agregar bloques.

---

## Render

```text
┌──────────────────────┐
│ Search Blocks...     │
├──────────────────────┤
│ BASIC                │
│ ├ Hero               │
│ ├ CTA                │
│ ├ Features           │
│ ├ FAQ                │
│                      │
│ CONTENT              │
│ ├ Gallery            │
│ ├ Testimonials       │
│ ├ Pricing            │
│                      │
│ FOOTER               │
│ ├ Footer             │
└──────────────────────┘
```

---

## UX

Click:

```text
Add block below selected block
```

No drag-drop complejo inicialmente.

Reorder simple:

```text
↑ ↓
```

Mucho más estable.

Menos bugs Livewire.

---

# 3. Main Canvas

## Objetivo

Vista editable de la landing.

---

## Render

```text
┌───────────────────────────────────────┐
│ HERO BLOCK                            │
│───────────────────────────────────────│
│ Automate your business                │
│ Stop using Excel + WhatsApp           │
│                                       │
│ [ Start Now ]                         │
│                                       │
│                    [ image ]          │
│                                       │
│ [Edit] [Duplicate] [Delete] [↑] [↓]  │
└───────────────────────────────────────┘
```

---

## Estado Hover

Cuando el usuario pasa mouse:

```text
+ outline
+ quick actions
```

---

## Estado Selected

```text
blue border
properties panel sync
```

---

# 4. Right Sidebar — Properties Panel

## Objetivo

Editar bloque seleccionado.

---

## Render

```text
┌──────────────────────────────┐
│ Hero Block                   │
├──────────────────────────────┤
│ Variant                      │
│ [ Split ▼ ]                  │
│                              │
│ Headline                     │
│ [ Automate your business ]   │
│                              │
│ Subtitle                     │
│ [ textarea... ]              │
│                              │
│ Button Label                 │
│ [ Start Now ]                │
│                              │
│ Button URL                   │
│ [ /register ]                │
│                              │
│ Background                   │
│ [ Primary ▼ ]                │
│                              │
│ Padding                      │
│ [ Large ▼ ]                  │
└──────────────────────────────┘
```

---

# 5. Responsive Preview Bar

## Render

```text
┌────────────────────────────────────┐
│ Desktop | Tablet | Mobile          │
└────────────────────────────────────┘
```

Canvas cambia width.

No iframe complejo inicialmente.

---

# 6. Template Selector

## Render

```text
┌─────────────────────────────────────────────┐
│ Choose Template                             │
├─────────────────────────────────────────────┤
│ [ SaaS ]      [ Corporate ]                 │
│                                             │
│ [ Restaurant ] [ Portfolio ]               │
│                                             │
│ [ Ecommerce ]                               │
└─────────────────────────────────────────────┘
```

---

# 7. Theme Editor

## Render

```text
┌──────────────────────────────┐
│ Theme Settings               │
├──────────────────────────────┤
│ Primary Color                │
│ [ #2563EB ]                  │
│                              │
│ Font                         │
│ [ Inter ▼ ]                  │
│                              │
│ Radius                       │
│ [ Large ▼ ]                  │
│                              │
│ Spacing                      │
│ [ Comfortable ▼ ]            │
└──────────────────────────────┘
```

---

# 8. Mobile UX

No intentes builder móvil completo.

Solo:

```text
basic edit support
```

El builder debe ser desktop-first.

---
---


**ESPECIFICACIÓN TÉCNICA**

**Landing Builder**

Catálogo de bloques · Modelo de datos · Plan de sprints

────────────────────────────────────────────────────────────

| Producto | Plinth - Landing Builder Module                               |
| -------- | ------------------------------------------------------------- |
| Versión  | v1.0 - Especificación técnica inicial                         |
| Autor    | Fernando (@nandocdev)                                         |
| Stack    | Laravel 12/13 · Livewire 3 · Flux UI · PostgreSQL · Alpine.js |
| Fecha    | 4 de junio de 2026                                            |
| Estado   | DRAFT - Pendiente de revisión arquitectónica                  |

# **1\. Introducción**

Este documento define la especificación técnica completa del módulo Landing Builder de Plinth. Cubre el modelo de datos, el catálogo de bloques versionado por sprint, las interfaces de componentes y las decisiones arquitectónicas clave.

## **1.1 Alcance**

- Modelo de persistencia: schema SQL + contrato JSON por bloque
- Catálogo de 12 bloques distribuidos en 4 sprints
- Variantes, config keys y partials por bloque
- Arquitectura de estado: Alpine local + Livewire persistencia
- Pipeline de publish: HTML estático → CDN
- Decisiones de diseño explícitas con justificación

## **1.2 Fuera de alcance**

- Drag-and-drop complejo (descartado, ver §2.4)
- Editor CSS libre (descartado definitivamente)
- Bloques anidados / nested columns
- Editor WYSIWYG tipo TinyMCE
- Builder móvil nativo (desktop-first, soporte básico mobile en v2)

# **2\. Arquitectura**

## **2.1 Stack**

- Backend: Laravel 12/13 + Livewire 3
- Frontend UI: Flux UI + Alpine.js
- Base de datos: PostgreSQL (JSONB para bloques y tema)
- Serving público: HTML estático generado en publish → R2/S3 + CDN

## **2.2 Modelo de persistencia**

Columna JSONB en tabla landings. No se normalizan los bloques - son configuraciones, no entidades con relaciones propias.

\-- Tabla principal

CREATE TABLE landings (

id UUID PRIMARY KEY DEFAULT gen_random_uuid(),

tenant_id UUID NOT NULL REFERENCES tenants(id),

slug VARCHAR(200) NOT NULL,

title VARCHAR(300),

theme JSONB NOT NULL DEFAULT '{}'::jsonb,

blocks JSONB NOT NULL DEFAULT '\[\]'::jsonb,

status VARCHAR(20) NOT NULL DEFAULT 'draft',

published_at TIMESTAMPTZ,

created_at TIMESTAMPTZ DEFAULT NOW(),

updated_at TIMESTAMPTZ DEFAULT NOW()

);

\-- Historial de versiones (snapshot al publicar)

CREATE TABLE landing_versions (

id UUID PRIMARY KEY DEFAULT gen_random_uuid(),

landing_id UUID NOT NULL REFERENCES landings(id),

blocks_snapshot JSONB NOT NULL,

theme_snapshot JSONB NOT NULL,

published_by UUID REFERENCES users(id),

created_at TIMESTAMPTZ DEFAULT NOW()

);

## **2.3 Schema JSON de bloque**

Cada elemento del array blocks sigue este contrato estricto:

{
    "id": "block_hero_1", // UUID o slug único por landing
    "type": "hero", // Identificador de bloque registrado
    "version": 1, // [MITIGACIÓN] Versión del esquema del bloque para migraciones futuras
    "variant": "split", // Variante activa
    "order": 1, // Posición en el array (fuente de verdad reorder)
    "visible": true, // Toggle sin borrar
    "config": {
        "headline": "Texto principal",
        "subtitle": "Subtítulo",
        "button_text": "Acción",
        "button_url": "/registro"
    },
    "styles": {
        "background": "primary", // token de tema
        "padding": "xl" // token de spacing
    },
    "meta": {
        "created_at": "2025-06-04T00:00:00Z",
        "updated_at": "2025-06-04T00:00:00Z"
    }
}

## **2.4 Arquitectura de estado en el builder**

El builder tiene dos capas de estado con responsabilidades separadas:

- Alpine.js: estado local del editor (blocks[], selectedBlockId, isDirty, theme). Es la fuente de verdad durante la sesión de edición. No hay round-trip al servidor por cada keystroke.
- Livewire: capa de persistencia únicamente. Solo interviene en operaciones explícitas: save(), publish(), loadLanding().

[MITIGACIÓN DE RIESGOS - SYNC]:
1. El builder mantiene un flag `isDirty` en Alpine.
2. El guardado (save) solo se dispara si `isDirty` es true.
3. Se implementa un "Debounced Persist": el cambio en Alpine espera 1s de inactividad antes de marcar `isDirty` para evitar colisiones con procesos de renderizado.
4. Livewire realiza una validación de "Checksum" del JSON recibido para evitar sobreescrituras por sesiones concurrentes.

## **2.5 Pipeline de publish**

- Usuario hace clic en Publish
- Livewire dispara PublishLandingJob
- Job renderiza cada bloque usando Blade templates
- Genera HTML estático completo con tema inlineado
- Sube el HTML a R2/S3 bajo {tenant_slug}/{landing_slug}/index.html
- Actualiza landings.status = published y published_at
- Guarda snapshot en landing_versions
- Invalida CDN cache (Cloudflare API o equivalente)

[MITIGACIÓN - SECURITY]:
- Los formularios públicos (Contact/Lead) en el HTML estático envían peticiones a un endpoint centralizado que requiere un `X-Landing-Signature`.
- Esta firma se genera en tiempo de publicación y vincula el `landing_id` con el dominio permitido (CORS estricto).
- El endpoint central valida la firma antes de procesar cualquier submission.
- Invalida CDN cache (Cloudflare API o equivalente)

El frontend público nunca ejecuta Livewire. Es HTML estático servido desde CDN. Esto garantiza performance y no consume instancias de servidor para visitantes.

# **3\. Plan de sprints**

12 bloques distribuidos en 4 sprints de 2 semanas. Cada sprint entrega un tipo de landing funcional y vendible de forma independiente.

| **Sprint** | **Foco**   | **Bloques**                                   | **Variantes** | **Entregable**              |
| ---------- | ---------- | --------------------------------------------- | ------------- | --------------------------- |
| Sprint 1   | Core SaaS  | Hero, CTA, Footer                             | 14 variantes  | Landing SaaS funcional      |
| Sprint 2   | Conversión | Features, Pricing, Testimonials               | 12 variantes  | Página de ventas completa   |
| Sprint 3   | Soporte    | FAQ, Contact, About                           | 11 variantes  | Landing con soporte/empresa |
| Sprint 4   | Avanzado   | Statistics, Gallery, Lead Form, Trust Signals | 14 variantes  | Catálogo completo v1        |

Criterio de priorización: bloques por frecuencia de uso en landings SaaS/professional services del mercado LATAM. Hero, CTA y Footer son requeridos en el 100% de los casos - van en Sprint 1.

**SPRINT 1 - Core SaaS**

# **4\. Sprint 1 - Bloques Core**

Objetivo: landing SaaS completamente funcional con los 3 bloques que aparecen en el 100% de los casos. Al finalizar este sprint, Plinth puede publicar una landing real.

## **4.1 Hero**

El bloque de mayor impacto visual. Define la primera impresión. Incluye las variantes de mayor conversión documentadas en patrones SaaS establecidos.

### **Variantes**

- centered - Headline + subtitle + CTA centrados. Sin imagen. Máxima claridad.
- split - Texto izquierda, imagen derecha. Patrón dominante en SaaS B2B.
- image-left - Imagen izquierda, texto derecha.
- bg-image - Imagen de fondo con overlay. Para sectores visuales.
- fullscreen - 100vh. Con scroll indicator. Para landings de alto impacto.

### **Config keys**

- headline (string, requerido) - Texto principal. Max 80 chars recomendado.
- subtitle (string) - Descripción corta. Max 200 chars.
- badge_text (string) - Etiqueta sobre el headline. "Nuevo", "v2.0", etc.
- button_primary_text (string) - Label del CTA principal.
- button_primary_url (string) - URL del CTA principal.
- button_secondary_text (string) - Label del CTA secundario.
- button_secondary_url (string) - URL del CTA secundario.
- image_url (string) - URL de la imagen (aplica a split, image-left, bg-image).
- image_alt (string) - Alt text de la imagen.
- show_stats (boolean) - Muestra strip de estadísticas bajo el CTA.
- stats (array) - Array de {value, label} para el stats strip.

### **Styles keys**

- background: primary | secondary | dark | gradient
- text_align: left | center (ignored en split/image-left)
- padding: md | lg | xl
- image_position: top | center | bottom (para bg-image)

### **Partials del bloque**

- hero-badge.blade.php - Badge opcional sobre el headline
- hero-headline.blade.php - H1 con tipografía de tema
- hero-subtitle.blade.php - Párrafo de descripción
- hero-cta-group.blade.php - Botones primario + secundario
- hero-image.blade.php - Imagen con aspect-ratio y lazy loading
- hero-stats-strip.blade.php - Fila de métricas bajo CTA

### **Notas de implementación**

- El bloque Hero siempre ocupa la posición order=0 en el array. El builder lo impide en otro lugar.
- Para variante fullscreen, height: 100svh con scroll snap opcional.
- Stats strip: datos como JSON en config.stats\[\], renderizados como contadores animados opcionales.
- Lazy loading de imagen desactivado en Hero - está above the fold.

## **4.2 CTA - Call to Action**

Bloque de conversión puro. Sin imagen, máximo foco en la acción. Puede aparecer múltiples veces en una landing (entre secciones y al final).

### **Variantes**

- centered - Headline + descripción + botones centrados. Caso default.
- banner - Texto izquierda, botón derecha. Franja horizontal compacta.
- split - Título izquierda, formulario lead corto derecha.
- floating - Barra fija al bottom del viewport (persiste en scroll).

### **Config keys**

- headline (string, requerido)
- description (string)
- button_primary_text / button_primary_url
- button_secondary_text / button_secondary_url
- show_guarantee (boolean) - Muestra texto de garantía bajo botones.
- guarantee_text (string) - "Sin tarjeta de crédito", "Cancela cuando quieras", etc.

### **Notas de implementación**

- La variante floating usa position: sticky en el builder preview y position: fixed en el HTML publicado.
- Múltiples instancias del bloque CTA en una misma landing son permitidas y comunes.
- El bloque CTA final (último antes de Footer) debe diferenciarse visualmente - usar background: dark o gradient.

## **4.3 Footer**

Único bloque con constraint de posición: siempre el último. El builder previene moverlo.

### **Variantes**

- simple - Logo + nav links en línea + copyright. Mínimo.
- multi-column - Logo + 2-4 columnas de navegación + redes sociales.
- mega - Multi-column + newsletter + redes + badges legales.

### **Config keys**

- logo_url / logo_alt
- columns (array) - \[{title, links: \[{label, url}\]}\]
- social_links (array) - \[{platform, url}\]. Íconos auto-seleccionados por platform.
- show_newsletter (boolean) - Muestra input de email.
- newsletter_action_url (string) - Endpoint para el form de newsletter.
- copyright_text (string) - Texto de copyright.
- legal_links (array) - \[{label, url}\] para Política de privacidad, T&C.

### **Notas de implementación**

- El bloque Footer siempre ocupa order = max(blocks.length - 1). El builder lo impide en otra posición.
- Social links: platform acepta: twitter, linkedin, instagram, facebook, github, youtube, tiktok. El sistema mapea a íconos del icon set del tema.
- Newsletter form: POST simple, no integración con ESPs en v1. Solo captura el email.

**SPRINT 2 - Conversión**

# **5\. Sprint 2 - Bloques de Conversión**

Objetivo: completar el stack de una página de ventas. Features describe el producto, Pricing cierra la decisión, Testimonials reduce fricción.

## **5.1 Features / Services**

Describe el valor del producto o servicio. Es el bloque más personalizable en variantes porque la presentación de features depende del tipo de producto.

### **Variantes**

- 3-columns - Grid de 3 feature cards. El patrón más común en SaaS.
- 4-columns - Grid denso para productos con muchas features.
- alternating-rows - Feature alternada: imagen izquierda/derecha. Para demos visuales.
- icon-list - Lista vertical con íconos. Para servicios o características simples.
- cards - Cards con hover effect y CTA por feature.

### **Config keys**

- section_title (string) - Título de la sección.
- section_subtitle (string) - Descripción corta de la sección.
- features (array) - \[{icon, title, description, image_url?, cta_text?, cta_url?}\]
- columns_count (integer: 2|3|4) - Solo aplica a variantes grid.

### **Partials**

- feature-card.blade.php - Card individual
- feature-icon.blade.php - Ícono del feature (SVG o icon set)
- feature-row.blade.php - Fila alternada para alternating-rows

### **Notas de implementación**

- El campo icon acepta: nombre de ícono del set del tema, o URL a SVG custom.
- Para alternating-rows, el orden izquierda/derecha se alterna automáticamente por índice par/impar. No configurable por ítem.
- Max 12 features por bloque. Más de eso indica que se necesitan múltiples bloques Features.

## **5.2 Pricing**

El bloque de mayor impacto en conversión después del Hero. Requiere soporte correcto para el plan destacado (featured) y el toggle mensual/anual.

### **Variantes**

- cards - Cards lado a lado. 2-4 planes. Estándar.
- featured-plan - Como cards pero uno destacado (más grande, borde accent).
- comparison-table - Tabla feature-por-feature entre planes. Para decisiones complejas.

### **Config keys**

- section_title / section_subtitle
- show_toggle (boolean) - Toggle mensual / anual.
- annual_discount_text (string) - Etiqueta del descuento anual. "Ahorra 20%".
- plans (array) - Ver sub-schema de plan abajo.

### **Sub-schema de plan**

{

"id": "starter",

"name": "Starter",

"badge": "Más popular", // null si no aplica

"is_featured": true,

"price_monthly": 29,

"price_annual": 23,

"currency": "USD",

"description": "Para equipos pequeños",

"features": \[

{ "text": "5 usuarios", "included": true },

{ "text": "Soporte 24/7", "included": false }

\],

"cta_text": "Empezar gratis",

"cta_url": "/registro?plan=starter"

}

### **Notas de implementación**

- El toggle mensual/anual es Alpine.js puro - sin Livewire. Cambia qué precio se muestra con x-show.
- El plan featured recibe borde de 2px accent y escala ligeramente (scale-105) sobre los demás.
- Para comparison-table, la primera columna lista los features; las siguientes son los planes. Máximo 4 planes en esta variante.
- El campo currency en v1 es solo presentacional. No conecta con Stripe en este bloque.

## **5.3 Testimonials**

Reduce la fricción de compra. Los testimonios deben parecer reales: foto, nombre, empresa, contexto.

### **Variantes**

- grid - 2-3 columnas de testimonial cards. Para 4-9 testimonios.
- carousel - Un testimonio a la vez con navegación. Para 3-8 testimonios.
- single-featured - Un testimonio grande con foto lateral. Para el testimonio anchor.

### **Config keys**

- section_title / section_subtitle
- testimonials (array) - \[{name, role, company, avatar_url, quote, rating?}\]
- show_rating (boolean) - Muestra estrellas.
- autoplay (boolean) - Para carousel. Interval: 5s.

### **Notas de implementación**

- El carousel usa Alpine.js con x-data. No depende de Livewire ni de Swiper en v1.
- Avatar: si avatar_url está vacío, genera iniciales desde name. Fondo color derivado del nombre.
- Rating: siempre de 5. Si rating es null, no renderiza estrellas aunque show_rating sea true.
- Mínimo 1 testimonio. No renderiza el bloque si testimonials está vacío.

**SPRINT 3 - Soporte y empresa**

# **6\. Sprint 3 - Soporte y Empresa**

Objetivo: landing con sección de preguntas frecuentes, formulario de contacto y sección corporativa. Completa el stack para negocios de servicios y SaaS con alto ticket.

## **6.1 FAQ**

Reduce objeciones antes del CTA final. El accordion es el patrón estándar - implementarlo correctamente en accesibilidad.

### **Variantes**

- accordion - Una pregunta se expande a la vez. El patrón más usado.
- two-columns - Preguntas y respuestas en grid 2 columnas. Para FAQs cortas.
- simple-list - Sin accordion. Todo visible. Para FAQs muy cortas (< 5 items).

### **Config keys**

- section_title / section_subtitle
- items (array) - \[{question, answer}\]
- open_first (boolean) - El primer item empieza expandido.
- show_contact_cta (boolean) - Link a contacto al final de la sección.
- contact_cta_text / contact_cta_url

### **Notas de implementación**

- El accordion usa Alpine.js x-data con activeIndex. Accesibilidad: aria-expanded, aria-controls en cada trigger.
- La respuesta acepta texto con saltos de línea. Se renderiza como párrafos múltiples.
- El bloque FAQ es candidato a JSON-LD para SEO (FAQPage schema). Implementar en el pipeline de publish.
- Max 20 items por bloque. Sin paginación en v1.

## **6.2 Contact**

Formulario de contacto básico. Sin integraciones externas en v1 - almacena en tabla contact_submissions y envía notificación por email interno.

### **Variantes**

- form-info - Formulario + datos de contacto (teléfono, email, dirección) lado a lado.
- compact - Solo formulario. Para embedding en otras secciones.
- map-included - form-info + mapa embebido (Google Maps embed URL).

### **Config keys**

- section_title / section_subtitle
- fields (array) - \[{type, label, name, required, placeholder}\]. Types: text|email|tel|textarea|select.
- submit_text (string) - Label del botón de envío.
- success_message (string) - Mensaje post-envío.
- show_address (boolean) / address (string)
- show_phone (boolean) / phone (string)
- show_email (boolean) / email (string)
- map_embed_url (string) - URL de embed de Google Maps. Solo para map-included.

### **Notas de implementación**

- El formulario POST a un endpoint Livewire del tenant: /contact/{landing_slug}.
- Rate limiting: 5 submissions por IP por hora (middleware Laravel).
- Honeypot field oculto para spam básico.
- Las submissions se guardan en contact_submissions con referencia al landing_id.
- Notificación: email al propietario del tenant via job en queue.

## **6.3 About**

Humaniza el producto o empresa. Aumenta confianza especialmente en productos de alto ticket o servicios profesionales.

### **Variantes**

- image-left - Imagen del equipo/empresa + texto descriptivo.
- image-right - Variante espejo de image-left.
- story - Solo texto, formateado como narrativa. Sin imagen.
- team-intro - Grid de miembros del equipo con foto, nombre y rol.

### **Config keys**

- section_title / section_subtitle
- description (string) - Texto principal de la sección.
- image_url / image_alt - Para variantes con imagen.
- metrics (array) - \[{value, label}\]. Métricas destacadas. Ej: "10 años de experiencia".
- show_cta (boolean) / cta_text / cta_url
- team_members (array) - \[{name, role, bio, avatar_url}\]. Solo para team-intro.

### **Notas de implementación**

- team-intro: grid auto-fit de 3-4 columnas según cantidad de miembros.
- metrics: renderizados como números grandes con label pequeño. Sin animación de contador en v1.

**SPRINT 4 - Avanzado**

# **7\. Sprint 4 - Bloques Avanzados**

Objetivo: completar el catálogo v1 con bloques de apoyo visual y conversión avanzada. Al finalizar este sprint, Plinth cubre el 95% de los casos de uso documentados en el mercado objetivo.

## **7.1 Statistics**

Bloque de impacto numérico. Transmite escala y credibilidad con datos cuantitativos.

### **Variantes**

- horizontal - Fila de 3-5 métricas. Compacto.
- grid - Grid 2x2 o 2x3. Para más métricas con más espacio.
- highlighted - Una métrica grande destacada + dos secundarias.

### **Config keys**

- stats (array) - \[{value, label, prefix?, suffix?, icon?}\]
- animate_counters (boolean) - Animación de conteo al entrar en viewport.

### **Notas de implementación**

- animate_counters: usa IntersectionObserver. Anima de 0 al valor final en 1.5s. Solo en el HTML publicado, no en el builder preview.
- Prefix y suffix para simbolos de moneda (\$), porcentaje (%), unidades (k, M).
- Max 6 stats por bloque en variante grid.

## **7.2 Gallery**

Visualización de imágenes. Casos de uso: portfolio, screenshots del producto, fotos del equipo/instalaciones.

### **Variantes**

- grid - Grid uniforme de imágenes. El más predecible y limpio.
- masonry - Grid de altura variable. Para portfolios visuales.
- carousel - Una imagen a la vez, con flechas de navegación.

### **Config keys**

- images (array) - \[{url, alt, caption?}\]
- columns (integer: 2|3|4) - Solo para grid.
- show_captions (boolean)
- lightbox (boolean) - Abre imagen en modal al hacer clic.

### **Notas de implementación**

- Lightbox en v1: implementación Alpine.js propia. Sin GLightbox ni Fancybox.
- Masonry: CSS columns, no JavaScript. Limitación: el orden visual no coincide con el orden del DOM (izquierda a derecha vs arriba a abajo por columna). Aceptable en v1.
- Lazy loading habilitado para todas las imágenes de Gallery. Hero es la excepción.
- Max 24 imágenes por bloque.

## **7.3 Lead Form**

Captura directa de leads con mayor contexto que el newsletter del Footer. Puede incluir campos de calificación.

### **Variantes**

- inline - Formulario integrado en la sección, sin sidebar.
- multi-field - Formulario de varios pasos. Reduce fricción percibida.
- newsletter - Email + botón. Mínimo absoluto.

### **Config keys**

- section_title / section_subtitle
- fields (array) - Mismo schema que Contact.fields.
- submit_text / success_message
- redirect_url (string?) - Si se provee, redirige al éxito en vez de mostrar mensaje.
- show_social_proof (string?) - Texto de prueba social bajo el botón. "Únete a 2.000 usuarios".

### **Notas de implementación**

- Lead Form y Contact comparten el mismo endpoint y la misma tabla contact_submissions. Se diferencian por source_block: lead_form | contact.
- multi-field: pasos gestionados con Alpine.js. No requiere múltiples requests.

## **7.4 Trust Signals**

Logos de clientes, certificaciones y badges de seguridad. Aumenta credibilidad percibida sin texto.

### **Variantes**

- logo-strip - Fila de logos de clientes/partners. El más común.
- certifications - Badges con nombre y descripción. Para compliance y seguridad.
- badges - Iconos de confianza: SSL, GDPR, PCI-DSS, etc.

### **Config keys**

- section_title (string?) - Opcional. Ejemplos: "Usado por equipos en", "Certificaciones".
- items (array) - \[{logo_url, alt, url?}\] para logo-strip, \[{badge_url, name, description} para certifications.
- grayscale (boolean) - Logos en escala de grises. Patrón estándar para evitar conflictos de branding.
- show_hover_color (boolean) - Los logos muestran color al hover cuando grayscale: true.

### **Notas de implementación**

- grayscale implementado con CSS filter: grayscale(100%). Transition a filter: none en hover.
- El bloque no tiene CTA propio. Su propósito es exclusivamente generar confianza visual.
- Max 12 logos en logo-strip. Más de eso se convierte en ruido visual.

# **8\. Sistema de tema**

El tema define los tokens visuales aplicados globalmente a todos los bloques. Se almacena en landings.theme (JSONB).

## **8.1 Schema del tema**

{

"colors": {

"primary": "#2563EB",

"secondary": "#64748B",

"background": "#FFFFFF",

"surface": "#F8FAFC",

"text": "#0F172A",

"text_muted": "#64748B"

},

"typography": {

"font_heading": "Inter",

"font_body": "Inter",

"scale": "default" // compact | default | large

},

"shape": {

"radius": "md" // none | sm | md | lg | full

},

"spacing": {

"section_padding": "comfortable" // compact | comfortable | spacious

}

}

## **8.2 Tokens de background por bloque**

El campo styles.background de cada bloque referencia un token del tema:

- primary - Color primario del tema. Para CTAs y secciones de alto contraste.
- secondary - Color secundario. Para secciones de apoyo.
- surface - Fondo de superficie (gris muy claro). Para secciones alternas.
- dark - Negro/oscuro fijo, independiente del tema. Para Footer y CTAs finales.
- white - Blanco puro. Para secciones de contenido.

## **8.3 Fuentes disponibles en v1**

- Inter - Default. Neutro, legible, SaaS-estándar.
- Geist - Moderna, técnica. Para productos tech/developer tools.
- Lato - Amigable, profesional. Para servicios y salud.
- Montserrat - Impacto, peso. Para marcas fuertes.
- Playfair Display - Serif elegante. Para lujo y editorial.

Carga de fuentes: Google Fonts vía &lt;link&gt; en el HTML publicado. En el builder preview, precargadas.

# **9\. Registro de bloques - resumen**

Todos los bloques del catálogo v1 con sus variantes y config keys principales:

| **Bloque**    | **Variantes**                                            | **Config keys**                                                                              | **Notas técnicas**                                       |
| ------------- | -------------------------------------------------------- | -------------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| Hero          | centered, split, image-left, bg-image, fullscreen        | headline, subtitle, badge*text, button_primary*\*, button*secondary*\*, image_url, stats\[\] | order=0 siempre. Sin lazy loading en imagen.             |
| CTA           | centered, banner, split, floating                        | headline, description, button*primary*\*, button*secondary*\*, guarantee_text                | floating: fixed en publish, sticky en preview.           |
| Footer        | simple, multi-column, mega                               | logo_url, columns\[\], social_links\[\], copyright_text, legal_links\[\]                     | order=max siempre. Newsletter: captura básica.           |
| Features      | 3-columns, 4-columns, alternating-rows, icon-list, cards | section_title, features\[{icon, title, description, image_url}\]                             | Max 12 features. Íconos: nombre o URL SVG.               |
| Pricing       | cards, featured-plan, comparison-table                   | plans\[{name, price*monthly, price_annual, features\[\], cta*\*}\], show_toggle              | Toggle mensual/anual: Alpine puro.                       |
| Testimonials  | grid, carousel, single-featured                          | testimonials\[{name, role, company, quote, rating}\], show_rating, autoplay                  | Carousel: Alpine sin Swiper. Avatar: iniciales fallback. |
| FAQ           | accordion, two-columns, simple-list                      | items\[{question, answer}\], open_first, show_contact_cta                                    | JSON-LD FAQPage en publish. aria-expanded requerido.     |
| Contact       | form-info, compact, map-included                         | fields\[\], submit_text, success_message, address, phone, email, map_embed_url               | POST a endpoint Livewire. Rate limit 5/IP/hora.          |
| About         | image-left, image-right, story, team-intro               | description, image_url, metrics\[\], team_members\[\]                                        | team-intro: grid auto-fit 3-4 cols.                      |
| Statistics    | horizontal, grid, highlighted                            | stats\[{value, label, prefix, suffix}\], animate_counters                                    | IntersectionObserver para animación. Solo en publish.    |
| Gallery       | grid, masonry, carousel                                  | images\[{url, alt, caption}\], columns, lightbox                                             | Lightbox: Alpine propio. Masonry: CSS columns.           |
| Lead Form     | inline, multi-field, newsletter                          | fields\[\], success_message, redirect_url, show_social_proof                                 | Mismo endpoint que Contact. source_block diferenciador.  |
| Trust Signals | logo-strip, certifications, badges                       | items\[{logo_url, alt}\], grayscale, show_hover_color                                        | Max 12 logos. filter: grayscale CSS.                     |

# **10\. Decisiones de diseño**

## **10.1 JSONB vs tabla normalizada para bloques**

Decisión: JSONB en columna landings.blocks.

- Los bloques son configuraciones, no entidades con relaciones propias entre sí.
- La query más frecuente es "dame todos los bloques de esta landing" - un SELECT por id. No hay necesidad de JOIN.
- La estructura de config varía por tipo de bloque. Normalizar requeriría tabla por bloque o columnas nullable masivas.
- PostgreSQL JSONB tiene índices GIN disponibles si se necesita búsqueda en contenido. No se necesita en v1.
- Contraparte: si se necesita analytics de "cuántas landings usan el bloque Pricing", requiere JSON queries. Aceptable en v1.

## **10.2 Alpine como estado del editor, no Livewire**

Decisión: Alpine.js maneja el estado local del builder. Livewire persiste.

- Un editor con round-trip al servidor por cada keystroke es inusable. 200-500ms de latencia por cambio = UX rota.
- Alpine permite edición fluida en memoria. El save es una operación explícita o autosave cada 30s.
- Livewire sigue siendo el backbone del builder como página. Solo el loop de edición es Alpine.

## **10.3 HTML estático para el publish**

Decisión: el frontend público es HTML estático generado al publicar, no Livewire.

- Performance: tiempo de carga &lt; 200ms desde CDN vs &gt; 500ms para SSR con Livewire.
- Costo: cero cómputo en servidor por cada visita a la landing publicada.
- SEO: HTML estático es indexado sin problemas. Livewire requiere configuración adicional.
- Contraparte: el formulario de contacto requiere un endpoint backend activo. Se resuelve con un POST a /api/contact/{landing_id} que sí usa Laravel. El HTML estático hace fetch a ese endpoint.

## **10.4 Sin drag-and-drop complejo**

Decisión: reorder por botones ↑↓ en v1. Sin Sortable.js ni drag-and-drop.

- Livewire + Sortable en producción genera bugs de hydration difíciles de reproducir y peores de depurar.
- El 90% del uso real de un builder es agregar bloques y editar contenido, no reordenar.
- Los botones ↑↓ son accesibles por teclado y funcionan en dispositivos táctiles sin código adicional.
- Sortable puede agregarse en v2 como enhancement, sobre una base estable.

## **10.5 Sin bloques anidados**

Decisión: los bloques son planos. No hay columns-inside-columns ni tabs-inside-sections.

- Nested blocks es la fuente de la mayoría de los bugs en builders maduros (Gutenberg, Elementor).
- Implica un renderer recursivo, conflict resolution en el editor y exponencialmente más estados posibles.
- La solución real para "necesito dos columnas" es una variante específica del bloque (split, alternating-rows) no un sistema de layout genérico.

# **11\. Próximos pasos**

- Validar este documento con el equipo. Aprobar sprints y prioridades antes de escribir código.
- Definir el contrato de Blade components: convención de nombres, props interface por bloque.
- Diseñar los 5 templates base (SaaS, Corporate, Restaurant, Portfolio, Ecommerce) usando los bloques definidos aquí.
- Spike técnico: validar el pipeline de publish con un bloque Hero real antes de comprometerse con la arquitectura estática.
- Definir el sistema de íconos: ¿Heroicons, Lucide, o set custom del tema?
- Establecer proceso de QA por bloque: checklist de accesibilidad, responsive, dark mode.

─ Fin del documento ─