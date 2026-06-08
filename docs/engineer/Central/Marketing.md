# Especificación del Módulo: Marketing (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo gestiona el sitio web de marketing público y el flujo de conversión de visitantes en nuevos inquilinos (tenants).

* **Propósito:** Mostrar los planes, características y valores de la plataforma, y convertir leads en organizaciones activas.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la lógica interna de los inquilinos ni su autenticación diaria (área privada del tenant).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** El proceso de registro (`RegisterTenant`) gestiona la creación de nuevos inquilinos en el contexto central, garantizando que el inquilino sea correctamente inicializado antes de permitir acceso.
* **Integración:** Consume servicios de `Central/Billing` para mostrar planes comerciales y `Central/Provisioning` para la creación del tenant.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Visitante | Como visitante, quiero registrarme como un nuevo inquilino. | - Completar wizard multi-paso<br>- Validación de slug único<br>- Integración con gateway de pago (si aplica)<br>- Redirección al dominio del nuevo tenant. |

## 4. Modelo de Datos (Persistencia)
Este módulo no posee tablas de persistencia propias, utiliza los modelos de `Central/Provisioning` (Tenant, Domain, etc.) y `Central/Billing` (Plan).

## 5. Contratos de Acción (Actions & DTOs)
No utiliza actions propias, delega la creación de inquilinos a `App\Modules\Central\Provisioning\Actions\CreateTenantAction`.

## 6. Eventos y Notificaciones (Events)
* Este módulo dispara el flujo de provisionamiento que emite `TenantProvisioned`.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Slug duplicado | Validación `unique:tenants,slug` en el wizard de registro. |
| Falla en pasarela de pago | `RegisterPaymentMethodAction` en `CreateTenantAction` captura errores y ejecuta rollback. |

## 8. Estrategia de Pruebas
* [ ] **Registro:** Validar el flujo completo: registro, billing y creación de base de datos/dominios para el tenant.
* [ ] **Validación:** Comprobar que los slugs reservados están correctamente bloqueados.
