# Especificación del Módulo: Provisioning (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo gestiona el ciclo de vida completo de los inquilinos (tenants) dentro de la plataforma SaaS, desde su creación (provisionamiento) hasta su archivado o borrado permanente.

* **Propósito:** Automatizar la creación de nuevos entornos de inquilinos, incluyendo reserva de dominios, configuración de base de datos y despliegue de usuarios iniciales.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la lógica de negocio diaria del inquilino, solo su existencia y estado operativo.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Utiliza `Stancl/Tenancy` para gestionar la separación de esquemas/bases de datos.
* **Integración:** El proceso de provisionamiento es una orquestación que llama a otros módulos (Auth para admin, Billing para suscripción inicial).
* **Atomicidad:** Implementa un sistema de registros (`ProvisioningLog`) y acciones de rollback para asegurar un estado consistente incluso si el provisionamiento falla parcialmente.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como administrador, quiero provisionar un nuevo tenant. | - Creación de registros tenant y dominio<br>- Creación de usuario administrador inicial<br>- Configuración de billing inicial (opcional). |
| `UC-02` | Admin | Como administrador, quiero gestionar el estado de un tenant (activo, suspendido, archivado). | - Bloqueo de acceso en mantenimiento<br>- Suspensión por falta de pago. |
| `UC-03` | Admin | Como administrador, quiero eliminar permanentemente un tenant. | - Borrado de archivos, bases de datos y registros en background (purge). |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `tenants` | `id`, `slug`, `name`, `status`, `maintenance_mode` | `slug` (Unique) | N/A (Global) |
| `domains` | `id`, `tenant_id`, `domain` | `domain` | N/A |
| `provisioning_logs`| `id`, `tenant_id`, `step`, `status` | `tenant_id` | N/A |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `CreateTenantAction` | `CreateTenantData` | `Tenant` | Provisionamiento atómico de nuevo tenant. |
| `DeleteTenantAction` | `Tenant`, `bool` | `void` | Borrado suave o duro (background purge). |
| `ArchiveTenantAction`| `Tenant` | `void` | Marca tenant como archivado/solo lectura. |
| `SwitchMaintenanceModeAction`| `Tenant`, `bool` | `void` | Cambia estado de mantenimiento. |

## 6. Eventos y Notificaciones (Events)
* `TenantProvisioned`: Disparado al crear el inquilino para inicializar roles y usuario admin.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Falla parcial en provisionamiento | `handleFailure` ejecuta rollback de los pasos completados (`domains`, logs). |
| Slug de tenant reservado | Validación contra `ReservedSlugs::$list` en UI y Action. |

## 8. Estrategia de Pruebas
* [ ] **Atomicidad:** Simular error en paso 3 y verificar rollback del paso 1.
* [ ] **Aislamiento:** Verificar que los tenants creados tienen correctamente configurado su dominio.
* [ ] **Estados:** Validar que el mantenimiento y read-only mode funcionan correctamente mediante middleware.
