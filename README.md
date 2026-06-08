# 🧱 LaraShift

> Enterprise SaaS Modular Monolith for Laravel.
>
> Multi-tenancy, Billing, Provisioning, Features, Quotas, Security and Tenant Operations — built from day one for production.

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
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

- Tenant provisioning
- Subscription lifecycle management
- Feature management
- Usage quotas
- Audit trails
- API access
- Tenant customization
- Operational tooling

LaraShift provides those capabilities as a **Modular Monolith**, avoiding the complexity and operational cost of microservices while maintaining strong domain boundaries.

---

## Core Principles

### 1. Modular Monolith

Business capabilities are isolated into bounded contexts.

```text
app/
└── Modules/
    ├── Central/
    ├── Tenant/
    └── Shared/
```

No service spaghetti.

No god folders.

No microservices unless there is a proven need.

---

### 2. PostgreSQL Row-Level Security (RLS)

Tenant isolation is enforced at the database layer.

Benefits:

- Defense in depth
- Reduced risk of tenant data leakage
- Centralized access control
- Production-grade isolation

---

### 3. Production First

Every module is designed around real SaaS operational requirements:

- Billing failures
- Subscription lifecycle
- Tenant suspension
- Auditability
- Support operations
- Security controls

---

# Architecture

## Central Context

Platform-level operations.

```text
Central
├── Provisioning
├── Billing
├── Features
├── Quotas
└── Platform Administration
```

### Provisioning

Responsible for tenant lifecycle.

```text
Create Tenant
↓
Assign Plan
↓
Configure Features
↓
Configure Quotas
↓
Activate
```

Capabilities:

- Tenant creation
- Tenant suspension
- Tenant activation
- Domain management
- Onboarding workflows

---

### Billing

Subscription and payment engine.

Capabilities:

- Subscription plans
- Invoices
- Payment processing
- Dunning workflows
- Multi-gateway support

Supported gateways:

- Stripe
- PagueloFacil
- dLocal

---

### Features

Feature flag system for SaaS plans.

Examples:

```text
API Access
Custom Domains
Webhooks
SMTP
Audit Export
```

Question answered by this module:

> Can this tenant use this capability?

---

### Quotas

Usage limitation engine.

Examples:

```text
Users
API Requests
Domains
Webhooks
Storage
```

Question answered by this module:

> How much can this tenant consume?

---

## Tenant Context

Customer-facing product capabilities.

```text
Tenant
├── Identity
├── Audit
├── Settings
├── API Keys
└── Webhooks
```

### Identity

Authentication and authorization.

Capabilities:

- Users
- Roles
- Permissions
- MFA
- Passkeys
- Session management

---

### Audit

Immutable audit logging.

Tracks:

- User actions
- Permission changes
- Security events
- Billing actions
- Configuration updates

---

### Settings

Tenant customization.

Capabilities:

- Branding
- Localization
- Timezones
- Currency
- Preferences

---

### API Keys

Programmatic access management.

Capabilities:

- API key generation
- Key rotation
- Revocation
- Scopes

Example scopes:

```text
users.read
users.write
billing.read
audit.read
```

---

### Webhooks

Event-driven integrations.

Capabilities:

- Event subscriptions
- Delivery tracking
- Retry mechanisms
- Signature validation

Example events:

```text
user.created
invoice.paid
subscription.cancelled
```

---

# Roadmap

## Phase 1 — SaaS Foundation

- [ ] Identity
- [ ] Provisioning
- [ ] Billing
- [ ] Features
- [ ] Quotas

---

## Phase 2 — Operations & Security

- [ ] Audit
- [ ] API Keys
- [ ] Settings
- [ ] Webhooks

---

## Phase 3 — Platform Extensions

- [ ] Notifications
- [ ] SMTP
- [ ] Domains
- [ ] Data Export

---

## Phase 4 — Growth Tools

- [ ] Landing Builder
- [ ] CMS
- [ ] Marketing

---

# Technology Stack

| Layer          | Technology        |
| -------------- | ----------------- |
| Backend        | Laravel 13        |
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

# Development Status

LaraShift is currently under active development.

The goal is to provide a production-ready SaaS foundation focused on:

- Security
- Maintainability
- Operational simplicity
- Long-term scalability

---

# License

MIT License.

Use it.
Fork it.
Build something valuable.