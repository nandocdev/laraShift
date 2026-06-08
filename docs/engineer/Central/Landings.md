# Especificación del Módulo: Landings (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo permite a los inquilinos crear, gestionar y publicar páginas de aterrizaje (landing pages) personalizadas sin necesidad de conocimientos técnicos avanzados.

* **Propósito:** Ofrecer un constructor visual basado en bloques que los inquilinos pueden utilizar para promocionar sus servicios.
* **Lo que este módulo NO hace (Non-goals):** No gestiona el hosting de dominios raíz, solo la renderización del contenido publicado.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Utiliza `tenant_id` para garantizar que cada landing pertenezca exclusivamente a un inquilino.
* **Almacenamiento (Storage):** Los assets cargados para landings se gestionan a través del sistema de archivos de Laravel, asegurando el aislamiento por inquilino.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Tenant | Como inquilino, quiero crear una landing page con bloques predefinidos. | - Selección de tipo de bloque (hero, cta, features, etc.)<br>- Configuración de estilos y contenido. |
| `UC-02` | Tenant | Como inquilino, quiero publicar mi landing para hacerla pública. | - Generación de HTML estático y guardado de versión (snapshot). |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `landings` | `id`, `tenant_id`, `slug`, `blocks` (JSON), `status` | `tenant_id`, `slug` | Acceso restringido al tenant activo |
| `landing_versions` | `id`, `landing_id`, `blocks_snapshot` | `landing_id` | Acceso restringido al tenant activo |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `PublishLandingAction` | `Landing`, `publisherId` | `Landing` | Renderiza el HTML y crea una versión snapshot. |
| `RenderLandingAction` | `Landing` | `string` (HTML) | Renderiza los bloques usando Blade. |

## 6. Eventos y Notificaciones (Events)
* `landing-saved`: Evento de frontend.
* `landing-published`: Evento de frontend.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Error al renderizar bloques | El sistema captura excepciones y mantiene el último estado funcional. |

## 8. Estrategia de Pruebas
* [ ] **Publicación:** Verificar que el HTML renderizado coincide con la configuración de bloques.
* [ ] **Aislamiento:** Validar que una landing no es accesible desde el dominio de otro inquilino.
