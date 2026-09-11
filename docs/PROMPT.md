Actúa como arquitecto principal de software, senior en Laravel, PostgreSQL, multi-tenancy, seguridad y producción. Tu trabajo es generar un plan único, completo, accionable y sin preguntas para construir un boilerplate SaaS multitenant single-database con PostgreSQL Row-Level Security.

No debes pedir aclaraciones. Si falta información secundaria, declara supuestos explícitos al final. Si una API exacta de Laravel 13, Livewire 4, Tailwind CSS v4 o Flux UI v2 no estuviera disponible en tu conocimiento, usa la convención moderna equivalente más cercana y márcalo como supuesto, sin romper la arquitectura.

La respuesta debe estar en español y debe ser un documento de implementación, no una explicación académica.

====================================================================
OBJETIVO
====================================================================

Diseñar y planificar un boilerplate SaaS listo para producción con estas características:

- Multi-tenancy single-database.
- PostgreSQL obligatorio.
- Aislamiento de datos por tenant_id usando PostgreSQL RLS como mecanismo principal.
- Los Global Scopes de Laravel solo pueden existir como defensa secundaria, nunca como mecanismo principal.
- Monolito modular estricto.
- Dos identidades separadas:
  - guard `central` para staff/superadmin.
  - guard `web` para usuarios del tenant.
- UI reactiva con Livewire + Alpine.js + Tailwind CSS v4 + Flux UI v2.
- Testing con Pest sobre PostgreSQL real.

====================================================================
STACK OBLIGATORIO
====================================================================

- PHP 8.3+.
- Laravel 13+ o, si hay incompatibilidad documentada, Laravel 12+ manteniendo las mismas convenciones.
- Livewire 4.
- Alpine.js.
- Tailwind CSS v4.
- Flux UI v2.
- PostgreSQL 15+ recomendado, ideal 16+.
- Pest PHP.
- spatie/laravel-data para DTOs.
- Redis recomendado para caché y colas. Si propones otra cosa, justifica.

No introduzcas paquetes adicionales salvo que sean estrictamente necesarios. Cada dependencia extra debe estar justificada en una sección de trade-offs.

Prohibido usar:
- stancl/tenancy.
- paquetes de tenancy que reemplacen el diseño RLS.
- CQRS complejo.
- Event Sourcing.
- microservicios.
- buses de comandos innecesarios.
- arquitecturas distribuidas.

====================================================================
ARQUITECTURA OBLIGATORIA
====================================================================

El sistema es un monolito modular estricto.

Estructura base:

app/Modules/
├── Platform/
│   ├── Contracts/
│   ├── Events/
│   ├── Tenancy/
│   └── UI/
│
├── Central/
│   ├── Auth/
│   ├── Billing/
│   ├── Provisioning/
│   └── Settings/
│
└── Tenant/
    ├── Access/
    ├── Workspace/
    ├── Experience/
    └── Compliance/

Cada módulo complejo debe dividirse en estas capas internas cuando aplique:

- Domain/
  - Models.
  - Value Objects.
  - Enums.
  - Events internos.
  - Policies.
  - Rules puras de negocio.

- Application/
  - Actions.
  - DTOs internos.
  - Jobs.
  - Listeners internos.

- Infrastructure/
  - Clients.
  - Gateways.
  - Notifications.
  - Persistence.

- Interface/
  - Livewire components.
  - Http/Controllers si aplica.
  - Routes si aplica.
  - Views.

- Database/
  - Factories.
  - Seeders.

- Providers/
  - ServiceProvider del módulo.

Las migraciones van centralizadas en:

database/migrations/

No debes colocar migraciones dentro de los módulos.

====================================================================
REGLAS DE ORO INNEGOCIABLES
====================================================================

1. Platform no puede importar nada de Central ni de Tenant.

2. Central no puede importar clases de Tenant.

3. Tenant no puede importar clases de Central.

4. La comunicación entre módulos solo puede ocurrir mediante:
   - Contratos definidos en Platform.
   - Eventos cross-module definidos en Platform/Events.
   - Actions públicas expuestas a través de contratos de Platform.

5. Prohibido consultar directamente Models o tablas de otro módulo.

6. Prohibido que Central haga queries directas sobre tablas de Tenant fuera de los contratos/actions autorizados por Platform.

7. Prohibido que Tenant acceda a tablas de Central sin autorización explícita vía contexto/contrato.

8. Los eventos internos de un módulo no pueden ser consumidos por otro módulo.

9. Los eventos cross-module deben vivir en Platform/Events.

