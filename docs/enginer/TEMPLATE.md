# Especificación del Módulo: [Nombre del Módulo]

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@usuario / Equipo] |
| **Contexto (Bounded Context)** | [CENTRAL / TENANT] |
| **Estado** | [Borrador / Revisión / Aprobado / Implementado] |
| **Fecha de Creación** | YYYY-MM-DD |

---

## 1. Visión General y Objetivo de Negocio
*(Describa de forma sencilla qué problema de negocio resuelve este módulo. Evite la jerga técnica. ¿Cuál es el valor para el cliente o para la operación?)*

* **Propósito:** ...
* **Lo que este módulo NO hace (Non-goals):** *(Defina claramente los límites para evitar el crecimiento descontrolado del alcance)*

## 2. Restricciones Arquitectónicas y Aislamiento
*(Obligatorio para LaraShift. Defina cómo este módulo respeta la infraestructura)*

* **Aislamiento de Datos:** ¿Las tablas utilizan `tenant_id` y RLS (Row-Level Security)?
* **Colas (Queues):** ¿Los jobs son *Tenant-Aware*?
* **Almacenamiento (Storage):** ¿Los archivos generados se almacenan bajo el namespace `tenant_{id}/`?
* **Cache/Cuotas:** ¿El módulo interactúa con límites globales mediante Redis?

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación
*(Liste las principales historias de usuario y sus criterios de aceptación. Concéntrese en las reglas de negocio, no en la interfaz de usuario.)*

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | [Ej: Tenant Admin] | Como [Persona], quiero [Acción] para [Valor]. | - Regla 1<br>- Regla 2<br>- Retorna 404 en caso de fallo de alcance |
| `UC-02` | ... | ... | ... |

## 4. Modelo de Datos (Persistencia)
*(Liste las tablas propuestas. Regla: Si pertenece al contexto TENANT, debe incluir `tenant_id` e índices compuestos apropiados)*

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `[nombre_tabla]` | `id` (UUID), `tenant_id` (UUID), `[campo]` | `(tenant_id, created_at)` | Acceso restringido al tenant activo |

## 5. Contratos de Acción (Actions & DTOs)
*(En LaraShift, la lógica de negocio vive en Actions, no en Controllers. Defina la interfaz pública del módulo)*

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `Create[X]Action` | `[X]Data` | `Model` | Crea [X] dentro de una transacción (`DB::transaction`). |
| `Process[X]Action` | `Process[X]Data` | `void` | Ejecuta un procesamiento aislado. |

## 6. Eventos y Notificaciones (Events)
*(¿Qué eventos emite este módulo para el resto del sistema? Recuerde: use eventos únicamente cuando exista un desacoplamiento real.)*

* `[Modulo][Accion]Completed`: Disparado cuando... Payload: `[tenant_id, resource_id]`
* `[Modulo][Accion]Failed`: Disparado cuando... Payload: `[reason]`

## 7. Casos Extremos y Riesgos (Edge Cases)
*(¿Qué ocurre cuando las cosas salen mal en producción?)*

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Violación de límite de cuota (`QuotaExceeded`) | Retorna 429 o `QuotaExceededException`. |
| Intento de acceso cross-tenant | Retorna `404 Not Found` y genera un registro en `audit_logs`. |
| Falla temporal de base de datos / deadlock | Transacción con reintento automático (Action en rollback). |
| Ejecución duplicada (Idempotencia) | Validación mediante `idempotency_key` (ej.: Webhooks). |

## 8. Estrategia de Pruebas
*(Pruebas obligatorias antes de aceptar el Pull Request)*

* [ ] **Pruebas de Aislamiento:** Garantizar que el Tenant A no puede leer ni modificar datos del Tenant B (Resultado esperado: HTTP 404).
* [ ] **Pruebas de Límites/Cuotas:** Validar el bloqueo cuando se alcanza el límite del plan.
* [ ] **Pruebas Transaccionales:** Simular una falla durante `Create[X]Action` y garantizar rollback completo.
* [ ] **Pruebas de Autenticación:** El acceso sin permisos debe retornar el código HTTP correcto y evitar filtraciones de información.