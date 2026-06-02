# Changelog

All notable changes to **Plinth** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-beta.1] - 2026-06-02

### Added
- **Core Architecture:**
  - Implementation of Modular Monolith structure in `app/Modules`.
  - Migration of all key models (`CentralUser`, `User`, `Tenant`, `Domain`) to **UUIDs** for unified traceability.
  - Setup of **PostgreSQL Row-Level Security (RLS)** foundations and Trait `BelongsToTenant`.
- **Infrastructure:**
  - Integrated `stancl/tenancy` for multi-tenancy logic (Single-DB mode).
  - Configured `spatie/laravel-activitylog` for automated audit trails.
  - Setup `laravel/cashier` for centralized billing management.
  - Integrated `laravel/horizon` and `spatie/laravel-medialibrary`.
- **Central Auth Module:**
  - Dedicated authentication guard `central` for platform admins.
  - Full authentication lifecycle: Login, Logout, Forgot/Reset Password.
  - Audit logging for all authentication events (success, failed, resets).
  - Admin Dashboard with system health and tenant overview.
- **Central Provisioning Module:**
  - Atomic tenant onboarding: creates Tenant, Domain, Database structure, and Admin user in one step.
  - Tenant management UI: list, create, and action menus.
  - CLI Tool: `php artisan provision:tenant` for rapid testing.
- **UI/UX:**
  - Official integration of **Flux UI** and **Livewire 4**.
  - Dedicated layouts for `auth`, `app`, and `central` contexts.
  - Native dark mode support.
- **AI Agents:**
  - Custom Gemini CLI agents for specialized tasks (Architect, Backend, Frontend, QA, Security).

### Fixed
- Corrected attribute conflicts in Flux UI components (`badge`, `text`).
- Fixed route conflicts in multi-tenant environment by using robust URL generation.
- Resolved factory discovery issues for modular models.

---
*Initial release of the Plinth SaaS Boilerplate foundation.*
