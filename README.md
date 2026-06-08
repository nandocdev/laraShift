# 🧱 LaraShift — Enterprise SaaS Modular Monolith

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16+-4169E1?style=for-the-badge&logo=postgresql)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

**LaraShift** is a production-grade SaaS Modular Monolith built with Laravel 13, designed for teams building high-security, scalable B2B products. It offers a structured foundation for multi-tenancy with deep integration for provisioning, billing, and identity management.

---

## 🏛️ Architectural Philosophy

LaraShift is built upon three non-negotiable pillars:

1.  **Modular Monolith:** Functionality is strictly organized into autonomous Bounded Contexts (`Central` and `Tenant`) within the `app/Modules/` directory, maintaining clean separation without microservice overhead.
2.  **Single Database (RLS):** Uses **PostgreSQL Row-Level Security (RLS)** as the primary defense to prevent horizontal data leakage between tenants.
3.  **Production-Ready:** Every workflow, from tenant onboarding to automated billing dunning and support impersonation, is designed for high-availability environments.

---

## 🚀 Key Functionalities

### Central Platform (Admin)
- **Provisioning:** Atomic multi-step tenant creation (subdomain, DB schema, initial admin, billing setup).
- **Billing Management:** Multi-gateway billing engine (Stripe, PagueloFacil, dLocal) with support for custom subscription plans and global invoice auditing.
- **Features & Quotas:** Dynamic feature flags and tenant-specific quotas (with automated threshold notifications).
- **Marketing & Content:** Built-in drag-and-drop **Landing Page Builder** with reusable block components (Hero, Features, Pricing, Testimonials).
- **Support Operations:** Audited tenant impersonation, global broadcast system (email/in-app banners), and support bitacora per tenant.
- **Branding & Settings:** Global platform styling, color presets, and branding configuration.

### Tenant-Specific Operations
- **Identity & Access Management:** Tenant-aware RBAC, user management, API Key management with granular scopes, and MFA support (TOTP/Passkeys).
- **Security & Audit:** Immutable audit logging of all sensitive actions, with export capabilities.
- **Tenant Customization:** Per-tenant branding (logos, colors), SMTP configuration (BYO-SMTP), and regional settings (timezones, locales, currencies).

---

## 🛠️ Official Stack

-   **Backend:** Laravel 13, PHP 8.3+, PostgreSQL 16+
-   **Multi-tenancy:** `stancl/tenancy` (Single-DB RLS)
-   **UI:** Livewire 4, Flux UI, Tailwind CSS
-   **Identity:** Fortify, Spatie Permission
-   **Billing:** Cashier (Stripe), Custom Driver Manager
-   **Observability:** Laravel Horizon, Activitylog

---

## 📂 Project Structure

```text
app/
└── Modules/
    ├── Central/           # Platform Business (Auth, Billing, Provisioning, Landings)
    └── Tenant/            # Customer Product (Audit, Identity, Settings)
    └── Shared/            # Contracts, Events, Infrastructure, Models
```

---

## 📜 Documentation

Detailed documentation can be found in the `docs/` directory:
- [Architecture & Isolation](docs/ARCHITECTURE.md)
- [Coding Standards](docs/CODINGSTANDARD.md)
- [Vision & Goals](docs/VISION.md)

---

## ⚖️ License

LaraShift is open-sourced software licensed under the [MIT license](LICENSE).
