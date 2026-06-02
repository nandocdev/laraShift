# Architecture.md

# Plinth — Architecture

## 1. Architectural Philosophy

Plinth is built using:

- Modular Monolith
- Single Database
- Tenant Isolation by Design

Architecture is opinionated.

The goal is not maximum flexibility.

The goal is:

- predictability
- maintainability
- tenant security
- operational simplicity

Every technical decision must support these goals.

---

# 2. Core Architectural Principles

Plinth follows five non-negotiable principles.

## 2.1 Tenant Isolation First

Highest priority:

Prevent Horizontal Data Leakage.

Isolation is mandatory.

Security outranks convenience.

Every tenant-scoped resource must assume:

- tenant ownership
- isolated access
- scoped identity

No feature may bypass isolation rules.

---

## 2.2 Modular Monolith

Plinth uses a Modular Monolith.

Not:

- layered monolith
- service soup
- microservices

Modules are bounded contexts.

Each module owns:

- business logic
- models
- actions
- policies
- UI
- events

Modules communicate through:

- Actions
- Events
- explicit contracts

Avoid:

- hidden dependencies
- cross-module coupling
- generic shared services

---

## 2.3 Single Database

Plinth uses:

Single PostgreSQL Database.

Isolation occurs at:

Database Layer
Application Layer
Infrastructure Layer

Benefits:

- simplified operations
- cheaper hosting
- easier analytics
- centralized maintenance

Multi-DB is intentionally avoided.

---

## 2.4 Production-Ready by Default

Architecture assumes:

- real customers
- subscriptions
- failures
- queues
- support operations
- legal retention

No demo-driven architecture.

Every workflow must survive production conditions.

---

## 2.5 Operational Simplicity

Complexity is treated as cost.

Prefer:

- fewer moving parts
- centralized observability
- reusable infrastructure

Reject:

- unnecessary services
- speculative scaling
- orchestration without need

---

# 3. High-Level System Design

Plinth contains two major bounded contexts.

## CENTRAL

The Platform.

Purpose:

Operate the SaaS business.

Access:

domain.com

Responsibilities:

- provisioning
- billing
- subscriptions
- support
- impersonation
- monitoring
- platform operations

CENTRAL owns platform-level concerns.

It never contains customer product logic.

---

## TENANT

The Product.

Purpose:

Deliver software to customers.

Access:

slug.domain.com

Responsibilities:

- IAM
- settings
- branding
- business modules
- audit
- quotas
- integrations

TENANT owns customer-facing software.

It never manages platform billing or global operations.

---

# 4. Project Structure

Plinth follows a strict modular structure.

```text
app/
└── Modules/
    ├── Central/
    │   ├── Provisioning/
    │   ├── Billing/
    │   ├── Operations/
    │   └── Auth/
    │
    └── Tenant/
        ├── Identity/
        ├── Settings/
        ├── Billing/
        ├── Audit/
        └── Feature/
```

No generic:

- Services/
- Helpers/
- Utils/

without strong justification.

Shared code belongs in:

```text
app/Support/
```

Only when:

- framework integration
- infrastructure concern
- genuinely reusable

Avoid dumping domain logic there.

---

# 5. Internal Module Structure

Every module follows:

```text
Module/
├── Actions/
├── DTOs/
├── Models/
├── Policies/
├── React/
├── Events/
├── Listeners/
├── Jobs/
├── Exceptions/
└── Tests/
```

Purpose:
clear ownership.

Avoid:

- giant modules
- controller-driven systems
- domain logic spread randomly

---

# 6. Data Isolation Model

Isolation exists at multiple layers.

Defense in depth.

---

## 6.1 Database Layer

Primary protection:
PostgreSQL Row-Level Security.

Tenant-scoped tables must:

- include tenant_id
- enforce RLS policies

Example:

```sql
tenant_id UUID NOT NULL
```

RLS is primary defense.

Not optional.

---

## 6.2 Application Layer

Secondary protection:
tenant-aware ORM.

Use:

- global scopes
- scoped repositories
- tenant-aware policies

These complement RLS.

