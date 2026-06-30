# ADR-004: Session Strategy

**Status:** Accepted
**Date:** 2026-06-30
**Deciders:** Architecture Team

---

## Context

The platform serves both Central (SaaS operators) and Tenant (customer) contexts. Each has different session lifecycle requirements:

- **Central sessions**: long-lived admin sessions, concurrent limits, impersonation audit
- **Tenant sessions**: user sessions scoped to tenant, revocable by tenant admin, concurrent limits per plan

Additionally, the platform needs to support both stateful (server-side) sessions for web UIs and stateless tokens for API access.

## Decision

We use a **dual strategy**: stateful sessions for web UIs (Laravel session driver) with explicit session tracking records, and **stateless API tokens** (Sanctum) for programmatic access.

### Central Sessions

Uses `CentralSession` model with:
- `session_id` for correlating with Laravel's session store
- `issued_at`, `expires_at`, `revoked_at` for lifecycle management
- Stored in `central_sessions` table
- Concurrent limit enforced by `RevokeOldestSessionAction` (default: 3 active sessions)

### Tenant Sessions

Uses `TenantSession` model with:
- Same structure as CentralSession but tenant-scoped
- Stored in `tenant_sessions` table
- Refresh token hash (`refresh_token_hash`) for token rotation
- Concurrent limit configurable via plan quotas (`max_sessions`)
- Admin revocation via `InvalidateUserSessionsAction`

### Session Lifecycle

```
User Login
    │
    ├── Laravel session created (stateful)
    ├── TenantSession/CentralSession record created
    ├── Refresh token generated (SecureRandom, hashed)
    │
    ├── Concurrent limit check:
    │       └── Revoke oldest if exceeded
    │
    ├── Activity logged (audit)
    │
    └── Session continues...

Admin Revocation
    │
    ├── TenantSession marked revoked_at
    ├── Laravel session deleted from store (if DB driver)
    ├── Activity logged (audit)
    └── User redirected to login on next request

Logout
    ├── TenantSession marked revoked_at
    ├── Session invalidated
    └── Activity logged
```

### Token Rotation

For API tokens (Sanctum):
- Short-lived access tokens (15-60 minutes)
- Longer-lived refresh tokens stored as hash in `TenantSession.refresh_token_hash`
- On refresh: old token hashed and compared, new token issued, old revoked
- Reuse detection: if an already-revoked refresh token is presented, all sessions for that user are revoked

### Benefits

1. **Explicit tracking** — all session activity visible in the database
2. **Admin control** — tenant admins can force-logout any user
3. **Plan enforcement** — concurrent session limits configurable per plan
4. **Auditability** — every login, logout, and revocation logged
5. **Dual approach** — stateful for web UX, stateless for API

### Trade-offs

1. **Storage overhead** — each session creates a DB record (negligible)
2. **DB session driver needed** — for full lifecycle management, the `database` session driver is required
3. **No auto-cleanup** — expired sessions need periodic pruning via scheduled job

## Consequences

1. New tenant user sessions MUST create a `TenantSession` record
2. `EnsureTenantSessionLimitAction` should be called after user login
3. `InvalidateUserSessionsAction` exposed to tenant admin UI
4. Session cleanup job should be scheduled (retention: 30 days)
5. API authentication uses Sanctum tokens, not sessions
