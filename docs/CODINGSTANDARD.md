# CodingStandards.md

# Plinth — Coding Standards

## Purpose

This document defines how code is written inside Plinth.

Architecture defines:

what we build.

Coding standards define:

how we build it.

These rules exist to:

* reduce inconsistency
* prevent architectural drift
* improve maintainability
* keep code production-ready

Code is evaluated on:

* clarity
* predictability
* safety
* simplicity

Not cleverness.

---

# 1. General Principles

Plinth follows:

* explicit code
* small units
* low coupling
* high readability

Prefer:

boring and predictable code.

Avoid:

* magic
* hidden behavior
* framework abuse
* unnecessary abstraction

Code should be easy to debug at 3AM.

---

# 2. File Organization

Code must respect module ownership.

Never organize by technical layer alone.

Avoid:

```text id="9crx8j"
app/Services
app/Helpers
app/Managers
```

Default structure:

```text id="kq9zjv"
Modules/
```

Each module owns:
* logic
* models
* React Components (UI)
* policies
* events

Modular UI Rule:
React pages and components specific to a module MUST reside within `app/Modules/{Context}/{Module}/React/`.
Avoid placing module-specific views in the global `resources/js/pages` directory.

Shared infrastructure belongs only in:

```text id="zjmbs7"
app/Support
```

When genuinely reusable.

Not as dumping ground.

---

# 3. Naming Rules

Naming must be explicit.

Prefer:

descriptive names.

Avoid:

abbreviations.

---

## Classes

Use nouns.

Examples:

Good:

```php id="vrvbg5"
Invoice
TenantQuota
SubscriptionPlan
```

Bad:

```php id="3l0rq0"
Mgr
Helper
Processor
```

---

## Actions

Pattern:

Verb + Subject + Action

Examples:

```php id="67gv3u"
CreateTenantAction
SyncSubscriptionAction
GenerateInvoiceAction
```

Avoid:

```php id="g3t4my"
TenantManager
BillingService
```

---

## DTOs

Suffix:

Data

Examples:

```php id="4h2lyd"
CreateTenantData
UpdateBrandingData
```

---

## Jobs

Suffix:

Job

Examples:

```php id="l0zx3z"
SendInvoiceJob
SyncQuotaJob
```

---

## Events

Past tense.

Examples:

```php id="3krq0u"
TenantProvisioned
SubscriptionUpdated
InvoiceGenerated
```

---

## Listeners

Describe reaction.

Examples:

```php id="7ktv3e"
SendWelcomeEmail
WarmTenantCache
```

---

# 4. Actions

Business logic belongs in Actions.

Not controllers.

Not React.

Not models.

Action pattern is mandatory.

---

## Structure

Actions should be:

* final
* readonly
* single responsibility

Example:

```php id="2hrqz9"
final readonly class CreateTenantAction
{
    public function execute(CreateTenantData $data): Tenant
    {
        return DB::transaction(function () use ($data) {
            // logic
        });
    }
}
```

---

## Rules

Actions:

* receive DTOs
* return typed results
* own business logic
* handle transactions explicitly

Avoid:

* HTTP dependency
* Request objects
* session access
* UI concerns

Actions must remain reusable.

---

# 5. DTO Standards

Arrays are forbidden for business payloads.

Use typed DTOs.

Preferred:

spatie/laravel-data.

Example:

