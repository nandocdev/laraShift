# Especificación del Módulo: Audit (Tenant)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | TENANT |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo registra de manera inmutable las acciones críticas realizadas dentro del espacio de un inquilino, proporcionando una pista de auditoría necesaria para cumplimiento normativo y resolución de conflictos.

* **Propósito:** Registrar quién hizo qué y cuándo dentro de la cuenta del inquilino.
* **Lo que este módulo NO hace (Non-goals):** No es un sistema de análisis de datos ni un sistema de logs de nivel de sistema operativo.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Utiliza `BelongsToTenant` y `TenantScope` para asegurar que un inquilino solo pueda auditar sus propias acciones.
* **Colas (Queues):** Las tareas pesadas como la exportación de logs se gestionan mediante `ExportAuditLogsJob`.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como administrador de tenant, quiero ver el historial de acciones. | - Filtrado por usuario, acción y rango de fechas. |
| `UC-02` | Admin | Como administrador de tenant, quiero exportar logs a CSV. | - Exportación asíncrona enviada por email. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `tenant_audit_logs` | `id`, `tenant_id`, `user_id`, `action`, `metadata` | `tenant_id`, `created_at` | Filtrado por `tenant_id` |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `RecordAuditLogAction` | `action`, `resource`, `metadata` | `AuditLog` | Registra una actividad de forma segura. |

## 6. Eventos y Notificaciones (Events)
* `AuditLogExportNotification`: Notificación enviada al usuario con enlace firmado para descargar el CSV.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Volumen excesivo de logs | Implementación de rotación o archivado de logs antiguos fuera del scope del tenant. |

## 8. Estrategia de Pruebas
* [ ] **Aislamiento:** Verificar que los logs del Tenant A no son visibles para el Tenant B.
* [ ] **Audit:** Verificar que las acciones de login/logout y cambios de permisos se registran correctamente.
