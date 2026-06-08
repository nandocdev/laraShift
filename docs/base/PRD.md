# PRD Structure

## 1. Executive Summary

- visión del producto
- arquitectura oficial
- principios SaaS LaraShift
- objetivos de negocio
- non-goals

## 2. Architecture Constraints

Derivado de arquitectura y guardrails:

- Single-DB
- Modular Monolith
- Tenant Isolation
- RLS + Scopes
- middleware order
- tenant-aware queues
- Redis-first quotas
- storage isolation
- auth scoping
- 404 cross-tenant

---

# CENTRAL PRD

## 1. Auth

### Functional Areas

- login central
- logout
- forgot/reset password
- 2FA
- session management
- global admins
- security policies
- access control

### Features

- `/login`
- central dashboard auth
- session invalidation
- forced logout
- concurrent session policies
- audit login history
- password policies
- Fortify integration

### Edge Cases

- brute force
- session hijacking
- expired reset
- MFA recovery

---

## 2. Provisioning

Basado en lifecycle y catálogo actual.

### Functional Areas

- onboarding
- tenant creation
- subdomain reservation
- domain validation
- maintenance
- archival
- deletion

### Features

- prospect registration
- atomic provisioning
- slug reservation
- blacklist
- onboarding wizard
- tenant activation
- maintenance mode
- read-only mode
- archive tenant
- hard delete
- inter-tenant migration

### Edge Cases

- duplicate slug
- partial provisioning
- rollback
- orphan storage
- failed welcome job

---

## 3. Billing

Bounded context central.

### Functional Areas

- plans
- pricing
- subscriptions
- dunning
- taxes
- invoices
- overages
- reconciliation

### Features

- plan matrix
- coupon engine
- checkout orchestration
- subscription sync
- webhook handling
- retries
- suspension
- recovery
- consolidated invoices
- credit balance
- over-usage billing
- tax engine

### Edge Cases

- duplicated webhooks
- partial payment
- payment gateway timeout
- subscription drift
- stale cache

---

## 4. Support

### Functional Areas

- impersonation
- broadcast
- tenant assistance
- overrides

### Features

- audited impersonation
- reason capture
- support sessions
- banners
- notifications
- quota override
- support notes
- tenant history

### Edge Cases

- impersonation abuse
- support escalation
- override expiration

---

## 5. Infrastructure

### Functional Areas

- telemetry
- queues
- cache
- rate limiting
- observability

### Features

- health monitor
- Horizon
- queue isolation
- cache priming
- noisy neighbor detection
- plan rate limits
- performance dashboards
- incident logs

### Edge Cases

- queue contamination
- Redis outage
- cache poisoning
- noisy tenant

---

# TENANT PRD

## 1. Identity

IAM bounded context.

### Functional Areas

- auth
- roles
- permissions
- invitations
- MFA
- API keys

### Features

- tenant login
- role builder
- permission matrix
- invite lifecycle
- TTL invites
- concurrent session limits
- password policies
- mandatory MFA
- API key management

### Edge Cases

- leaked invite
- orphan permissions
- cross-tenant auth

---

## 2. Settings

### Functional Areas

- branding
- localization
- domains
- configuration

### Features

- white-label
- logos
- theme
- custom domains
- SSL validation
- timezone
- language
- currencies
- SMTP gateway

### Edge Cases

- invalid SSL
- DNS mismatch
- broken SMTP

---

## 3. Audit

Governance context.

### Functional Areas

- audit
- compliance
- recovery
- portability

### Features

- immutable logs
- export
- GDPR anonymization
- soft delete
- trash recovery
- change history

### Edge Cases

- tampering attempts
- large exports
- recovery conflicts

---

## 4. Quotas

### Functional Areas

- limits
- usage
- upgrades
- enforcement

### Features

- usage dashboard
- hard limit checks
- alerts
- threshold notifications
- upgrade triggers
- plan changes
- payment methods
- invoice history

### Edge Cases

- stale quota cache
- race conditions
- quota desync

---

## 5. Integrations

### Functional Areas

- inbound
- outbound
- automation

### Features

- outgoing webhooks
- retries
- secret signing
- API integrations
- Zapier-compatible events
- webhook logs
- delivery history

### Edge Cases

- replay attacks
- dead endpoints
- duplicate deliveries

---

# Recomendación

No generar un PRD monolítico único.

Mejor:

1. `PRD_Central.md`
2. `PRD_Tenant.md`

Cada módulo con:

- Overview
- Business Goal
- User Stories
- Functional Requirements
- Non-Functional Requirements
- UX Flow
- Permissions Matrix
- Data Model
- Events
- API/Actions
- Edge Cases
- Security
- Testing
- Acceptance Criteria
- Future Extensions

Confirmación necesaria antes de generar:
