# Especificación del Módulo: Settings (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo centraliza la configuración de alto nivel de la plataforma SaaS, permitiendo a los administradores ajustar aspectos visuales y técnicos sin necesidad de despliegues de código.

* **Propósito:** Configurar aspectos globales como el nombre de la plataforma, colores de marca y URL del logo, que son utilizados para la generación dinámica de la interfaz y documentos (PDFs).
* **Lo que este módulo NO hace (Non-goals):** No gestiona configuraciones específicas de inquilinos (esas residen en `Tenant/Settings`).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Utiliza la tabla `central_settings` para almacenar pares clave-valor. Es totalmente global a la plataforma.
* **Caché:** Todas las configuraciones son cacheadas mediante `Cache::rememberForever` con invalidación automática cuando se actualiza un registro.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como administrador, quiero actualizar el branding de la plataforma. | - Actualizar nombre, color primario, logo.<br>- Previsualización en tiempo real de cambios. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `central_settings` | `key` (PK), `value`, `type` | N/A | N/A (Global) |

## 5. Contratos de Acción (Actions & DTOs)
No utiliza acciones específicas (la lógica está contenida en `CentralBranding` Support class y el componente Livewire).

## 6. Eventos y Notificaciones (Events)
No emite eventos.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Fallo al guardar configuración | Validación de tipos (`castValue`) para evitar valores inválidos en DB. |

## 8. Estrategia de Pruebas
* [ ] **Caché:** Verificar que al actualizar una configuración, la caché se invalida y los cambios se reflejan inmediatamente.
* [ ] **Validación:** Validar formatos de color HEX y URLs de logo.