10. Los DTOs cross-module deben vivir en Platform/Contracts o Platform/Tenancy, nunca dentro de un módulo específico.

11. Ninguna clase puede depender de un Eloquent Model de otro módulo.

12. Los Service Providers de cada módulo solo registran rutas, vistas, comandos, listeners, policies y bindings del módulo. No deben contener lógica de negocio.

13. Toda solución debe priorizar:
   1. Seguridad.
   2. Corrección.
   3. Simplicidad.
   4. Mantenibilidad.
   5. Rendimiento.
   6. Escalabilidad razonable.

====================================================================
ESTRATEGIA DE TENANCY OBLIGATORIA
====================================================================

La tenancy es:

- Single database.
- PostgreSQL.
- tenant_id en todas las tablas tenant-owned.
- Row-Level Security como mecanismo principal.
- Identificación de tenant por subdominio.

Ejemplo:

- Central: https://app.saas.test
- Tenant: https://acme.saas.test
- Tenant: https://globex.saas.test

El tenant se resuelve por subdominio.

El contexto de tenant se propaga a PostgreSQL mediante:

SET LOCAL app.tenant_id

o equivalentemente:

select set_config('app.tenant_id', ?, true)

Siempre dentro de una transacción.

Prohibido usar:

SET SESSION app.tenant_id

como mecanismo principal.

Prohibido depender solo de PHP, middleware o Global Scopes.

====================================================================
CONTEXTOS POSTGRESQL OBLIGATORIOS
====================================================================

Para evitar fugas no solo entre tenants, sino también desde Tenant hacia Central, el sistema debe usar tres contextos PostgreSQL:

1. Contexto central:
   app.central = 'on'

   Permite acceso a tablas central-only como central_users, platform_settings, etc.

2. Contexto de resolución de tenant:
   app.tenant_resolution = 'on'

   Contexto corto y controlado para resolver el tenant por subdominio.

3. Contexto tenant:
   app.tenant_id = uuid

   Permite acceso a tablas tenant-owned cuyo tenant_id coincida.

El plan debe incluir funciones PostgreSQL como:

app.current_tenant_id()
app.is_central()
app.is_tenant_resolution()

Ejemplo de patrón esperado:

create or replace function app.current_tenant_id()
returns uuid
language sql
stable
as $$
  select nullif(current_setting('app.tenant_id', true), '')::uuid;
$$;

create or replace function app.is_central()
returns boolean
language sql
stable
as $$
  select coalesce(current_setting('app.central', true), 'off') = 'on';
$$;

create or replace function app.is_tenant_resolution()
returns boolean
language sql
stable
as $$
  select coalesce(current_setting('app.tenant_resolution', true), 'off') = 'on';
$$;

====================================================================
TRANSACCIONES Y CONTEXTO
====================================================================

Debes diseñar un componente central en Platform/Tenancy, por ejemplo:

ContextTransactor

con métodos similares a:

- central(Closure $callback)
- tenantResolution(Closure $callback)
- tenant(TenantId $tenantId, Closure $callback)
- centralAndTenant(TenantId $tenantId, Closure $callback)

Estos métodos deben:

- Abrir transacción.
- Establecer variables locales con set_config(..., true).
- Ejecutar el callback.
- Hacer commit o rollback.
- Garantizar que el contexto no se filtra fuera de la transacción.

Ejemplo conceptual:

DB::transaction(function () use ($tenantId, $callback) {
    DB::statement('select set_config(?, ?, true)', ['app.tenant_id', (string) $tenantId]);
    return $callback();
});

Prohibido concatenar tenant_id en SQL.

Prohibido ejecutar queries tenant-aware fuera de una transacción con contexto.

====================================================================
MIDDLEWARE DE TENANCY
====================================================================

Debes definir middleware claros en Platform/Tenancy.

Middleware mínimos:

1. ResolveTenantFromSubdomain
   - Resuelve el tenant por subdominio.
   - Usa un contrato TenantResolver.
   - No depende de Central ni Tenant directamente.
   - Usa contexto tenant_resolution para consultar el registro del tenant.
   - Cachea la resolución de forma segura.
   - Si el tenant no existe, responde 404.
   - Si el tenant está suspendido/cancelado/provisioning, responde 403 o 404 según convenga.

2. BeginTenantContext
   - Inicia transacción con app.tenant_id.
   - Aplica a rutas de tenant.
   - Debe hacer commit al terminar y rollback en excepción.

