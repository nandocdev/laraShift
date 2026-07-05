<!-- CODEGRAPH_START -->
## CodeGraph

In repositories indexed by CodeGraph (a `.codegraph/` directory exists at the repo root), reach for it BEFORE grep/find or reading files when you need to understand or locate code:

- **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch hops grep can't follow. Name a file or symbol in the query to read its current line-numbered source. If it's listed but deferred, load it by name via tool search.
- **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same output.

If there is no `.codegraph/` directory, skip CodeGraph entirely — indexing is the user's decision.
<!-- CODEGRAPH_END -->

## Arquitectura: Monolito Modular (Central, Tenant, Platform)

Todos los desarrollos en este repositorio deben seguir la arquitectura de **Monolito Modular** definida en [MODULE_SCOPE_MAP.md](file:///home/nandocdev/Projects/laraShift/docs/architecture/MODULE_SCOPE_MAP.md):

### 1. Estructura de Scopes (en `app/Modules/`)
- `Central/`: Módulos de operación del negocio SaaS.
- `Tenant/`: Módulos de funcionalidades que usan los clientes finales en sus workspaces.
- `Platform/`: Módulos y primitivas transversales (seguridad, multi-tenancy, contratos, observabilidad, UI base). **Prohibido colocar nuevos desarrollos en `Shared`**. Todo nuevo desarrollo transversal o de infraestructura técnica debe ir en `Platform/` (o migrar lo existente bajo `Shared/` de forma ordenada).

### 2. Estructura Interna Estándar de los Módulos
Cualquier módulo complejo debe subdividirse en las siguientes capas bajo `app/Modules/<Scope>/<Modulo>/`:
- `Domain/`: Reglas puras de negocio (Models, ValueObjects, Enums, Events de dominio, Policies, Rules). Sin controllers ni llamadas HTTP externas.
- `Application/`: Casos de uso y orquestación (Actions, Commands, Queries, DTOs, Jobs, Listeners).
- `Infrastructure/`: Adaptadores técnicos (Persistence, Clients, Gateways de pago, Mail, Notifications).
- `Interface/`: Capa de entrada/salida de usuario (Livewire, Http/Controllers, Routes, Views).
- `Database/`: Migraciones, Factories y Seeders específicos del módulo.
- `Providers/`: El Service Provider del módulo.

### 3. Restricciones de Dependencias (Regla de Oro)
- `Platform` debe ser 100% independiente. **Prohibido importar clases de `Central` o `Tenant` dentro de `Platform`**.
- `Central` y `Tenant` pueden depender de `Platform`.
- Para evitar acoplamiento directo entre `Central` y `Tenant`, usa contratos abstractos (`Platform/Contracts`), eventos de integración (`Platform/Events`), read models o jobs.
