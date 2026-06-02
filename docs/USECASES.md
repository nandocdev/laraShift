# UseCases.md

# Plinth — Use Cases

## Purpose

This document defines the functional capabilities of Plinth.

Use cases are grouped by bounded context.

They define:

* responsibilities
* ownership
* behavioral expectations
* architectural boundaries

Use cases are contracts.

They do not prescribe UI or implementation details.

Implementation may evolve.

Responsibilities do not.

---

# 1. Context Separation

Plinth contains two primary contexts:

1. CENTRAL
2. TENANT

Rule:

Capabilities belong to one context only.

Avoid duplicated ownership.

---

# 2. CENTRAL Context

CENTRAL operates the SaaS platform itself.

Access:

```text id="8x5m6y"
domain.com
```

CENTRAL owns:

* platform business
* provisioning
* billing
* operations
* support
* global governance

CENTRAL never owns:

* customer business data
* customer workflows
* tenant feature logic

---

# 3. CENTRAL Modules

---

# 3.1 Provisioning & Tenant Lifecycle

Purpose:

Manage the full lifecycle of a tenant.

---

## CU-C1.1 Prospect Registration

Capabilities:

* capture tenant prospect data
* validate uniqueness
* prepare onboarding

Validation:

* unique slug
* reserved domains
* normalized identifiers

Failure examples:

* duplicated slug
* blocked subdomain

---

## CU-C1.2 Atomic Provisioning

Purpose:

Create tenant safely.

Must execute atomically.

Creates:

* tenant record
* domain/subdomain
* first admin account
* storage namespace

Expected:

all-or-nothing.

Partial provisioning is invalid.

---

## CU-C1.3 Domain Reservation

Purpose:

Protect platform namespace.

Manage:

reserved slugs.

Examples:

```text id="uwgv0t"
admin
api
root
support
```

Must reject:

restricted identifiers.

---

## CU-C1.4 Tenant Maintenance

Purpose:

Operate individual tenants.

Capabilities:

* maintenance mode
* read-only mode
* controlled access restriction

Scope:

single tenant only.

Never platform-wide unless explicitly requested.

---

## CU-C1.5 Inter-Tenant Migration

Edge case.

Purpose:

Move data between tenants.

Examples:

* mergers
* acquisitions
* consolidation

Requirements:

* auditability
* transactional integrity
* ownership validation

High risk.

Never implicit.

---

## CU-C1.6 Hard Delete

Purpose:

Permanent removal.

Includes:

* records
* storage
* residual artifacts

Requirements:

* retention policy
* legal compliance
* irreversible execution

Soft delete is insufficient.

---

## CU-C1.7 Tenant Archive

Purpose:

Cold storage.

Use when:

tenant becomes inactive.

Expected:

* frozen state
* preserved integrity
* reduced operational cost

---

# 3.2 Billing, Plans & Taxes

Purpose:

Operate SaaS monetization.

Billing belongs exclusively to CENTRAL.

---

## CU-C2.1 Pricing Matrix

Capabilities:

* plans
* billing cycles
* feature hierarchy
* currency handling

Plans define:

commercial limits.

Not authorization.

---

## CU-C2.2 Coupons & Discounts

Capabilities:

* percentage discounts
* fixed discounts
* promotional periods
* volume pricing

Must support:

expiration and constraints.

---

## CU-C2.3 Tax Configuration

Purpose:

Dynamic taxation.

Based on:

tenant jurisdiction.

Examples:

* VAT
* local taxes
* withholding

Must remain configurable.

Not hardcoded.

---

## CU-C2.4 Dunning & Retries

Purpose:

Recover failed payments.

Flow:

1 notify
2 retry
3 suspend
4 cancel

Configurable policy.

---

## CU-C2.5 Credit Reconciliation

Purpose:

Handle balance adjustments.

Examples:

* refunds
* credits
* overpayment

Maintain:

audit consistency.

---

## CU-C2.6 Invoice Generation

Purpose:

Produce legal billing artifacts.

Formats:

* PDF
* regional requirements

Expected:

historical integrity.

Invoices are immutable.

---

# 3.3 Support & Operations

Purpose:

Enable platform management.

---

## CU-C3.1 Audited Impersonation

Purpose:

Support access.

Requirements:

* operator identity
* reason
* timestamps
* audit log

Impersonation without audit:

invalid.

---

## CU-C3.2 Global Communications

Purpose:

Broadcast information.

Targets:

* all tenants
* tenant admins
* filtered audiences

Examples:

* maintenance notices
* incident banners

---

## CU-C3.3 Global Health Monitor

Purpose:

Platform observability.

Visibility:

* latency
* failures
* queue health
* tenant impact

Centralized operations.

---

## CU-C3.4 Quota Override

Purpose:

Support exceptions.

Allows:

manual quota increase.

Scope:

single tenant.

Does not alter:

base plan definition.

---

# 4. TENANT Context

TENANT delivers the customer product.

Access:

```text id="m8x7fc"
slug.domain.com
```