```php id="zqf3w5"
final class CreateUserData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

Benefits:

* validation
* typing
* predictable inputs

Avoid:

```php id="j0k2tr"
array $payload
```

Ambiguous payloads create bugs.

---

# 6. Controllers

Controllers are orchestration only.

Responsibilities:

* authorize
* validate
* call action
* return response

Nothing else.

Good:

```php id="x3t9pq"
return $action->execute($data);
```

Bad:

* business rules
* DB writes
* branching logic

Fat controllers are rejected.

---

# 7. React Components

React handles:
UI state.

Not domain logic.

Responsibilities:
* state
* interaction
* presentation

Business logic:
delegated to Actions.

Avoid:
* persistence logic
* quota logic
* billing rules

Keep components small.

Prefer composition.

---

# 8. Models

Models represent persistence.

Not business orchestration.

Responsibilities:
* relations
* casts
* scopes
* lightweight helpers

Avoid:
* workflows
* orchestration
* large methods

Models must remain readable.

---

# 9. Tenant-Aware Models

Tenant models require:

* tenant_id
* tenant scope
* ownership protection

Example:

```php id="n4z2cf"
protected static function booted(): void
{
    static::addGlobalScope(new TenantScope());
}
```

Scopes complement:

RLS.

Never replace it.

Tenant ownership must be enforced.

---

# 10. Transactions

Multi-step writes:

must use transactions.

Required when:

* multiple tables
* provisioning
* billing
* quota mutations

Example:

```php id="0zv1aq"
DB::transaction(function () {
    //
});
```

Avoid:

partial writes.

Atomicity matters.

---

# 11. Exception Handling

Use domain exceptions.

Avoid:

generic exceptions.

Bad:

```php id="9h2xvt"
throw new Exception();
```

Prefer:

```php id="7px0vk"
throw new QuotaExceededException();
```

Exceptions must communicate:

* cause
* business meaning
* expected handling

---

# 12. Event Usage

Events are optional.

Not mandatory.

Use when:

* async work
* integrations
* module separation

Avoid:

event chains.

Bad:

Event → Listener → Event → Listener

Prefer:

direct Action call when simpler.

Avoid:

event-driven theater.

---

# 13. Queue Standards

All Jobs:

Tenant-Aware.

Mandatory:

tenant_id transport.

Example:

```php id="smpj3d"
public function __construct(
    public string $tenantId
) {}
```

Handle:

```php id="74k8dw"
tenancy()->initialize($this->tenantId);
```

Cleanup required.

Avoid:

context leakage.

Workers process multiple tenants.

Assume hostile state.

---

# 14. Database Standards

Migrations must be explicit.

Prefer:

clear definitions.

Tenant tables:

include tenant_id.

Prefer indexes:

```sql id="5d1a9w"
(tenant_id, id)
(tenant_id, created_at)
(tenant_id, foreign_key)
```

Avoid:

missing indexes.

Common failures:

* N+1
* full scans
* unbounded queries

Performance matters.

---

# 15. Query Standards

Prefer:

Eloquent + scopes.

Avoid:

raw SQL unless justified.

Always review:

* ownership
* indexes
* eager loading

Prevent:

N+1 queries.

Good:

```php id="k5wx2r"
User::with('roles')
```

Bad:

loop queries.

---

# 16. Authorization

Authorization is:

server-side.

Never trust UI.

Use:

* Policies
* Gates
* scoped checks

Tenant ownership:

mandatory.

Cross-tenant result:

404.

Never:

403.

---

# 17. Logging

Logs exist for operations.

Not debugging spam.

Log:

* failures
* security events
* billing events
* impersonation
* tenant anomalies

Avoid:

noise.

Critical events:

structured logging.

---

# 18. Testing Standards

Code without tests is incomplete.

Minimum:

Feature tests.

Required:

---

## Isolation Tests

Two tenants.

Expected:

404.

---

## Quota Tests

Expected:

hard limit.

---

## Security Tests

Validate:

* permissions
* ownership
* policies
* impersonation

---

## Idempotency Tests

Critical for:

* webhooks
* retries
* queues

Repeated execution:

no duplicated side effects.

---

# 19. Dependency Rules

Dependencies require justification.

Default:

minimal dependencies.

Questions:

* does this reduce complexity?
* does this improve maintainability?
* does Laravel already solve this?

Avoid:

dependency inflation.

---

# 20. Code Review Rules

Code review evaluates:

* correctness
* architecture fit
* tenant safety
* simplicity

Reject code that:

* bypasses tenancy
* introduces fat controllers
* hides logic
* weakens security
* increases accidental complexity

Working code alone is insufficient.

---

# Final Rule

Readable code outlives clever code.

If an implementation is difficult to:

* understand
* debug
* audit
* secure

it is not acceptable inside Plinth.