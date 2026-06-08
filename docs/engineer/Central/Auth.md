# Especificación del Módulo: Auth (Central)

**Metainformación**

| Campo | Valor |
| --- | --- |
| **Responsable** | [@nandocdev / Equipo Core] |
| **Contexto (Bounded Context)** | CENTRAL |
| **Estado** | Implementado |
| **Fecha de Creación** | 2026-06-08 |

---

## 1. Visión General y Objetivo de Negocio
Este módulo gestiona la autenticación, seguridad y gestión de sesiones para los administradores y usuarios de la plataforma central (área administrativa de LaraShift). Asegura que el acceso sea seguro, auditado y cumpla con las políticas de concurrencia.

* **Propósito:** Proporcionar un sistema de autenticación seguro, multifacta (MFA) y auditado para los usuarios administrativos de la plataforma.
* **Lo que este módulo NO hace (Non-goals):** No gestiona la identidad de los usuarios finales de los inquilinos (esa lógica reside en `Tenant/Identity`).

## 2. Restricciones Arquitectónicas y Aislamiento
* **Aislamiento de Datos:** Las tablas (`central_users`, `central_sessions`, `central_2fa`) residen en la base de datos central. No requieren `tenant_id` ya que son globales.
* **Colas (Queues):** Las notificaciones de seguridad utilizan las colas predeterminadas de la plataforma.
* **Sesiones:** Implementación basada en base de datos (`central_sessions`) para permitir auditoría, concurrencia limitada y revocación remota.

## 3. Casos de Uso (Use Cases) y Criterios de Aceptación

| ID | Persona | Historia | Criterios de Aceptación (Acceptance Criteria) |
| --- | --- | --- | --- |
| `UC-01` | Admin | Como administrador, quiero iniciar sesión de forma segura. | - Validación de credenciales<br>- Desafío MFA si está habilitado<br>- Registro de sesión y auditoría de log. |
| `UC-02` | Admin | Como administrador, quiero habilitar 2FA. | - Generación de secreto y QR<br>- Verificación de código inicial<br>- Generación de códigos de recuperación. |
| `UC-03` | Admin | Como administrador, quiero limitar mis sesiones activas. | - Revocación automática de sesiones antiguas al exceder el límite definido. |

## 4. Modelo de Datos (Persistencia)

| Tabla | Campos Principales | Índices Necesarios | Reglas RLS |
| --- | --- | --- | --- |
| `central_users` | `id`, `email`, `password`, `is_global_admin` | `email` (Unique) | N/A |
| `central_sessions` | `id`, `user_id`, `session_id`, `revoked_at` | `session_id`, `user_id` | N/A |
| `central_2fa` | `id`, `user_id`, `secret` (Encrypted) | `user_id` (Unique) | N/A |

## 5. Contratos de Acción (Actions & DTOs)

| Acción (Clase PHP) | DTO de Entrada (Input) | Retorno (Output) | Descripción |
| --- | --- | --- | --- |
| `LoginCentralUserAction` | `LoginData` | `string` | Valida credenciales, checkea MFA, inicializa sesión. |
| `EnrollCentral2FAAction` | `CentralUser` | `array` | Inicia/confirma el flujo de MFA (TOTP). |
| `LogoutCentralUserAction` | N/A | `void` | Finaliza sesión y revoca registros en DB. |
| `RevokeOldestSessionAction` | `CentralUser`, `limit` | `void` | Revoca sesiones antiguas al exceder límite de concurrencia. |

## 6. Eventos y Notificaciones (Events)
* `central_user_login_failed`: Registro de actividad de seguridad.
* `central_user_logged_in`: Auditoría exitosa.
* `2fa_enrolled`: Registro de actividad de seguridad.

## 7. Casos Extremos y Riesgos (Edge Cases)

| Escenario de Falla | Mitigación / Respuesta del Sistema |
| --- | --- |
| Sesión terminada en DB | `ValidateCentralSession` fuerza logout y redirige a login. |
| Intento de concurrencia excesiva | `RevokeOldestSessionAction` purga sesiones antiguas automáticamente. |
| Falla en gateway 2FA | Validación robusta de códigos TOTP mediante `PragmaRX\Google2FA`. |

## 8. Estrategia de Pruebas
* [ ] **Autenticación:** Validar el flujo normal, MFA y login fallido.
* [ ] **Concurrencia:** Asegurar que `RevokeOldestSessionAction` respeta el límite configurado.
* [ ] **Integridad de sesión:** Verificar que `ValidateCentralSession` invalida sesiones marcadas como revocadas en la base de datos.
