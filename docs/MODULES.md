# Bounded Contexts

Separación por dominio funcional:

```text
Central (Plataforma SaaS)
Tenant (Producto Reservas)
```

Los módulos SaaS globales ya existen y se reutilizan.

---

# CENTRAL (sin cambios estructurales)

Estos módulos siguen siendo responsabilidad del SaaS, no del producto.

```text
Modules/Central
├── Auth
├── Provisioning
├── Billing
├── Infrastructure
├── Support
└── Monitoring
```

Responsabilidades:

### Provisioning

- onboarding tenant
- slug/domain
- bootstrap negocio
- tenant lifecycle

### Billing

- planes
- cuotas
- checkout
- suspensión
- webhooks
- dunning

Billing sigue siendo **CENTRAL bounded context**. Nunca moverlo al producto.

### Support / Ops

- impersonación
- soporte
- health
- overrides

---

# TENANT PRODUCT — Booking Platform

```text
Modules/Tenant
├── Identity
├── Settings
├── Audit
├── Quotas
├── Catalog
├── Locations
├── Staff
├── Scheduling
├── Booking
├── CRM
├── Availability
├── Notifications
├── PublicSite
├── Payments
├── Reports
└── Integrations
```

---

# 1. Identity (IAM)

Reutilizado.

```text
Tenant/Identity
```

Responsabilidad:

- usuarios
- roles
- permisos
- sesiones
- invitaciones
- 2FA

Ejemplos:

- Owner
- Manager
- Receptionist
- Professional
- Cashier

Tenant-aware con permisos scoped.

---

# 2. Settings

Reutilizado.

```text
Tenant/Settings
```

Responsabilidad:

- branding
- timezone
- idioma
- SMTP
- dominio
- white-label

Crítico para reservas:

- zona horaria
- moneda
- horarios regionales
- branding booking page

---

# 3. Audit

Reutilizado.

```text
Tenant/Audit
```

Responsabilidad:

- activity log
- cambios críticos
- compliance

Eventos auditables:

- cancelación manual
- override horario
- reasignación staff
- reembolso
- no-show editado

---

# 4. Quotas

Reutilizado.

```text
Tenant/Quotas
```

Redis-first.

Límites típicos:

- sucursales
- staff
- reservas mensuales
- SMS
- storage
- integraciones

---

# 5. Catalog

Primer módulo propio.

Define **qué vende o agenda el negocio**.

```text
Tenant/Catalog
```

Universal.

Sirve para:

- abogado
- tutor
- barbería
- spa
- consultoría

Subdominio:

```text
Services
ServiceCategories
ServiceBundles
PricingRules
```

Casos:

### Individual

Abogado:

```text
Consulta legal 60m
Consulta urgente
```

### Multi-sucursal

Spa:

```text
Masaje
Facial
Paquete Premium
```

Responsabilidad:

- servicio
- duración
- precio base
- buffers
- modalidad

No manejar disponibilidad aquí.

---

# 6. Locations

Multi-sucursal.

```text
Tenant/Locations
```

Necesario.

No todos lo usarán.

Soporta:

### Single-location

```text
default office
```

### Multi-branch

```text
Sucursal Marbella
Sucursal Costa del Este
Sucursal David
```

Subdominio:

```text
Locations
Rooms
Resources
```

Responsabilidad:

- sucursal
- salas
- cabinas
- capacidad
- dirección

Tenant scoped.

Índices:

```text
(tenant_id, location_id)
```

---

# 7. Staff

Separar staff de IAM.

Error común:

> user == employee

No siempre.

```text
Tenant/Staff
```

Modelo:

```text
User
StaffProfile
```

Permite:

- profesional sin login
- reemplazos
- agenda asignada

Subdominio:

```text
StaffProfiles
Specialties
EmploymentRules
```

Ejemplos:

Barbería:

```text
Luis corta cabello
Ana manicure
```

Tutor:

```text
Profesor Matemática
```

---

# 8. Scheduling

Core operativo.

No mezclar con Booking.

```text
Tenant/Scheduling
```

Responsabilidad:

**cómo opera la agenda**

Subdominio:

```text
BusinessHours
StaffSchedules
Breaks
Blackouts
HolidayRules
RecurringSchedules
```

Casos:

- horario negocio
- horario profesional
- lunch
- vacaciones
- feriados
- mantenimiento

Esto genera disponibilidad potencial.

---

# 9. Availability

Separado deliberadamente.

Scheduling define reglas.

