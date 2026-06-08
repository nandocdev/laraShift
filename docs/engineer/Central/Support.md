# Especificación del Módulo: Support (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo proporciona herramientas administrativas para gestionar el soporte al cliente, auditoría de acceso e impersonación, y comunicación masiva hacia los inquilinos.

* **Propósito:** Ofrecer herramientas para que el equipo de soporte pueda asistir eficazmente a los inquilinos, incluyendo la impersonación auditada y el envío de notificaciones críticas.
* **Lo que este módulo NO hace (Non-goals):** No gestiona tickets de soporte (ej. Zendesk), sino que centraliza la operación técnica administrativa.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Las notas de soporte (`support_notes`) y sesiones de impersonación (`support_sessions`) están ligadas a `tenant_id`.
* **Seguridad:** La impersonación es una función crítica; se audita exhaustivamente mediante el middleware `AuditImpersonationActions`.
* **Colas (Queues):** Los broadcasts pueden procesarse en segundo plano (chunked).

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Operador | Como operador, quiero impersonar a un tenant para depurar errores. | - Registro de motivo auditado<br>- Generación de token de un solo uso<br>- Auditoría de todas las acciones mientras dure la sesión. |
| `UC-02` | Admin | Como administrador, quiero enviar broadcasts (email/banner). | - Selección de audiencia (todos/plan/status)<br>- Registro de histórico de mensajes enviados. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `support_notes` | `id`, `tenant_id`, `author_id`, `content` | `tenant_id` | N/A (Admin context) |
| `support_sessions` | `id`, `tenant_id`, `operator_id`, `token` | `tenant_id`, `token` | N/A (Admin context) |
| `broadcasts` | `id`, `title`, `body`, `sent_at` | N/A | N/A (Global) |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `CreateSupportNoteAction` | `Tenant`, `content` | `SupportNote` | Crea una nota de soporte para el tenant. |
| `ImpersonateTenantAction` | `Tenant`, `reason` | `string` (URL) | Inicia sesión de impersonación auditada. |
| `SendBroadcastAction` | `title`, `body`, `filter` | `Broadcast` | Envía notificaciones masivas. |

## 6. Eventos y Notificaciones (Events)
* `BroadcastNotification`: Notificación por correo.
* `ImpersonationEndedNotification`: Notificación al tenant al finalizar la impersonación.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Abuso de impersonación | Auditoría obligatoria, registro de razón y límite de tiempo. |
| Impersonación cruzada | Token de un solo uso limitado en tiempo (`expires_at`). |

## 8. Estrategia de Pruebas
* [ ] **Seguridad Impersonación:** Verificar que un operador no puede impersonar sin una razón. Verificar que el token es de un solo uso.
* [ ] **Auditoría:** Verificar que las actividades realizadas durante la impersonación contienen el contexto del impersonador.
* [ ] **Broadcasts:** Validar el filtrado de destinatarios según plan y estado del tenant.