3. BeginCentralContext
   - Inicia transacción con app.central = 'on'.
   - Aplica a rutas central.
   - Debe permitir provisioning con central + tenant_id cuando corresponda.

4. TenantCachePrefix
   - Establece prefijo de caché por tenant.
   - Debe ejecutarse después de resolver tenant.

5. TenantLoggingContext
   - Añade tenant_id, request_id y guard a logs.

Orden esperado para rutas tenant:

ResolveTenantFromSubdomain
BeginTenantContext
TenantCachePrefix
TenantLoggingContext
auth:web
...

Orden esperado para rutas central:

BeginCentralContext
CentralLoggingContext
auth:central
...

====================================================================
RLS OBLIGATORIO
====================================================================

Todas las tablas tenant-owned deben tener:

- tenant_id uuid not null.
- índice por tenant_id.
- foreign key a central.tenants si aplica.
- ENABLE ROW LEVEL SECURITY.
- FORCE ROW LEVEL SECURITY.
- políticas restrictivas para SELECT/INSERT/UPDATE/DELETE.

Patrón obligatorio para tablas tenant-owned:

alter table tenant.users enable row level security;
alter table tenant.users force row level security;

create policy tenant_users_isolation
on tenant.users
using (tenant_id = app.current_tenant_id())
with check (tenant_id = app.current_tenant_id());

No se permite ninguna política que autorice filas cuando app.tenant_id sea NULL.

Las tablas central-only deben estar protegidas para requerir contexto central:

create policy central_users_central_only
on central.users
using (app.is_central())
with check (app.is_central());

La tabla central.tenants debe tener una política especial:

- SELECT permitido para:
  - contexto central.
  - contexto tenant_resolution.
  - contexto tenant cuando la fila sea el propio tenant.

- INSERT/UPDATE/DELETE permitido solo para contexto central.

Objetivo:
- Central puede administrar tenants.
- Tenant puede leer su propio registro si es necesario.
- Tenant no puede listar otros tenants.
- La resolución por subdominio funciona sin exponer toda la tabla.

====================================================================
ROLES DE POSTGRESQL
====================================================================

Debes definir una estrategia segura de roles PostgreSQL.

Requisitos:

- La aplicación nunca debe usar un superusuario.
- El rol de aplicación debe ser NOBYPASSRLS.
- Las migraciones pueden ejecutarse con un rol owner/migrator.
- El runtime debe usar un rol sin BYPASSRLS.

Debes explicar si usarás:

Opción simple:
- Un solo rol de aplicación NOBYPASSRLS.
- RLS fuerte por contexto.

Opción endurecida:
- Roles separados para central y tenant runtime.
- Grants mínimos por schema.

Si eliges una opción, justifícala. No dejes la decisión abierta.

En cualquier caso, debes entregar:

- SQL de creación de schemas.
- SQL de roles si aplica.
- SQL de grants.
- SQL de políticas RLS.
- Recomendación para producción.

====================================================================
MODELO DE DATOS BASE
====================================================================

Debes definir el modelo de datos base para Central y Tenant.

Usa schemas PostgreSQL:

- central
- tenant

Si decides no usar schemas, justifica y usa prefijos claros. La opción recomendada es schemas.

Tablas mínimas Central:

central.users
- id uuid primary key
- name
- email citext unique
- password
- role enum: super_admin, admin, support
- is_active boolean
- last_login_at timestamp nullable
- timestamps

central.tenants
- id uuid primary key
- subdomain citext unique
- name
- status enum: provisioning, active, suspended, cancelled
- settings jsonb default '{}'
- timestamps
- deleted_at nullable opcional

central.plans
- id uuid primary key
- name
- slug unique
- features jsonb
- is_active boolean
- timestamps

central.subscriptions
- id uuid primary key
- tenant_id uuid fk central.tenants
- plan_id uuid fk central.plans
- status enum: trialing, active, past_due, cancelled
- trial_ends_at nullable
- ends_at nullable
- timestamps

central.platform_settings
- key string primary key o unique
- group string
- value jsonb
- timestamps

central.password_reset_tokens
- email citext
- token
- created_at
- debe servir para guard central

central.audit_logs
- id
- central_user_id nullable
- action
- subject_type
- subject_id
- metadata jsonb
- ip_address nullable
- user_agent nullable
- created_at

Tablas mínimas Tenant:

tenant.users
- id uuid primary key
- tenant_id uuid not null fk central.tenants
- name
- email citext
- password
- is_active boolean
- email_verified_at timestamp nullable
- timestamps
- unique (tenant_id, email)
- index tenant_id

tenant.roles
- id uuid primary key
- tenant_id uuid not null
- name
- label
- permissions jsonb
- is_system boolean
- timestamps
- unique (tenant_id, name)

tenant.role_user
- role_id uuid fk tenant.roles
- user_id uuid fk tenant.users
- tenant_id uuid not null
- timestamps
- primary key o unique (role_id, user_id)
- index tenant_id

tenant.workspaces
- id uuid primary key
- tenant_id uuid not null
- name
- slug
- settings jsonb
- timestamps
- index tenant_id

tenant.settings
- tenant_id uuid not null
- key
- value jsonb
- timestamps
- unique (tenant_id, key)

tenant.password_reset_tokens
- tenant_id uuid not null
- email citext
- token
- created_at
- unique o index apropiado por tenant/email

tenant.invitations
- id uuid
- tenant_id uuid not null
- email citext
- role_id uuid nullable
- token
- invited_by uuid nullable
- expires_at
- accepted_at nullable
- timestamps
- unique (tenant_id, email) mientras esté pendiente

tenant.audit_logs
- id
- tenant_id uuid not null
- user_id nullable
- action
- auditable_type
- auditable_id
- changes jsonb
- ip_address nullable
- user_agent nullable
- created_at
- index tenant_id

Debes especificar:
- constraints.
- indexes.
- foreign keys.
- enums en PHP y checks/constraints en PostgreSQL.
- qué tablas llevan RLS.
- qué políticas lleva cada tabla.

====================================================================
IDENTIDAD Y AUTH
====================================================================

Hay dos identidades separadas.

Central:
- Modelo: CentralUser.
- Tabla: central.users.
- Guard: central.
- Provider: central_users.
- Cookie de sesión separada, por ejemplo central_session.
- Rutas bajo dominio central.
- Password reset separado.

Tenant:
- Modelo: User.
- Tabla: tenant.users.
- Guard: web.
- Provider: tenant_users.
- Cookie de sesión separada, por ejemplo tenant_session.
- Rutas bajo subdominio de tenant.
- Password reset separado y tenant-aware.

Debes resolver:
- Configuración de guards y providers.
- Sesiones separadas para evitar colisiones entre central y tenant.
- Throttling de login por guard, IP y tenant cuando aplique.
- Password hashing con Argon2id o bcrypt fuerte.
- Políticas de autorización.

No debes permitir que un usuario de tenant acceda a rutas central aunque use la misma URL.

No debes permitir que un CentralUser inicie sesión como usuario de tenant sin un mecanismo explícito y auditable. Si propones impersonación, debe ser opcional, auditable y segura.

====================================================================
ENRUTAMIENTO
====================================================================

Debes organizar rutas en:

routes/web.php
routes/tenant.php
routes/settings.php

Convención obligatoria:

routes/web.php
- Rutas del panel Central.
- Dominio central.
- Guard central.
- Incluye login central, dashboard, tenants, central users, billing básico.

routes/tenant.php
- Rutas del panel Tenant.
- Subdominio dinámico.
- Guard web.
- Incluye login tenant, dashboard, workspace, team, roles, tenant settings.

routes/settings.php
- Settings globales de plataforma administrados desde Central.
- Se monta bajo dominio central y grupo autenticado central.
- Ejemplos: platform settings, branding, billing settings.

Las settings propias del tenant deben vivir dentro de Tenant/Workspace o Tenant/Experience y rutearse en routes/tenant.php.

Debes entregar:

- Registro de route files en bootstrap/app.php o mecanismo equivalente.
- Grupos por dominio/subdominio.
- Middleware por grupo.
- Prefijos y nombres de rutas.
- Orden de middleware.
- Cómo se evita que rutas tenant respondan en dominio central y viceversa.

Ejemplo de rutas central:

- GET /login
- POST /login
- GET /dashboard
- GET /tenants
- POST /tenants
- GET /tenants/{tenant}
- POST /tenants/{tenant}/suspend
- POST /tenants/{tenant}/activate
- GET /users
- GET /settings/platform
- GET /settings/branding
- GET /settings/billing

Ejemplo de rutas tenant:

- GET /login
- POST /login
- GET /dashboard
- GET /settings/workspace
- GET /settings/profile
- GET /team
- POST /team/invite
- GET /roles

====================================================================
PLATFORM: CONTRATOS Y EVENTOS
====================================================================

Platform debe contener contratos mínimos para desacoplar Central y Tenant.

Contratos mínimos:

TenantContext
- Obtiene tenant actual.
- No depende de Eloquent.
- Expone TenantId.

TenantResolver
- Resuelve tenant por subdominio.
- Devuelve DTO/Value Object, no Model de Central.

TenantProvisioner
- Provisiona recursos iniciales de un tenant.
- Implementado por Tenant.
- Consumido por Central vía contrato.

TenantAwareJob
- Interfaz para jobs tenant-aware.
- Expone tenantId().
- Debe permitir rehidratación de contexto.

ContextTransactor
- Ejecuta callbacks con contexto central, resolution o tenant.

TenantCache
- Genera claves de caché tenant-scoped.

CurrentTenantAccessor
- Acceso seguro al tenant actual para UI/logs/policies.

Eventos cross-module mínimos en Platform/Events:

- TenantProvisioned
- TenantActivated
- TenantSuspended
- TenantCancelled

Los payloads de eventos cross-module no deben incluir Eloquent Models. Deben usar IDs, enums y DTOs de Platform.

====================================================================
PROVISIONING DE TENANTS
====================================================================

Debes diseñar el flujo completo de provisioning desde Central.

Flujo obligatorio:

1. Staff central crea tenant mediante Action en Central/Provisioning.

2. La Action valida:
   - subdomain único y válido.
   - nombre.
   - plan opcional.
   - datos del primer admin del tenant si aplica.

3. Se inicia una transacción con contexto central.

4. Se inserta central.tenants con status provisioning.

5. Se establece contexto central + tenant_id dentro de la misma transacción.

6. Central invoca TenantProvisioner vía contrato de Platform.

7. TenantProvisioner, implementado por Tenant, crea:
   - workspace default.
   - roles default.
   - usuario owner del tenant.
   - settings iniciales.
   - registros mínimos de Access/Workspace.

8. Se actualiza tenant status a active.

9. Se dispatchea TenantProvisioned.

10. Si algo falla, rollback completo.

Prohibido que Central cree directamente usuarios, roles o workspaces usando Models de Tenant.

Prohibido que Tenant consulte Central para provisionar salvo por DTOs/contratos.

====================================================================
ACTIONS Y DTOS
====================================================================

Estándar obligatorio para Actions:

- final readonly class.
- Dependencias inyectadas por constructor.
- Método execute().
- Sin estado mutable.
- Sin lógica en controllers/Livewire.
- Puede devolver DTO, Model propio del módulo, void o resultado tipado.
- Puede dispatchear eventos.
- Puede llamar persistence del mismo módulo.
- No puede tocar tablas de otro módulo.

Ejemplo conceptual:

final readonly class CreateTenantAction
{
    public function __construct(
        private TenantRepository $tenants,
        private TenantProvisioner $provisioner,
        private Dispatcher $events,
    ) {}

    public function execute(CreateTenantData $data): TenantData
    {
        // ...
    }
}

DTOs:

- Usar spatie/laravel-data.
- DTOs internos pueden vivir en Application/Data del módulo.
- DTOs cross-module deben vivir en Platform.
- DTOs no pueden contener Eloquent Models.
- DTOs deben validar entrada o venir de validación previa en Interface.

Ejemplo de DTO cross-module:

- TenantProvisionData
- TenantData
- TenantAdminData
- TenantBrandingData

No abuses de DTOs para todo. Úsalos donde aporten contratos claros, especialmente entre módulos y entradas de Actions.

====================================================================
JOBS TENANT-AWARE
====================================================================

Todo job tenant-aware debe:

- Implementar TenantAwareJob.
- Serializar tenant_id.
- Rehidratar contexto antes de ejecutar.
- Ejecutarse dentro de transacción con app.tenant_id.
- Fallar de forma segura si el tenant no existe o está suspendido.

Debes proponer uno de estos mecanismos:

1. Middleware de queue para jobs tenant-aware.
2. Trait seguro que envuelva handle().
3. Pipeline de Bus.

La opción recomendada debe evitar que el contexto de un job se filtre a otro job en el mismo worker.

Prohibido confiar en un singleton global mutable sin limpiar.

Jobs central-aware:

- Si un job solo toca Central, no debe requerir tenant_id.
- Si un job mezcla Central y Tenant, debe justificarlo y usar ContextTransactor.

====================================================================
CACHÉ
====================================================================

La caché debe estar aislada por tenant.

Formato obligatorio de claves tenant:

tenant:{tenant_id}:{key}

Formato para central:

central:{key}

Formato para resolución:

tenant-resolution:{subdomain}

