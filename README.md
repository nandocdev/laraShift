# 🧱 openSaaS

> Boilerplate SaaS multitenant listo para producción: Monolito Modular sobre Laravel, con aislamiento real por PostgreSQL RLS, billing agnóstico al proveedor e IAM completo por tenant.

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-RLS-4169E1?style=for-the-badge&logo=postgresql)](https://www.postgresql.org)
[![Livewire](https://img.shields.io/badge/Livewire-4+Flux-4e1d95?style=for-the-badge&logo=livewire)](https://livewire.laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

---

## Por qué openSaaS

La mayoría de los starters se detienen en login + equipos + suscripciones básicas. Una plataforma SaaS real necesita:

- **Provisioning idempotente** de tenants (reintentos sin duplicados, rollback transaccional)
- **Billing agnóstico al proveedor** (Clave redirect, dLocal tarjeta/efectivo, Stripe diferido)
- **Dunning unificado** con reloj propio (reintentos MIT vs renovación por link)
- **Aislamiento bank-grade** con PostgreSQL Row-Level Security como garantía real
- **IAM por tenant**: roles granulares, SSO/SAML, sesiones revocables, 2FA + passkeys
- **Portabilidad y cierre**: exportación con purga a 24h, danger zone con gracia de 30 días

Todo como **Monolito Modular**: límites de dominio fuertes sin el costo operativo de microservicios.

---

## ⚡ Quickstart

```bash
# 1. Instalar dependencias
composer setup

# 2. Desarrollo local (app + Vite concurrently)
composer dev

# 3. Tests
composer test          # o: php artisan test --compact

# 4. Calidad antes de cada commit
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=NombreDelTest
```

> **Requisitos:** PHP ^8.4 · Node 22 · PostgreSQL 15+ (prod) / SQLite `:memory:` (tests) · Redis (prod: cache, colas, sesiones).

---

## 🏗 Arquitectura

Tres scopes estrictos. La comunicación entre módulos ocurre solo vía **Actions públicas, Contracts o Domain Events** — nunca `Model::find()` cruzado.

```text
app/Modules/
├── Platform/   → Transversal sin reglas de negocio: Contracts, Events, Tenancy (RLS),
│                  Security, UI, Observability, Data
├── Central/    → Operación de la plataforma: Auth, Billing, Catalog, Provisioning,
│                  Growth, Operations, Settings, Support
└── Tenant/     → Scaffolding del producto: Access, Workspace, Experience,
                   Compliance, Integrations
```

```text
Central ─┐
          ├──> Platform   (Platform nunca importa Central ni Tenant)
Tenant  ─┘
```

Cada módulo complejo sigue `Domain / Application / Infrastructure / Interface / Database / Providers`. Lógica de negocio solo en **Actions** (`final readonly class` + `execute(DTO)` con `spatie/laravel-data`); Livewire solo estado de UI.

**Regla de oro:** nada de capas "por si acaso" — cada abstracción se gana con 2+ casos de uso reales.

---

## 🔒 Multitenancy: Single DB + RLS

El aislamiento no depende de Scopes de Eloquent (defensa secundaria) sino de PostgreSQL RLS como mecanismo principal:

- `TenantContext` registrado como binding `scoped()` (compatible Octane, nunca `singleton`)
- `SET LOCAL app.tenant_id` dentro de transacción explícita por unidad de trabajo
- Jobs en cola con contrato `TenantAware` + rehidratación propia de contexto
- Todo módulo tenant-aware incluye su `CrossTenantLeakTest`

---

## 💳 Billing

Core propio agnóstico al proveedor, con capacidades resueltas por **proveedor × método de pago**:

| Vía | Checkout | Recurrencia |
| --- | -------- | ----------- |
| Clave (PagueloFacil) | Redirect + webhook HMAC | Por link de renovación (scheduler + dunning separado) |
| dLocal tarjeta | Smart Fields + cobro server-side | MIT silencioso (3 reintentos → `suspended`) |
| dLocal efectivo | Checkout único | Sin recurrencia (la UI nunca la promete) |
| Stripe | Diferido hasta demanda real | — |

Idempotencia no negociable: `UNIQUE(gateway, gateway_event_id)`, `lockForUpdate` + recuperación `23505`, `Cache::lock` en verificación. Detalle completo en [`docs/BILLING.md`](docs/BILLING.md) y roadmap en [`docs/ROADMAP_BILLING.md`](docs/ROADMAP_BILLING.md).

---

## 🧪 Testing

```bash
php artisan test --compact                          # suite completa (SQLite)
php artisan test --compact --filter=NombreDelTest    # iteración rápida
vendor/bin/pint --parallel --test                    # lo que el CI exige
```

- **Pest** sobre SQLite `:memory:`; tests `RLSEnforce` contra Postgres real en CI
- Mínimo por feature: caso feliz + validación + permisos + aislamiento + error
- Pipeline de verificación: `config:clear` → `pint` → tests (ver `composer ci:check`)

---

## 🗂 Módulos Tenant (scaffolding, sin dominio vertical)

| Módulo | Qué cubre |
| ------ | --------- |
| Access | Usuarios, roles granulares, API keys, invitaciones con token, SSO/SAML, sesiones revocables, 2FA + passkeys |
| Workspace | Dashboard, equipo, notificaciones, danger zone con gracia de purga |
| Experience | Branding, localización, SMTP, landing builder |
| Compliance | Auditoría, exportación autolimpiable (24h) |
| Integrations | SMTP por tenant (con aislamiento Octane) |

> CRM, Documents, Forms o cualquier vertical **no viven en el core**: cada producto los construye sobre este framework en su propio ciclo de release.

---

## 📚 Documentación

| Documento | Contenido |
| --------- | --------- |
| [`docs/PRD.md`](docs/PRD.md) | Visión y alcance del framework |
| [`docs/CU.md`](docs/CU.md) | Matriz de casos de uso Central + Tenant |
| [`docs/BILLING.md`](docs/BILLING.md) | Especificación del Billing Core |
| [`docs/ARCHITECTURE_RULES.md`](docs/ARCHITECTURE_RULES.md) | Reglas obligatorias de implementación |
| [`docs/PROJECT_DECISIONS.md`](docs/PROJECT_DECISIONS.md) | Decisiones arquitectónicas (multitenancy, scopes, módulos) |
| [`docs/CentralEntry.md`](docs/CentralEntry.md) | Mapa del panel Central |
| [`docs/modules/`](docs/modules/) | Auditorías por módulo con hallazgos y rutas de trabajo |
| [`AGENTS.md`](AGENTS.md) | Protocolo del Tech Lead + skills por dominio |

---

## 🎯 Para quién es (y para quién no)

**Ideal para:** B2B SaaS, plataformas internas, white-labels, productos multitenant, software empresarial.

**No es:** un CMS, un eCommerce, una SPA, ni un producto final — es la base sobre la que se construye.

---

## 📄 Licencia

MIT. Úsalo. Forkéalo. Construye algo valioso.
