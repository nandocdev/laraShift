# Auditoría — Tenant/Access

> Fecha: 2026-08-28
> Estado: 🔴 Requiere intervención

## 1. Resumen ejecutivo

El módulo `Tenant/Access` se encarga de gestionar la identidad, roles, permisos, claves API, invitaciones y la autenticación multifactor (MFA) dentro de cada entorno (Tenant). 

A nivel general, el módulo tiene una separación de responsabilidades adecuada (Models, Actions, Livewire, API Controllers) y el diseño de la base de datos se beneficia de la estrategia Single DB + Postgres RLS. Sin embargo, **el módulo presenta vulnerabilidades críticas de seguridad** en la capa de interfaz de Livewire (IDOR/Escalada de privilegios vertical) debido a la ausencia de comprobaciones de autorización, lo que permite a cualquier usuario de un tenant administrar roles y revocar/crear API Keys. También presenta deficiencias de performance por consultas N+1 en middlewares que afectan toda la aplicación.

## 2. Alcance

La auditoría ha analizado:
- Componentes Livewire (`RoleManagement`, `ManageApiKeys`, etc.)
- Controladores API (`IdentityApiController`)
- Middlewares de autorización y validación (`EnforceTenantMfa`, `EnsureUserBelongsToTenant`)
- Actions de lógica de negocio (`GenerateApiKey`, etc.)
- Modelos Eloquent (`User`, `Role`, `ApiKey`, `Invitation`)
- Reglas de enrutamiento web y API
- Tests asociados.

## 3. Arquitectura actual

El módulo sigue la convención de `Monolito Modular` definida. Utiliza una arquitectura basada en:
- **Presentation:** Rutas web/auth, Livewire para la UI del Dashboard, y endpoints API JSON (protegidos por Auth/MFA).
- **Application:** Clases `Action` con el patrón `final readonly class` (ej. `GenerateApiKey`, `SendInvitation`) para ejecutar operaciones de negocio sin acoplar la UI a Eloquent.
- **Domain:** Modelos Eloquent utilizando los Traits de `App\Modules\Platform\Tenancy` (`BelongsToTenant`) y `Spatie\Permission\Traits\HasRoles`.
- **Persistencia:** PostgreSQL RLS para el aislamiento lógico de los inquilinos.

## 4. Dependencias

- `Platform/Tenancy` para el contexto de inquilino (RLS y middleware `ApplyTenantRateLimits`, etc.).
- `Platform/Security` para el manejo de hash en API Keys (`ApiKeyHasher`).
- `Tenant/Compliance` para el registro de logs de auditoría (AuditLogs).
- `Spatie/Laravel-Permission` para la gestión de roles.
- `Laravel Fortify` & `Laravel Passkeys` para autenticación.

## 5. Health Score

| Área         | Estado             |
| ------------ | ------------------ |
| Arquitectura | 🟢                 |
| Backend      | 🟡                 |
| Database     | 🟡                 |
| Frontend     | 🔴                 |
| Security     | 🔴                 |
| Testing      | 🟡                 |
| Performance  | 🟡                 |
| Operabilidad | 🟢                 |

**Estado general:** 🔴 Requiere intervención (Debido a vulnerabilidad de Autorización P0)

## 6. Hallazgos

### [P0] Ausencia de validación de políticas (Autorización) en componentes Livewire

**Categoría:** Security

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Livewire/RoleManagement.php`
`app/Modules/Tenant/Access/Interface/Livewire/ManageApiKeys.php`

**Problema:**
Los métodos públicos en Livewire (`create`, `update`, `delete` de roles, y `generate`, `revoke` de API keys) y sus rutas asociadas en `web_auth.php` no tienen protección de middleware de autorización (`can:`) ni llamadas a `$this->authorize()`.

**Evidencia:**
Cualquier usuario autenticado en el Tenant puede navegar a las rutas o despachar solicitudes XHR de Livewire para cambiar roles o crear API keys. RLS previene que afecten a otros tenants, pero permite Escalada de Privilegios dentro de su propia organización al no validar permisos como `roles:manage` o `identity:write`. El test `TenantRoleHardeningTest.php` confirma esto al permitir a un usuario raso con rol "editor" disparar el método `delete` del componente y alcanzar el chequeo de 409 Conflict.

**Impacto:**
Escalada de privilegios vertical. Un usuario sin privilegios puede reasignarse roles administrativos (o a otros) o generar API Keys administrativas.

**Recomendación:**
Añadir el trait `Illuminate\Foundation\Auth\Access\AuthorizesRequests` en los componentes Livewire y utilizar `$this->authorize('roles:manage')` en la creación, actualización y borrado. Opcionalmente, agregar middleware de autorización a las rutas respectivas en `web_auth.php`.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Regla única de validación sin scoping de Tenant

**Categoría:** Integrity / Database

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Http/Controllers/Api/IdentityApiController.php:152`
`app/Modules/Tenant/Access/Interface/Livewire/RoleManagement.php:56`