Debes diseñar:

- TenantCache o servicio equivalente.
- Invalidación de caché al actualizar tenant.
- TTL razonable para resolución de subdominio.
- Uso de tags si usas Redis.
- Prevención de colisiones entre tenants.

Prohibido usar claves globales para datos de tenant.

Rate limiting también debe incluir tenant_id cuando aplique.

====================================================================
UI: LIVEWIRE + FLUX UI
====================================================================

La UI debe ser reactiva con Livewire y Flux UI.

Debes definir:

- Layouts base en Platform/UI.
- Layout central.
- Layout tenant.
- Componentes Flux reutilizables.
- Dark mode opcional.
- Formularios con Livewire.
- Validación server-side.
- Estados de carga.
- Estados vacíos.
- Mensajes flash.

Reglas UI:

- Livewire components no deben ejecutar queries directamente.
- Livewire components deben llamar Actions o contratos.
- Controllers, si existen, deben ser delgados.
- Vistas en módulos Interface/Views.
- Registrar namespaces de vistas:
  - platform::
  - central::
  - tenant::

Pantallas mínimas Central:

- Login central.
- Dashboard central.
- Listado de tenants.
- Crear tenant.
- Detalle de tenant.
- Suspender/activar tenant.
- Gestión de central users.
- Platform settings.
- Branding settings.
- Billing/plans básico.

Pantallas mínimas Tenant:

- Login tenant.
- Dashboard tenant.
- Workspace settings.
- Team/users.
- Roles.
- Invitations.
- Profile.
- Audit/compliance básico si aplica.

====================================================================
AUTORIZACIÓN
====================================================================

Debes definir policies y gates.

Central:

- Superadmin puede todo.
- Admin puede gestionar tenants y settings limitados.
- Support puede ver y asistir, sin acciones destructivas.

Tenant:

- Owner puede todo dentro del tenant.
- Admin puede gestionar usuarios y roles limitados.
- Member acceso básico.

Reglas:

- Las policies deben comprobar tenant_id.
- No basta con ocultar botones en UI.
- Toda acción server-side debe autorizar.
- No usar Gate::before para bypass total sin control.
- Las policies no deben depender de Models de otro módulo.

====================================================================
TESTING CON PEST
====================================================================

Debes diseñar una estrategia de testing completa.

Requisitos:

- Pest PHP.
- PostgreSQL real para tests de tenancy/RLS.
- Prohibido validar RLS solo con SQLite.
- Tests de feature, unit, tenancy y arquitectura.

Entorno:

- .env.testing con PostgreSQL.
- Docker Compose opcional pero recomendado.
- Usuario de test sin BYPASSRLS para probar RLS.
- Migraciones ejecutadas antes de tests.

Tests mínimos:

1. RLS básico:
   - Sin app.tenant_id, select devuelve vacío.
   - Sin app.tenant_id, insert/update/delete falla.
   - Con tenant A, no se ven filas de tenant B.
   - Con tenant A, insert con tenant_id B falla.

2. CrossTenantLeakTest:
   - Usuario tenant A no puede ver usuarios de tenant B.
   - Usuario tenant A no puede editar recursos de tenant B.
   - Rutas con IDs de otro tenant devuelven 404/403.
   - Actions no pueden leer otro tenant.
   - Jobs no pueden leer otro tenant.
   - Caché no puede filtrar entre tenants.

3. Central vs Tenant:
   - Rutas central no accesibles con guard web.
   - Rutas tenant no accesibles con guard central sin mecanismo explícito.
   - Contexto tenant no puede leer central_users.
   - Contexto central no puede leer tenant.users sin tenant_id explícito.

4. Provisioning:
   - Crear tenant desde central crea tenant, workspace, roles y owner.
   - Si falla provisioning, se hace rollback.
   - Tenant provisioning no deja tenant activo a medias.

5. Subdominios:
   - Subdominio válido resuelve tenant.
   - Subdominio inexistente responde 404.
   - Subdominio suspendido responde 403/404.
   - Subdominios reservados no se asignan.

6. Jobs:
   - Job tenant-aware rehidrata tenant_id.
   - Job sin tenant no accede a datos tenant.
   - Job de tenant A no lee tenant B.

7. Arquitectura:
   - Platform no importa Central ni Tenant.
   - Central no importa Tenant.
   - Tenant no importa Central.
   - No hay consultas cross-module directas.

Debes entregar ejemplos concretos de Pest para CrossTenantLeakTest y RLS.

====================================================================
ESTRUCTURA DETALLADA ESPERADA
====================================================================

