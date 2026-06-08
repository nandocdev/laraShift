# Especificación del Módulo: Identity (Tenant)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | TENANT |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo es el pilar central de seguridad y gestión de usuarios dentro de un inquilino. Administra quién puede acceder, qué puede hacer (RBAC) y cómo se asegura el acceso (MFA/API Keys).

* **Propósito:** Proporcionar un sistema completo de identidad, acceso y autenticación para los miembros de un inquilino.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la identidad central (usuarios que administran la plataforma SaaS).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Todos los modelos (User, Role, ApiKey) implementan `BelongsToTenant` para garantizar el aislamiento estricto.
* **Seguridad:** Implementa MFA obligatorio (cuando se configura) y middleware para verificar el estado activo del usuario en tiempo real.
* **Permisos:** Utiliza `spatie/laravel-permission` configurado con `setPermissionsTeamId` para el aislamiento de roles.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como admin, quiero invitar nuevos usuarios. | - Envío de token de invitación expirado en 48h. |
| `UC-02` | Admin | Como admin, quiero definir roles personalizados. | - Asignación granular de permisos. |
| `UC-03` | User | Como usuario, quiero asegurar mi cuenta con 2FA. | - Soporte TOTP y códigos de recuperación. |
| `UC-04` | User | Como usuario, quiero generar API Keys. | - Scopes definidos, hash almacenado. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `users` | `id`, `tenant_id`, `email`, `status` | `tenant_id` | Acceso restringido al tenant activo |
| `roles` | `id`, `tenant_id`, `name`, `is_system` | `tenant_id`, `name` | Acceso restringido al tenant activo |
| `tenant_api_keys` | `id`, `tenant_id`, `key_hash`, `scopes` | `tenant_id` | Acceso restringido al tenant activo |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `AcceptInvitationAction` | `token`, `name`, `password` | `User` | Valida token y crea/activa usuario. |
| `EnrollTenant2FAAction` | `User` | `bool` | Configura TOTP MFA. |
| `GenerateApiKeyAction` | `name`, `scopes` | `array` | Genera nuevo API Key y retorna el valor plano (una vez). |
| `SendInvitationAction` | `email`, `role`, `inviter` | `Invitation` | Envía invitación verificando cuotas. |

## 6. Eventos y Notificaciones (Events)
* `TenantUserInvited`, `TenantUserJoined`, `TenantRoleUpdated`, `TenantApiKeyCreated`.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Escala de privilegios | Middleware `EnsureUserBelongsToTenant` + RBAC estricto. |
| Usuario desactivado pero con sesión activa | Middleware `EnsureUserIsActive` invalida sesión en cada request. |

## 8. Estrategia de Pruebas
* [ ] **Aislamiento:** Verificar que un usuario del Tenant A no puede acceder a recursos del Tenant B.
* [ ] **RBAC:** Validar que los permisos se aplican correctamente en las rutas.
* [ ] **Ciclo de Invitación:** Validar expiración de tokens y creación de usuarios.