Availability calcula slots.

```text
Tenant/Availability
```

Responsabilidad:

**availability engine**

Subdominio:

```text
SlotGeneration
CapacityRules
AvailabilityCache
```

Flujo:

```text
Scheduling
    ↓
Availability Engine
    ↓
Available Slots
```

Redis-heavy.

Evita:

- queries explosivas
- recomputación

Edge cases:

- DST
- timezone
- double booking
- concurrent booking

Hot path.

No hacer consultas naïve.

---

# 10. Booking

Dominio principal.

```text
Tenant/Booking
```

Responsabilidad:

**reserva confirmada**

Subdominio:

```text
Bookings
BookingStatus
BookingPolicies
Rescheduling
Cancellation
```

Estados:

```text
draft
pending
confirmed
checked_in
completed
cancelled
no_show
```

Casos:

- crear
- confirmar
- mover
- cancelar
- no-show

No calcular disponibilidad aquí.

Consume Availability.

---

# 11. CRM

Cliente del negocio.

Separado del booking.

```text
Tenant/CRM
```

Responsabilidad:

- clientes
- historial
- notas
- preferencias

Subdominio:

```text
Customers
Tags
Notes
Consents
History
```

Casos:

Abogado:

- expediente
- notas

Spa:

- preferencias
- alergias
- historial

Cuidado:

datos sensibles → Audit.

---

# 12. Notifications

Async.

Nunca inline.

```text
Tenant/Notifications
```

Tenant-aware jobs obligatorios.

Canales:

- email
- SMS
- WhatsApp
- push

Casos:

- reminder
- confirmación
- cancelación
- follow-up

Subdominio:

```text
Templates
CampaignRules
DeliveryLogs
```

Jobs:

```text
tenant_id required
```

Sin excepción.

---

# 13. PublicSite

Canal público.

Separarlo del admin.

```text
Tenant/PublicSite
```

Responsabilidad:

**booking funnel**

Subdominio:

```text
Landing
PublicBooking
PublicAvailability
SEO
```

Rutas:

```text
slug.domain.com
slug.domain.com/book
```

Casos:

- landing
- catálogo
- agenda pública
- checkout

Compatible con branding.

---

# 14. Payments

Tenant-side.

No billing SaaS.

Distinción crítica.

```text
Tenant/Payments
```

Responsabilidad:

**cobro del negocio a sus clientes**

No:

- suscripción SaaS

Sí:

- depósito
- anticipo
- pago cita

Subdominio:

```text
Invoices
Deposits
Refunds
PaymentTransactions
```

Webhooks:

idempotentes obligatorios.

---

# 15. Reports

Analytics tenant.

```text
Tenant/Reports
```

KPIs:

- ocupación
- revenue
- no-show
- cancelaciones
- staff utilization
- peak hours

Query pattern:

- aggregate tables
- cache
- async

No dashboards con N+1.

---La siguiente capa natural sería definir **Casos de Uso (CU) del producto Booking** siguiendo el mismo estilo de `01_CasosDeUso.md`.

# 16. Integrations

Separado por estabilidad.

```text
Tenant/Integrations
```

Responsabilidad:

salidas/entradas externas.

Subdominio:

```text
CalendarSync
WebhookOutbound
ImportExport
ExternalProviders
```

Integraciones:

- Google Calendar
- Outlook
- Zapier
- webhooks

Consistente con webhooks tenant configurables.

---

# Estructura Final

```text
Modules/
├── Central/
│   ├── Auth
│   ├── Provisioning
│   ├── Billing
│   ├── Support
│   └── Infrastructure
│
└── Tenant/
    ├── Identity
    ├── Settings
    ├── Audit
    ├── Quotas
    ├── Catalog
    ├── Locations
    ├── Staff
    ├── Scheduling
    ├── Availability
    ├── Booking
    ├── CRM
    ├── Notifications
    ├── PublicSite
    ├── Payments
    ├── Reports
    └── Integrations
```

# Trade-offs

### Ventajas

- reusable para múltiples industrias
- evita módulo monolítico "Appointments"
- separa reglas vs ejecución
- soporta single practitioner y cadenas
- multi-sucursal natural
- queue isolation clara

### Coste

- más módulos
- mayor disciplina arquitectónica
- Availability requiere diseño serio de cache/concurrency

Pero evita deuda grande después.

La siguiente capa natural sería definir **Casos de Uso (CU) del producto Booking** siguiendo el mismo estilo de `01_CasosDeUso.md`.