Debes entregar un árbol completo de archivos para los tres módulos.

No basta con listar carpetas. Debes proponer archivos concretos.

Ejemplo de nivel esperado:

app/Modules/Platform/Contracts/TenantContext.php
app/Modules/Platform/Contracts/TenantResolver.php
app/Modules/Platform/Contracts/TenantProvisioner.php
app/Modules/Platform/Contracts/TenantAwareJob.php
app/Modules/Platform/Tenancy/TenantId.php
app/Modules/Platform/Tenancy/CurrentTenant.php
app/Modules/Platform/Tenancy/ContextTransactor.php
app/Modules/Platform/Tenancy/Middleware/ResolveTenantFromSubdomain.php
app/Modules/Platform/Tenancy/Middleware/BeginTenantContext.php
app/Modules/Platform/Tenancy/Middleware/BeginCentralContext.php
app/Modules/Platform/Tenancy/Support/TenantCache.php
app/Modules/Platform/Events/TenantProvisioned.php
app/Modules/Platform/Events/TenantSuspended.php
app/Modules/Platform/Providers/PlatformServiceProvider.php
app/Modules/Platform/UI/Components/...

app/Modules/Central/Auth/Domain/Models/CentralUser.php
app/Modules/Central/Auth/Application/Actions/LoginCentralUserAction.php
app/Modules/Central/Auth/Interface/Livewire/LoginForm.php
app/Modules/Central/Provisioning/Application/Actions/CreateTenantAction.php
app/Modules/Central/Provisioning/Application/Actions/SuspendTenantAction.php
app/Modules/Central/Provisioning/Application/Actions/ActivateTenantAction.php
app/Modules/Central/Provisioning/Interface/Livewire/TenantIndex.php
app/Modules/Central/Settings/Application/Actions/UpdatePlatformSettingsAction.php
app/Modules/Central/Providers/CentralServiceProvider.php

app/Modules/Tenant/Access/Domain/Models/User.php
app/Modules/Tenant/Access/Domain/Models/Role.php
app/Modules/Tenant/Access/Application/Actions/CreateUserAction.php
app/Modules/Tenant/Access/Application/Actions/InviteUserAction.php
app/Modules/Tenant/Access/Application/TenantProvisionerImplementation.php
app/Modules/Tenant/Workspace/Domain/Models/Workspace.php
app/Modules/Tenant/Workspace/Application/Actions/UpdateWorkspaceSettingsAction.php
app/Modules/Tenant/Compliance/Domain/Models/AuditLog.php
app/Modules/Tenant/Providers/TenantServiceProvider.php

Debes completar el árbol con todas las capas internas relevantes.

====================================================================
ENTREGABLES EXACTOS
====================================================================

Debes entregar exactamente estas siete secciones:

1. Estructura detallada del monolito modular siguiendo las capas internas.

   - Árbol completo.
   - Responsabilidad de cada módulo.
   - Responsabilidad de cada capa.
   - Qué archivos van en Platform, Central y Tenant.
   - Cómo se registran providers, vistas y rutas.
   - Composer autoload PSR-4 para app/Modules.

2. Definición del modelo de datos base para Central y Tenant.

   - Tablas.
   - Columnas.
   - Tipos.
   - Constraints.
   - Índices.
   - Foreign keys.
   - Enums.
   - Modelos Eloquent.
   - Casts.
   - Relaciones.
   - Qué tablas son central-only y cuáles tenant-owned.
   - Diagrama simple ASCII o Markdown.

3. Guía de implementación del TenantContext y la política RLS en PostgreSQL.

   - ContextTransactor.
   - Middleware.
   - set_config con transacciones.
   - Funciones PostgreSQL.
   - Políticas RLS.
   - Force RLS.
   - Roles y grants.
   - Cómo evitar fugas entre tenants.
   - Cómo evitar que tenant acceda a central.
   - Cómo tratar jobs, CLI y queues.
   - Ejemplos de código production-ready.

4. Organización del enrutamiento.

   - routes/web.php central.
   - routes/tenant.php.
   - routes/settings.php.
   - Middleware por grupo.
   - Guards.
   - Dominios/subdominios.
   - Nombres de rutas.
   - Livewire pages.
   - Cómo se registra todo en bootstrap/app.php.

5. Estándares de Actions y DTOs para comunicación entre módulos.

   - Reglas.
   - Ejemplos de Actions.
   - Ejemplos de DTOs.
   - Contratos de Platform.
   - Eventos cross-module.
   - Provisioning flow.
   - Qué está prohibido.
   - Ejemplos correctos e incorrectos.