TENANT owns:

* users
* business workflows
* tenant configuration
* integrations
* branding
* usage

TENANT never owns:

* platform billing
* global support
* provisioning

---

# 5. TENANT Modules

---

# 5.1 Identity & Access Management

Purpose:

Tenant-scoped identity.

All IAM is tenant-bound.

---

## CU-T1.1 Custom Roles

Capabilities:

* create roles
* assign permissions
* granular access

Roles:

tenant-scoped only.

Global roles forbidden.

---

## CU-T1.2 User Invitations

Capabilities:

* invitation flow
* email validation
* token expiration

Expected:

secure onboarding.

---

## CU-T1.3 Concurrent Sessions

Purpose:

Limit device access.

Capabilities:

* session limits
* forced logout
* active session visibility

---

## CU-T1.4 Password Policy

Capabilities:

* complexity
* rotation
* minimum requirements

Tenant-defined.

---

## CU-T1.5 Forced MFA

Purpose:

Tenant security policy.

Admin may require:

2FA.

Scope:

tenant-wide.

---

## CU-T1.6 API Keys

Purpose:

External integration.

Requirements:

* scoped credentials
* revocation
* auditability

Never global.

---

# 5.2 Subscription, Quotas & Usage

Purpose:

Customer self-management.

---

## CU-T2.1 Usage Dashboard

Capabilities:

* quota visibility
* usage metrics
* remaining capacity

Near real-time.

---

## CU-T2.2 Self-Service Upgrade

Capabilities:

* upgrade
* downgrade
* checkout

No support dependency.

Billing logic still belongs to CENTRAL.

---

## CU-T2.3 Payment Methods

Capabilities:

* add
* remove
* select

Secure tokenized handling.

Never raw card storage.

---

## CU-T2.4 Invoice History

Purpose:

Historical visibility.

Tenant accesses:

previous invoices.

Read-only.

---

## CU-T2.5 Quota Alerts

Capabilities:

threshold notifications.

Examples:

* 50%
* 80%
* 95%

Configurable.

---

# 5.3 Governance & Compliance

Purpose:

Tenant trust and traceability.

---

## CU-T3.1 Audit Log

Purpose:

Immutable business audit.

Tracks:

* who
* what
* when

Critical models only.

---

## CU-T3.2 Data Export

Purpose:

Portability.

Formats:

* CSV
* JSON

Tenant-owned data only.

---

## CU-T3.3 Trash Recovery

Purpose:

Controlled recovery.

Supports:

soft-delete restoration.

Retention-based.

---

## CU-T3.4 Data Anonymization

Purpose:

Compliance.

Allows:

record anonymization.

Examples:

* privacy requests
* GDPR

Selective.

Not destructive by default.

---

# 5.4 Settings, Branding & Integrations

Purpose:

Tenant customization.

---

## CU-T4.1 Custom Domains

Capabilities:

* domain mapping
* SSL validation
* domain ownership checks

Tenant-scoped.

---

## CU-T4.2 White-label Branding

Capabilities:

* logos
* colors
* visual identity

Applies to:

* UI
* email

---

## CU-T4.3 Localization

Capabilities:

* timezone
* currency
* language
* numeric formats

Per tenant.

---

## CU-T4.4 Outgoing Webhooks

Purpose:

Event integration.

Requirements:

* retry handling
* signing
* idempotency

External delivery.

---

## CU-T4.5 SMTP Gateway

Purpose:

Tenant-owned email delivery.

Supports:

* SendGrid
* SES
* custom SMTP

Tenant credentials remain isolated.

---

# 6. Infrastructure Use Cases

Invisible but mandatory.

---

## CU-X1 Storage Isolation

Guarantee:

tenant-scoped storage.

Pattern:

```text id="c5yknj"
tenant_{id}/
```

Signed URLs:

isolated.

---

## CU-X2 Cache Priming

Purpose:

Performance.

Preload:

* tenant config
* quotas
* subscription state

Avoid repeated DB access.

---

## CU-X3 Graceful Tenant Handover

Queue requirement.

Workers must:

* clear memory
* reset state
* avoid context leakage

Cross-tenant residue:

critical failure.

---

## CU-X4 Rate Limiting by Plan

Purpose:

Protect platform.

Limits vary by:

plan tier.

Examples:

* Free
* Pro
* Enterprise

Protect against:

noisy neighbors.

---

# 7. Standard Exceptions

Plinth standardizes business failures.

---

## TenantSuspendedException

Access blocked.

Cause:

billing or suspension.

---

## QuotaExceededException

Action denied.

Cause:

plan limits exceeded.

---

## CrossTenantAccessAttempt

Critical security event.

Cause:

invalid ownership access.

Expected:

404 + audit log.

---

## PlanFeatureAccessDenied

Cause:

feature unavailable.

Expected:

controlled denial.

---

# Final Rule

A use case is valid only if:

* ownership is clear
* tenant boundaries remain intact
* implementation preserves isolation
* architecture remains simple

Features do not justify breaking platform guarantees.