# Especificación del Módulo: Infrastructure (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo contiene las utilidades transversales de infraestructura para la plataforma central. Su objetivo es proporcionar herramientas para el monitoreo de salud del sistema y la gestión inteligente de colas de trabajos en segundo plano, vital para el aislamiento de tareas entre inquilinos.

* **Propósito:** Ofrecer servicios de soporte técnico necesarios para la operación, monitoreo y enrutamiento de trabajos en entornos multi-inquilino.
* **Lo que este módulo NO hace (Non-goals):** No contiene lógica de negocio específica de usuarios o facturación.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** No gestiona datos de negocio; sus interacciones son con las tablas de infraestructura (`tenants` para resolución) y servicios de sistema (Redis, Queue).
* **Colas (Queues):** Implementa el enrutamiento dinámico de colas (`TenantQueueManager`) para garantizar el aislamiento ("Noisy Neighbor" prevention).
* **Cache:** Utilizado intensivamente en `HorizonQueueResolver` para optimizar el monitoreo de colas y evitar consultas recurrentes a la base de datos central.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | SRE/Dev | Como administrador, quiero verificar la salud de los servicios críticos. | - Comprobación de BD, Redis y Queue.<br>- Respuesta 200 si todo es correcto, 503 si hay fallos. |
| `UC-02` | Sistema | Como sistema, quiero enrutar jobs a colas específicas por tenant. | - Enrutamiento dinámico `tenant.{slug}.{priority}`. |
| `UC-03` | Sistema | Como sistema, quiero monitorear las colas de todos los tenants activos. | - Resolución dinámica de colas para Horizon sin impacto en rendimiento. |

## 4. Modelo de Datos (Persistencia)
Este módulo no posee tablas de persistencia propias, utiliza modelos de otros módulos (`Central/Provisioning/Models/Tenant`).

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `HorizonQueueResolver::resolve` | N/A | `array` | Resuelve las colas activas para monitoreo de Horizon. |
| `TenantQueueManager::dispatch` | `Tenant`, `Job`, `string` | `void` | Despacha un job a la cola aislada del tenant. |

## 6. Eventos y Notificaciones (Events)
* Este módulo no emite eventos de negocio, pero es consumido por los procesos de infraestructura del sistema.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Redis caído | `HealthCheckController` reporta estado degradado. `TenantQueueManager` continúa operando aunque la performance puede degradarse. |
| Base de datos inaccesible | `HealthCheckController` reporta fallo. |
| Cola profunda (>1000 jobs) | `HealthCheckController` reporta estado `warn`. |

## 8. Estrategia de Pruebas
* [ ] **Salud del Sistema:** Validar que `HealthCheckController` devuelve 200 en estado saludable y 503 cuando un servicio falla.
* [ ] **Enrutamiento de Colas:** Verificar que los jobs son despachados a la cola `tenant.{slug}.default` o `.low` según el estado del tenant.
* [ ] **Resolución de Colas:** Validar que `HorizonQueueResolver` incluye las colas de tenants activos y excluye a los inactivos (o según la lógica de negocio).
