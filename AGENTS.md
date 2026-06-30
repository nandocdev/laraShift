<!-- CODEGRAPH_START -->
## CodeGraph

In repositories indexed by CodeGraph (a `.codegraph/` directory exists at the repo root), reach for it BEFORE grep/find or reading files when you need to understand or locate code:

- **MCP tool** (when available): `codegraph_explore` answers most code questions in one call — the relevant symbols' verbatim source plus the call paths between them, including dynamic-dispatch hops grep can't follow. Name a file or symbol in the query to read its current line-numbered source. If it's listed but deferred, load it by name via tool search.
- **Shell** (always works): `codegraph explore "<symbol names or question>"` prints the same output.

If there is no `.codegraph/` directory, skip CodeGraph entirely — indexing is the user's decision.
<!-- CODEGRAPH_END -->

## LaraShift — Agent Guide

Laravel 11+ SaaS modular monolith with multi-tenancy, billing, provisioning, and PostgreSQL RLS isolation.

### Architecture

- **3 context groups** under `app/Modules/`: `Central/` (SaaS platform ops), `Tenant/` (customer product), `Shared/` (contracts, events, tenancy bootstrappers).
- Every module follows: `Actions/`, `DTOs/`, `Models/`, `Policies/`, `Livewire/`, `Events/`, `Listeners/`, `Jobs/`, `Exceptions/`, `Providers/`, `Routes/`, `Tests/`. Business logic lives in **Actions** only — not controllers, models, or Livewire components.
- **Billing is CENTRAL-only.** `Tenant/` may have a Payments module for customer-to-business charges, never SaaS subscriptions.
- **Cross-tenant access returns 404, never 403** (403 confirms existence; 404 preserves isolation).
- **Middleware order is mandatory:** `InitializeTenancy → ApplyTenantScopes/RLS → Authenticate → CheckSubscription`.

### Routes

| File | Context |
|---|---|
| `routes/web.php` | Central (SaaS platform) |
| `routes/tenant.php` | Tenant (customer product) |
| `routes/tenant_api.php` | Tenant API |
| `routes/settings.php` | Settings |

### Multi-Tenancy

- **stancl/tenancy v3** with PostgreSQL RLS (via `PostgresRlsBootstrapper`). Single database, row-level isolation.
- Tenant migrations: `database/migrations/tenant/`. Central migrations: `database/migrations/`.
- Every tenant-scoped table must include `tenant_id` and enforce RLS.
- Jobs must carry `tenant_id` and initialize tenancy before business logic.

### Commands

| Command | What it does |
|---|---|
| `composer dev` | Runs server + queue + logs + Vite concurrently |
| `composer lint` | `pint --parallel` (auto-fix) |
| `composer lint:check` | `pint --parallel --test` (check only) |
| `composer test` | `config:clear → lint:check → php artisan test` |
| `./vendor/bin/pest` | Direct Pest runner |
| `php artisan sail` | Laravel Sail |

### Testing

- **Pest** (`pestphp/pest ^4.7`), SQLite `:memory:` in CI.
- Mandatory test categories per module: Feature, **Isolation** (expect 404 on cross-tenant), Security, Quota, Idempotency.
- `Tests/TestCase.php` provides `skipUnlessFortifyHas()` helper.

### Tech Stack

- Livewire 4 + Flux UI (private composer package — needs credentials for install) + Tailwind CSS 4 + Vite 8.
- Spatie packages: permissions, activitylog, medialibrary, data.
- Laravel Cashier (Stripe) + PagueloFacil + dLocal.
- Redis for quotas/cache; Horizon for queue monitoring.

### Gotchas

- `composer install` requires Flux credentials: `composer config http-basic.composer.fluxui.dev <user> <key>`.
- `.npmrc` has `ignore-scripts=true` — npm lifecycle scripts won't run.
- `.env` must configure `CENTRAL_DOMAIN` and `CENTRAL_DOMAINS`.
- Tenant-owned agent guidance in `.gemini/agents/` and `.claude/CLAUDE.md`.