They never replace it.

Never trust UI filtering.

---

## 6.3 Security Behavior

Cross-tenant access:
Expected result:
404

Never:
403

Reason:
403 confirms existence.

404 preserves isolation.

Every attempt must:

- log
- audit
- trigger security visibility

Exception:
CrossTenantAccessAttempt

Severity:
critical.

---

# 7. Routing and Tenant Resolution

Platform routing is domain-driven.

CENTRAL:

```text
domain.com
```

TENANT:

```text
slug.domain.com
```

Tenant identification occurs before auth.

Never after.

Supported:

- subdomains
- custom domains

Tenant resolution belongs to tenancy middleware.

Never controllers.

---

# 8. Middleware Pipeline

Middleware order is mandatory.

```text
1 InitializeTenancy
2 ApplyTenantScopes / RLS
3 Authenticate
4 CheckSubscription
```

Reason:
Authentication without tenancy context is unsafe.

Violating this order risks:

- credential leakage
- wrong session scope
- unauthorized access

Architecture must reject alternative order.

---

# 9. Queue Architecture

Queues are:
Tenant-Aware.

Every job must transport:

- tenant_id

Initialization:
before business logic.

Example flow:
1 receive job
2 initialize tenant
3 execute
4 cleanup state

Workers must support:
Graceful Tenant Handover.

Avoid:

- singleton contamination
- shared memory state
- residual tenant context

Queue isolation is mandatory.

---

# 10. Cache and Quota Strategy

Quota enforcement is:
Redis-first.

Flow:
1 cache lookup
2 validation
3 controlled fallback

Avoid:
database hot paths.

Cache stores:

- limits
- usage
- subscription state
- frequently accessed tenant config

Support:
Cache Priming.

Goal:
reduce repeated database access.

---

# 11. Storage Architecture

Storage is isolated.

Namespace:

```text
tenant_{id}/
```

Applies to:

- uploads
- logos
- media
- exports
- generated files

Never use:
shared buckets.

Signed URLs:
must remain tenant-scoped.

---

# 12. Identity and Access Architecture

IAM is tenant-scoped.

Scope:

- users
- roles
- permissions
- sessions
- invitations
- API keys

Global shared roles:
forbidden.

Examples:
Invalid:
Admin

Valid:
Tenant A Admin
Tenant B Admin

Permissions must resolve inside tenant context.

---

# 13. Billing Architecture

Billing belongs to:
CENTRAL.

Never TENANT.

Responsibilities:

- subscriptions
- retries
- dunning
- invoices
- reconciliation
- quota sync

Subscription state controls access.

Invalid subscription:
restricted access.

Billing is business infrastructure.

Not UI logic.

---

# 14. Event Architecture

Events exist for:
real decoupling.

Valid:

- async processing
- notifications
- integrations
- domain separation

Avoid:
event-driven theater.

If synchronous execution is simpler:
prefer Actions.

---

# 15. UI Architecture

Official UI stack:

- React
- Shadcn/UI
- Tailwind

Single design language.

Shared between:

- CENTRAL
- TENANT

Avoid:

parallel frontend stacks.

Reason:

multiple UI systems increase:

- maintenance cost
- design drift
- onboarding friction

---

# 16. Observability

Plinth assumes observability.

Required:

- queue monitoring
- exception tracking
- health visibility
- auditability

Recommended:

- Horizon
- Telescope
- structured logging

Goal:

detect failures early.

---

# 17. Testing Architecture

Testing validates architecture.

Not only features.

Mandatory categories:

## Isolation Tests

Expected:

404.

---

## Quota Tests

Expected:

hard enforcement.

---

## Idempotency Tests

Expected:

no duplicated effects.

---

## Security Tests

Validate:

- tenant scope
- impersonation
- permissions
- RLS

Architecture without tests is untrusted.

---

# 18. Final Rule

If a technical proposal improves speed but weakens:

- tenant isolation
- security
- maintainability
- operational simplicity

the proposal is architecturally invalid.

Architecture exists to protect the system from accidental complexity and unsafe shortcuts.