6. Estrategia de Testing.

   - Pest.
   - PostgreSQL real.
   - CrossTenantLeakTest.
   - RLS tests.
   - Central vs Tenant tests.
   - Jobs tests.
   - Caché tests.
   - Arquitectura tests.
   - Ejemplos concretos de tests.
   - Configuración de entorno.

7. Plan de desarrollo paso a paso respetando scopes.

   - Fases.
   - Orden correcto.
   - Tareas por módulo.
   - Comandos.
   - Migraciones.
   - Tests a añadir en cada fase.
   - Criterios de aceptación por fase.
   - Riesgos.
   - Checklist final de producción.

====================================================================
FORMATO DE SALIDA
====================================================================

La respuesta debe ser un documento Markdown con este orden:

# Plan de implementación: SaaS multitenant RLS

## 0. Supuestos y decisiones explícitas

## 1. Estructura modular detallada

## 2. Modelo de datos base

## 3. TenantContext y PostgreSQL RLS

## 4. Enrutamiento

## 5. Actions, DTOs y comunicación entre módulos

## 6. Testing con Pest

## 7. Plan de desarrollo paso a paso

## 8. Checklist de producción

## 9. Riesgos y trade-offs

## 10. Supuestos finales

Reglas de formato:

- Usa headings.
- Usa bullets.
- Usa tablas si ayudan.
- Usa diagramas ASCII si ayudan.
- Cada bloque de código debe indicar la ruta del archivo.
- No entregues pseudocódigo si puedes entregar código real.
- No uses comentarios obvios.
- No repitas el enunciado.
- No hagas preguntas.
- No entregues solo teoría.

====================================================================
REQUISITOS DE PRODUCCIÓN
====================================================================

Debes considerar explícitamente:

- Seguridad.
- Validación.
- CSRF.
- Autorización.
- Rate limiting.
- Logs.
- Observabilidad básica.
- Errores y excepciones.
- Transacciones.
- Race conditions.
- N+1 queries.
- Índices.
- Migraciones.
- Backups.
- Colas.
- Caché.
- Sesiones.
- Cookies.
- Subdominios reservados.
- Suspensión de tenants.
- Fallos de provisioning.
- Idempotencia en jobs críticos si aplica.

No hace falta que resuelvas todo con infraestructura compleja, pero sí debes identificar riesgos y controles mínimos.

====================================================================
PROHIBICIONES ADICIONALES
====================================================================

No propongas:

- Microservicios.
- Separar Central y Tenant en aplicaciones distintas.
- Event Sourcing.
- CQRS pesado.
- Repositorios abstractos innecesarios.
- Factories innecesarias.
- Capas adicionales que no resuelvan un problema real.
- Tenancy por base de datos separada.
- Tenancy por schema separado sin RLS.
- Global Scopes como única defensa.
- Consultas cross-module directas.
- Uso de superusuario de PostgreSQL en runtime.
- Código que dependa de otro módulo por FQCN si viola reglas.
- Paquetes que oculten la lógica de tenancy.

====================================================================
CRITERIOS DE ACEPTACIÓN
====================================================================

El plan se considera válido si:

1. Platform no depende de Central ni Tenant.

2. Central y Tenant no se importan entre sí.

3. Toda tabla tenant-owned tiene RLS con tenant_id.

4. Sin app.tenant_id no se puede leer ni escribir datos tenant.

5. Un tenant no puede leer datos de otro tenant.

6. Un tenant no puede leer tablas central-only.

7. Central puede administrar tenants sin violar RLS.

8. El provisioning es transaccional y atómico.

9. Los jobs tenant-aware rehidratan contexto.

10. La caché está aislada por tenant.

11. Hay tests reales contra PostgreSQL.

12. Hay CrossTenantLeakTest.

13. El plan es incremental y ejecutable.

14. No introduce complejidad injustificada.

15. Entrega código suficiente para implementar el boilerplate.

====================================================================
INSTRUCCIÓN FINAL
====================================================================

Genera ahora el documento completo siguiendo exactamente las reglas anteriores.

No preguntes.

No simplifiques las reglas.

No omitas los siete entregables.

No conviertas la respuesta en un catálogo de opciones. Entrega una recomendación única, clara y ejecutable, con trade-offs cuando sea necesario.

Si una parte no puede completarse por limitación de conocimiento, indícalo en supuestos y entrega la mejor alternativa compatible con la arquitectura.