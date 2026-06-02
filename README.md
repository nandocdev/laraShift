# 🧱 LaraShift — Enterprise SaaS Boilerplate

[![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=for-the-badge&logo=php)](https://www.php.net)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-16+-4169E1?style=for-the-badge&logo=postgresql)](https://www.postgresql.org)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)

**LaraShift** is a production-grade SaaS Multi-tenant Boilerplate built with Laravel 13, designed for teams building high-security, scalable B2B products. It eliminates the need to rebuild repetitive infrastructure by providing a robust, opinionated foundation.

---

## 🏛️ Architectural Philosophy

LaraShift is built upon three non-negotiable pillars:

1.  **Modular Monolith:** Functionality is organized into isolated Bounded Contexts (`Central` and `Tenant`), avoiding the complexity of microservices while maintaining clean separation.
2.  **Single Database (RLS):** Uses **PostgreSQL Row-Level Security (RLS)** as the primary defense to prevent horizontal data leakage. Security outranks convenience.
3.  **Production-Ready by Default:** Every workflow is designed to survive real-world production conditions (queues, dunning, legal retention, and heavy concurrency).

---

## 🛠️ Official Stack

-   **Backend:** Laravel 13, PHP 8.3+, PostgreSQL 16+
-   **Multi-tenancy:** [stancl/tenancy](https://tenancyforlaravel.com/) (Single-DB RLS Mode)
-   **UI:** [Livewire 4](https://livewire.laravel.com/), [Flux UI](https://fluxui.dev/), [Tailwind CSS](https://tailwindcss.com/)
-   **Identity:** [Laravel Fortify](https://laravel.com/docs/fortify), [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) (Tenant-aware)
-   **Billing:** [Laravel Cashier (Stripe)](https://laravel.com/docs/billing)
-   **Audit:** [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
-   **Observability:** [Laravel Horizon](https://laravel.com/docs/horizon)

---

## 📂 Project Structure

LaraShift follows a strict modular structure under `app/Modules`:

```text
app/
└── Modules/
    ├── Central/           # Platform Business (Provisioning, Billing, Operations)
    │   ├── Auth/
    │   └── Provisioning/
    │
    └── Tenant/            # Customer Product (Identity, Settings, CRM, Features)
        └── Identity/
```

Inside each module, you will find a consistent structure: `Actions`, `DTOs`, `Models`, `Livewire`, `Providers`, and `Routes`.

---

## 🚀 Getting Started

### 1. Prerequisites
- PHP 8.3+
- PostgreSQL 16+ (with Superuser privileges to enable RLS)
- Redis

### 2. Installation

```bash
git clone https://github.com/yourusername/LaraShift.git
cd LaraShift
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

### 3. Database & Seeding

```bash
# Ensure your PostgreSQL user has permissions to create databases/schemas if needed
php artisan migrate:fresh --seed
```

### 4. Default Credentials
- **Central Admin:** `admin@larashift.test` / `password`
- **Acme Tenant Admin:** `admin@acme.test` / `password`

---

## ✨ Core Features

-   [x] **Atomic Provisioning:** Create a tenant, domain, and admin user in a single transactional step.
-   [x] **Unified ID Strategy:** Uses UUIDs across all tables (Central & Tenant) for global traceability and `activity_log` compatibility.
-   [x] **Audit Logging:** Built-in tracking for authentication, provisioning, and security events.
-   [x] **Robust IAM:** Tenant-scoped roles and permissions with MFA support.
-   [x] **Modern UI:** Full Flux UI integration with native dark mode and responsive design.

---

## 📜 Documentation

Detailed documentation can be found in the `docs/` directory:
- [Architecture & Isolation](docs/ARCHITECTURE.md)
- [Coding Standards](docs/CODINGSTANDARD.md)
- [Vision & Goals](docs/VISION.md)

---

## ⚖️ License

LaraShift is open-sourced software licensed under the [MIT license](LICENSE).
