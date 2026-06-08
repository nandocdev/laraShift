# Especificación del Módulo: Features (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo gestiona el catálogo global de funcionalidades (features) y permite aplicar reglas específicas de acceso (overrides) para cada inquilino (tenant). Es la base del modelo de suscripción basado en características.

* **Propósito:** Definir funcionalidades granulares que pueden activarse/desactivarse según el plan contratado o mediante excepciones manuales (overrides) aplicadas por un administrador.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la lógica de facturación de los planes (eso reside en `Billing`), solo la lógica de acceso a funcionalidades.

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Las definiciones de funcionalidades son globales (`features`). Los overrides son específicos por inquilino (`tenant_feature_overrides`).
* **Cache:** Se utiliza `Cache::rememberForever` para resolver el conjunto efectivo de características de un inquilino, asegurando alta velocidad de acceso.
* **Integración:** El trait `HasFeatures` permite a cualquier modelo de inquilino verificar acceso de forma sencilla.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como administrador, quiero crear nuevas funcionalidades. | - Definir key técnica única<br>- Asignar a un módulo<br>- Activar/desactivar. |
| `UC-02` | Admin | Como administrador, quiero aplicar un override a un tenant. | - Grant (Allow) o Revoke (Deny) acceso<br>- Definir fecha de expiración opcional<br>- Registro de auditoría. |
| `UC-03` | Tenant | Como inquilino, quiero verificar mi acceso a una feature. | - Resolución jerárquica (Override > Plan Base). |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `features` | `id`, `key`, `name`, `is_active` | `key` (Unique) | N/A |
| `tenant_feature_overrides` | `id`, `tenant_id`, `feature_id`, `type` | `tenant_id`, `feature_id` | N/A (Admin context) |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `ApplyTenantFeatureOverrideAction` | `Tenant`, `key`, `type`, `reason` | `TenantFeatureOverride` | Crea/actualiza un override e invalida caché. |
| `ResolveTenantFeaturesAction` | `Tenant`, `force` | `array` | Resuelve features efectivas con jerarquía. |

## 6. Eventos y Notificaciones (Events)
* `tenant_feature_override_applied`: Registro de actividad.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Contaminación de caché en tests | Invalidation forzada cuando `runningUnitTests()` es true. |
| Alta carga de queries | Caching persistente (`rememberForever`) y uso de `with('feature')`. |

## 8. Estrategia de Pruebas
* [ ] **Jerarquía de resolución:** Validar que `deny` prevalece sobre `allow` y el plan base.
* [ ] **Invalidación de Caché:** Verificar que al aplicar un override, la caché del inquilino se limpia inmediatamente.
* [ ] **Aislamiento:** Verificar que los overrides de un inquilino no afectan a otros.
