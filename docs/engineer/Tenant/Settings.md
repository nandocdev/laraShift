# Especificación del Módulo: Settings (Tenant)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | TENANT |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo permite a cada inquilino configurar su entorno de trabajo de manera personalizada, incluyendo su imagen de marca, preferencias regionales y configuración de correo saliente.

* **Propósito:** Ofrecer autonomía a los inquilinos para configurar su entorno SaaS según sus necesidades operativas y de marca.
* **Lo que este módulo NO hace (Non-goals):** No configura funcionalidades globales de la plataforma (esas residen en `Central/Settings`).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Todos los ajustes están almacenados en `tenant_settings`, con el correspondiente `tenant_id` que garantiza que los cambios solo afectan al inquilino actual.
* **Configuración:** La configuración es persistida en DB y cargada dinámicamente durante el ciclo de vida del request del inquilino.
* **SMTP:** El sistema permite el uso de servidor SMTP propio del cliente (BYO-SMTP) con verificación previa mediante pruebas de conexión.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Tenant Admin | Como admin, quiero personalizar el logo y colores de mi tenant. | - Carga de logo<br>- Selección de colores/presets.<br>- Actualización del branding en UI y documentos. |
| `UC-02` | Tenant Admin | Como admin, quiero configurar mi propio SMTP para correos. | - Validación de credenciales SMTP<br>- Envío de email de prueba exitoso antes de habilitar. |
| `UC-03` | Tenant Admin | Como admin, quiero configurar mi zona horaria y moneda. | - Ajuste de configuraciones regionales. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `tenant_settings` | `tenant_id`, `logo_path`, `primary_color`, `smtp_host`, `timezone` | `tenant_id` (Unique) | Acceso restringido al tenant activo |

## 5. Contratos de Acción (Actions & DTOs)
No utiliza actions específicas; la lógica está encapsulada directamente en los componentes Livewire que actualizan el modelo `TenantSetting`.

## 6. Eventos y Notificaciones (Events)
* `TenantSettingsUpdated`: Disparado al cambiar cualquier configuración.
* `TenantSmtpConfigured`: Disparado al actualizar SMTP.
* `TenantMfaRequirementChanged`: Disparado al cambiar la política de MFA del inquilino.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Configuración SMTP errónea | Test de conexión obligatorio antes de guardar como "verificado". |
| Carga de archivo de logo grande | Validación `image|max:2048` en Livewire. |

## 8. Estrategia de Pruebas
* [ ] **Branding:** Verificar que al cambiar el color primario, la landing page y PDFs reflejan el nuevo color.
* [ ] **SMTP:** Validar que la prueba de conexión realmente utiliza los parámetros introducidos.
* [ ] **Aislamiento:** Asegurar que los ajustes de configuración del Tenant A no son visibles para el Tenant B.
