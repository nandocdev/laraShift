# 🧱 LaraShift

> Enterprise SaaS Modular Monolith for Laravel.
>
> Multi-tenancy, Billing, Provisioning, Features, Quotas, Security and Tenant Operations — built from day one for production.

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16+-4169E1?style=for-the-badge&logo=postgresql)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

---

## Why LaraShift?

Most Laravel SaaS starters stop at:

- Authentication
- Teams
- Basic subscriptions

Real SaaS platforms require much more:

- **Idempotent Tenant Provisioning** (with rollback and retry)
- **Subscription Lifecycle Management** (including unified Dunning)
- **Feature & Quota Management Engine**
- **Bank-Grade Isolation** (via PostgreSQL Row-Level Security)
- Audit trails & Secure API Access (HMAC)
- Operational tooling

LaraShift provides those capabilities as a **Modular Monolith**, avoiding the complexity and operational cost of microservices while maintaining strong domain boundaries.

---

## Core Principles

### 1. Modular Monolith (RUP Oriented)

Business capabilities are isolated into bounded contexts, strictly enforcing architectural boundaries.

```text
app/
└── Modules/
    ├── Central/ (Platform)
    ├── Tenant/ (Product)
    └── Shared/ (Contracts & Events)
```

No service spaghetti. No god folders. No microservices unless there is a proven need.

---

### 2. Bank-Grade PostgreSQL Row-Level Security (RLS)

Tenant isolation is enforced at the database layer, completely decoupling security from application-level ORM scopes.

Benefits:
- **Defense in depth:** Validated through `RLSIsolationTest`.
- Reduced risk of tenant data leakage.
- Centralized access control: `tenant_isolation` policy applied with `WITH CHECK`.

---

### 3. Production First

Every module is designed around real SaaS operational requirements:
- Atomic, modular provisioning with `TenantDataSeeder` initialization.
- Centralized Dunning workflows (suspension on failed payments).
- HMAC-SHA256 hardened API Keys with throttled usage tracking.
- Dynamic `Feature` and `Quota` middleware protection (`HTTP 403` / `HTTP 429`).

---

# Architecture

## Central Context

Platform-level operations.

### Provisioning
Responsible for the robust tenant lifecycle via a modular pipeline.
Capabilities:
- Domain Reservation (`ReserveTenantDomainAction`)
- Database Core Data Seeding (`SetupTenantCoreDataAction`)
- External Infrastructure Hook (`ProvisionInfrastructureAction` -> `RailwayService`)
- Idempotent execution (retry without duplication)

### Billing
Subscription and payment engine.
Capabilities:
- Subscription plans
- Invoices & Dunning workflows
- Unified Webhook handling
- Supported gateways: Stripe, PagueloFacil, dLocal.

### Features & Quotas
Usage limitation engine running at runtime.
Capabilities:
- Trait-based validation: `$tenant->hasFeature()`, `$tenant->withinQuota()`.
- Middlewares: `feature`, `quota`.
- Graceful exception handling: `QuotaExceededException`.

---

## Tenant Context

Customer-facing product capabilities.

### Identity & API Keys
Authentication and authorization.
Capabilities:
- Users, Roles, Permissions (Spatie).
- Secure API Keys (HMAC hashed, no dynamic `Gate::define` memory leaks).
- Throttled metric updates to protect Database I/O.

---

# Roadmap & Status

LaraShift has reached a high level of **SaaS Readiness**, completing its core architectural foundation.

## Phase 1 — SaaS Foundation [COMPLETED]
- [x] Identity & Roles
- [x] Idempotent Provisioning
- [x] Billing & Dunning
- [x] RLS Database Isolation
- [x] Secure API Keys

## Phase 2 — Product & Operations [IN PROGRESS]
- [x] Feature & Quota Engine
- [x] Infrastructure Hooks
- [ ] Real Domain mutator implementation (Railway API)
- [ ] Comprehensive Audit Logs
- [ ] Webhook outbound delivery

## Phase 3 — Platform Extensions
- [ ] Notifications Center
- [ ] SMTP Configuration
- [ ] Data Export

## Phase 4 — Growth Tools
- [ ] Landing Builder
- [ ] Marketing & CMS

---

# Technology Stack

| Layer          | Technology        |
| -------------- | ----------------- |
| Backend        | Laravel 11        |
| Language       | PHP 8.3+          |
| Database       | PostgreSQL 16+    |
| Multi-Tenancy  | stancl/tenancy    |
| Frontend       | Livewire 4        |
| UI             | Flux UI           |
| Styling        | Tailwind CSS      |
| Authentication | Fortify           |
| Authorization  | Spatie Permission |
| Queues         | Laravel Horizon   |
| Audit          | Activitylog       |

---

# Design Goals

LaraShift is designed for teams building:
- B2B SaaS products
- Internal business platforms
- White-label applications
- Multi-tenant products
- Enterprise software

Not intended for:
- Consumer social networks
- Real-time gaming platforms
- Microservice-first architectures

---

# License

MIT License.

Use it. Fork it. Build something valuable.