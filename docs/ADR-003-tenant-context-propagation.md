# ADR-003: Tenant Context Propagation Strategy

**Status:** Accepted
**Date:** 2026-06-30
**Deciders:** Architecture Team

---

## Context

In a multi-tenant SaaS platform, every async boundary (queues, events, broadcasts) must carry tenant context to ensure correct data isolation. Without explicit propagation, workers have no way to know which tenant a job belongs to, leading to data leakage across tenants.

The existing `stancl/tenancy` package provides `QueueTenancyBootstrapper` which serializes tenant state into job payloads. However, this implicit approach has limitations:

1. **No visibility** — the `tenant_id` is not visible in job payloads or logs
2. **Coupling** — workers must have the bootstrapper enabled
3. **No graceful handover** — jobs can't easily switch tenants within the same worker

## Decision

We adopt an **explicit propagation strategy** using a `TenantContext` value object embedded in every async job.

### Core Components

1. **`TenantContext` value object** (`Shared/Tenancy/ValueObjects/TenantContext.php`)
   - Immutable value object with `tenantId`, `tenantSlug`
   - `fromCurrent()` — captures current tenancy context
   - `fromArray()` — reconstructs from serialized data
   - `initialize()` — boots tenancy for this context
   - Serializes to/from arrays for queue transport

2. **`AbstractTenantJob` base class** (`Shared/Tenancy/Jobs/AbstractTenantJob.php`)
   - Every tenant job MUST extend this class
   - Constructor receives `TenantContext`
   - Automatically stores `tenantId`, `tenantSlug`, `tenantContext` as public properties
   - `initializeTenancy()` — called at the start of `handle()` to set up tenant state
   - `queueName()` — resolves the correct queue bucket based on tenant ID hash and priority
   - `failed()` — logs with tenant context on failure

### Data Flow

```
[Action/Controller]
    │
    ├── TenantContext::fromCurrent()
    │       └── Captures tenant_id + tenant_slug
    │
    ├── new ProcessSomethingJob($context, $data)
    │       └── Constructor stores context in public properties
    │
    ├── dispatch($job)->onQueue($job->queueName())
    │       └── Queue name determined by tenant hash + priority
    │
    └── [Worker]
            │
            ├── initializeTenancy()
            │       ├── Reconstruct TenantContext from array
            │       ├── Find tenant by ID via TenantResolver::findById()
            │       └── tenancy()->initialize($tenant)
            │
            └── handle()
                    └── Business logic executes inside tenant context
```

### Queue Routing

Jobs are routed to tenant-specific queue buckets to prevent noisy-neighbor problems:

```
Hashing: crc32($tenantId) % 5 + 1 → bucket 1-5

Queues: tenant.b{bucket}.{priority}
  - priority >= 5 → high
  - priority 3-4 → default
  - priority <= 2 → low
```

### Benefits

1. **Explicit** — tenant_id is visible in job payloads, logs, and monitoring
2. **Portable** — works with any queue driver (Redis, SQS, database)
3. **Debuggable** — failed jobs carry complete tenant context
4. **Graceful handover** — workers can cleanly switch tenants between jobs
5. **Independent of bootstrappers** — works with or without `QueueTenancyBootstrapper`

### Trade-offs

1. **Serialization overhead** — each job carries a small additional payload (~100 bytes)
2. **Discipline required** — every new job must extend `AbstractTenantJob` and call `initializeTenancy()`
3. **No auto-magic** — unlike bootstrapper-based approaches, this requires explicit handling

## Consequences

1. All new async jobs MUST extend `AbstractTenantJob`
2. Existing jobs SHOULD be migrated to use `AbstractTenantJob` during regular maintenance
3. `TenantResolver` is used for job context initialization to leverage caching
4. The `QueueTenancyBootstrapper` from stancl/tenancy remains enabled as a defense-in-depth layer