**Problema:**
La validación `unique:roles,name` comprueba la singularidad del nombre del rol en toda la tabla globalmente.

**Evidencia:**
Como la tabla de base de datos es compartida entre inquilinos (Single DB), si el Tenant A crea un rol llamado "Contabilidad", la regla `unique:roles,name` bloqueará al Tenant B al intentar crear un rol con el mismo nombre. 

**Impacto:**
Filtración cruzada indirecta (Saber qué nombres de roles usan otros clientes) y Denegación de Servicio (Dos) en la creación de roles comunes.

**Recomendación:**
Usar Rule constraints: `Rule::unique('roles', 'name')->where('tenant_id', tenant('id'))`.

**Complejidad:** Baja
**Prioridad:** Próximo sprint

---

### [P1] N+1 Severo (Consultas recurrentes en Middleware Global)

**Categoría:** Performance

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Http/Middleware/EnforceTenantMfa.php:33`

**Problema:**
El middleware de cumplimiento de MFA realiza una consulta Eloquent a `TenantSetting` en cada solicitud web de un usuario autenticado.

**Evidencia:**
El código ejecuta `$settings = TenantSetting::where('tenant_id', tenant('id'))->first();`. Al ser un middleware asociado al grupo de rutas del Tenant, esto carga un Select en la base de datos por cada página que visite cualquier usuario, sin utilizar ningún mecanismo de memoria en caché.

**Impacto:**
Sobrecarga masiva e innecesaria de la base de datos en aplicaciones de alto tráfico.

**Recomendación:**
Utilizar la caché de Laravel para retener esta configuración: `Cache::remember("tenant:{tenant('id')}:settings", now()->addHour(), fn() => ...)`. Alternativamente, depender del `tenant()` object para cargar configuraciones o emitir eventos al modificar el MFA de los settings del inquilino.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P2] Falta de paginación en endpoints API de la lista de usuarios y roles

**Categoría:** Backend / Performance

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Http/Controllers/Api/IdentityApiController.php:26`

**Problema:**
Los endpoints de listado de miembros (`listMembers`) e invitaciones (`listInvitations`) usan `->get()` para cargar todos los registros.

**Evidencia:**
`User::with('roles')->latest()->get()` traerá toda la tabla a memoria.

**Impacto:**
En Tenants de tamaño empresarial (miles de empleados), este endpoint sufrirá latencias de serialización muy altas, consumirá mucha memoria y derivará en Timeouts PHP (OOM).

**Recomendación:**
Sustituir `->get()` por `->paginate(50)` u otro cursor de paginación adecuado, o incluir soporte explícito para queries dinámicas.

**Complejidad:** Media
**Prioridad:** Backlog

---

### [P2] Inserción de base de datos en `mount()` de Livewire

**Categoría:** Architecture / Frontend

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Livewire/RoleManagement.php:48`

**Problema:**
El ciclo de vida de Livewire inicializa o crea permisos en cada visita de página usando `Permission::firstOrCreate()`.

**Evidencia:**
En cada render del componente para cualquier usuario, Laravel ejecuta operaciones SELECT / INSERT si los permisos básicos no están poblados.

**Impacto:**
Acoplamiento de infraestructura estática (Seeders de Permisos) con el ciclo de vida del Frontend, generando carga extra no deseable a la base de datos.

**Recomendación:**
Desplazar esta lógica a un `TenantSeeder` o a un Listener que responda al evento de inicialización de un inquilino nuevo (`TenantProvisioned`), eliminando este bloque del componente.

**Complejidad:** Baja
**Prioridad:** Próximo sprint

---

### [P2] Validación `exists` expuesta a otros Tenants

**Categoría:** Security / Backend

**Ubicación:**
`app/Modules/Tenant/Access/Interface/Http/Controllers/Api/IdentityApiController.php:129`

**Problema:**
El endpoint `updateMemberRole` valida la existencia del rol utilizando `exists:roles,name` sin filtrarlo por el `tenant_id`.

**Evidencia:**
El validador aprueba la validación si se pasa un rol que pertenece al Tenant X pero el atacante está en el Tenant Y. Aunque internamente `syncRoles` utiliza el guard y el Context ID correcto (fallando o siendo inocuo después), la validación en la capa HTTP debería atraparlo por integridad.

**Impacto:**
No causa exposición de datos críticos gracias a RLS, pero ensucia la capa de servicio y provoca excepciones de lógica o estados huérfanos.

**Recomendación:**
Scoping estricto: `Rule::exists('roles', 'name')->where('tenant_id', tenant('id'))`.

**Complejidad:** Baja
**Prioridad:** Próximo sprint

---

## 7. Matriz de riesgos

| ID   | Severidad | Categoría | Hallazgo | Impacto | Complejidad | Prioridad      |
| ---- | --------- | --------- | -------- | ------- | ----------- | -------------- |
| M001 | P0        | Security  | Ausencia de autorización en Livewire | Alto | Baja | Inmediata |
| M002 | P1        | Integrity | Validación única sin scoping de Tenant | Medio | Baja | Próximo sprint |
| M003 | P1        | Performance| N+1 en middleware de MFA | Alto | Baja | Inmediata |
| M004 | P2        | Backend | Falta de paginación API Listados | Medio | Media | Backlog |
| M005 | P2        | Frontend | `firstOrCreate` de DB en mount Livewire | Bajo | Baja | Próximo sprint |
| M006 | P2        | Security | Validación `exists` sin scope de Tenant | Bajo | Baja | Próximo sprint |

## 8. Ruta de trabajo

1. **M001 — Bloquear Endpoints y Actions de Livewire (Autorización)**
   - Dependencias: ninguna
   - Esfuerzo: Bajo
   - Riesgo: Alto
   - Resultado: Evitar escalada de privilegios y ataques de manipulación directa de parámetros Livewire (IDOR vertical).

2. **M003 — Optimizar Query Middleware de Configuración Tenant (MFA)**
   - Dependencias: ninguna
   - Esfuerzo: Bajo
   - Riesgo: Bajo
   - Resultado: Aligerar la latencia base de TODAS las requests web de los tenants.

3. **M002 & M006 — Corregir reglas de Validación de Roles (Integridad Multitenant)**
   - Dependencias: ninguna
   - Esfuerzo: Bajo
   - Riesgo: Bajo
   - Resultado: Solucionar bloqueos de creación de nombres de roles (DoS entre tenants) y prevenir validaciones cruzadas.

4. **M005 — Mover creación de Permisos al flujo de Provisionamiento**
   - Dependencias: ninguna
   - Esfuerzo: Medio
   - Riesgo: Medio
   - Resultado: Remover lógica de negocio y persistencia costosa en la carga de componentes UI.

5. **M004 — Interfaz Paginada**
   - Dependencias: ninguna
   - Esfuerzo: Medio
   - Riesgo: Medio
   - Resultado: Estabilizar consumo de memoria en la API JSON para grandes orgs.

## 9. Quick Wins

- Añadir middlewares de Gates directamente en el archivo `web_auth.php` a rutas exclusivas para reducir ruido lógico en Livewire.
- Cachear `$settings` en `EnforceTenantMfa.php`.

## 10. Qué NO hacer

- **NO retirar PostgreSQL RLS de los modelos** aunque haya errores de validación a nivel Laravel; el RLS ha demostrado su valor protegiendo la visibilidad de datos aunque fallen las Gates a nivel Livewire.
- **NO sobre-ingenierizar el cacheo del Middleware.** Una simple cache en Redis/Array temporal vinculada a un evento "ConfigActualizada" es suficiente.
- **NO separar los Livewire en más Clases Action o CQRS.** Los componentes ya despachan `App\Modules\Tenant\Access\Application\Actions` donde es pertinente y funcionan bien bajo una convención MVC.

## 11. Cobertura de pruebas

El módulo cuenta con pruebas (ej. `TenantRoleHardeningTest`, `TenantIdentityLifecycleTest`, `TenantApiKeyTest`), pero:
- Los tests actuales de **Hardening no simulan usuarios maliciosos interactuando con Livewire** con roles inferiores a "Admin". Solo asumen Happy Paths o comprueban acciones imposibles como intentar borrar un rol de sistema. (Path Negativo Inexistente).
- Faltan tests explícitos para comprobar el middleware MFA.

## 12. Riesgos pendientes

- Monitorizar cómo se gestionan y expiran los tokens de invitación.
- Mantener en observación si `tenant_api_keys` soporta suficiente entalpía frente a ataques de fuerza bruta, o si amerita rate-limiting por clave además de por Tenant global.

## 13. Conclusión

El módulo presenta un buen esqueleto arquitectónico, pero está comprometido críticamente en su implementación del Frontend. **La máxima urgencia (P0) debe recaer sobre el blindaje de las Actions de los componentes Livewire y las validaciones de sus rutas para frenar la escalada de privilegios.** Una vez estabilizada la autorización y aplicada la caché en el middleware, el módulo será considerablemente maduro.
