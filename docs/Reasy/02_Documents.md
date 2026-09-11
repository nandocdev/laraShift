# Reasy – Documentación Técnica Completa

## Sistema SaaS de Gestión de Reservas Multiempresa

> **Versión:** 2.1 (alineada a openSaaS)
> **Fecha:** Septiembre 2025
> **Autor:** Equipo de Arquitectura
> **Estado:** En Desarrollo
> **Nombres anteriores deprecados:** ReserveHub. El nombre oficial del producto es **Reasy** sobre el framework **openSaaS**.

> **Alineación normativa openSaaS (lectura obligatoria):**
>
> - Reasy es **producto vertical**, no core del framework. Toda lógica de reservas (negocios, sedes, servicios, recursos, horarios, reservas, clientes del negocio) vive en la **capa de extensión del producto**, nunca en `Tenant/Access|Compliance|Experience|Integrations|Workspace` del core ni en `Central/*` como dominio. Ver `docs/PROJECT_DECISIONS.md §11`, `docs/ARCHITECTURE_RULES.md`, `docs/PRD.md`.
> - **Arquitectura oficial:** monolito modular (`Platform/Central/Tenant`), Laravel 13+, Livewire 4 + FluxUI 2 + TailwindCSS, PostgreSQL + RLS (`tenant_id` + `SET LOCAL` transaccional), Redis (cache `tenant:{id}:*`, colas Horizon, locks, sesiones). Quedan **derogados** para este producto: microservicios, Kong/Kafka/K8s como requisito, sharding por tenant, CQRS/Event Sourcing, Repository genérico, React/Vue/Inertia en paneles, JWT unificado multi-rol, Python/FastAPI como referencia de implementación.
> - **Identidad:** guards separados `central` (`CentralUser`) y `tenant` (`User`). Prohibida tabla única `users` con `type` y token único multi-rol (§7.2 queda como referencia histórica, no normativo).
> - **Billing:** Clave (redirect) primero, dLocal (Smart Fields + MIT tarjeta) segundo, Stripe diferido (`docs/BILLING.md`). Stripe/PayPal/Adyen en §4.2 son referencia genérica, no plan de integración.
> - **Dinero:** centavos enteros (`amount_cents int`) + `PriceFormatter` en `Platform/Data`, no `DECIMAL` + casts dispersos.
> - **Async:** Jobs `TenantAware` + `RehydrateTenantContext`, idempotentes y reintentables. Cache siempre `tenant:{id}:{key}`. Todo módulo tenant-aware exige `CrossTenantLeakTest`.
> - En caso de contradicción entre este documento y `docs/PROJECT_DECISIONS.md`, prevalece `PROJECT_DECISIONS.md`.

---

## Índice

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Análisis del Negocio](#2-análisis-del-negocio)
3. [Arquitectura del Sistema](#3-arquitectura-del-sistema)
4. [Especificaciones Técnicas Detalladas](#4-especificaciones-técnicas-detalladas)
5. [Diseño de Base de Datos](#5-diseño-de-base-de-datos)
6. [APIs y Contratos](#6-apis-y-contratos)
7. [Seguridad y Cumplimiento](#7-seguridad-y-cumplimiento)
8. [Estrategia de Despliegue](#8-estrategia-de-despliegue)
9. [Monitoreo y Observabilidad](#9-monitoreo-y-observabilidad)
10. [Plan de Pruebas](#10-plan-de-pruebas)
11. [Documentación para Desarrolladores](#11-documentación-para-desarrolladores)

---

## 1. Resumen Ejecutivo

### 1.1 Visión del Producto

Reasy es el producto vertical de reservas construido sobre openSaaS, diseñado para revolucionar la gestión de reservas y citas en múltiples sectores verticales. Nuestra solución permite a las empresas optimizar sus operaciones, reducir la fricción en el proceso de reserva y maximizar los ingresos a través de políticas inteligentes de depósitos y cancelaciones.

### 1.2 Propuesta de Valor

- **Para Negocios**: Aumento del 35% en conversión de reservas, reducción del 60% en no-shows
- **Para Clientes**: Experiencia de reserva fluida en menos de 2 minutos
- **Para la Plataforma**: Modelo de monetización escalable con múltiples fuentes de ingresos

### 1.3 Métricas de Éxito

| Métrica           | Objetivo Año 1 | Objetivo Año 3 |
| ----------------- | -------------- | -------------- |
| Tenants Activos   | 500            | 5,000          |
| Transacciones/Mes | 50K            | 1M             |
| Uptime            | 99.9%          | 99.95%         |
| NPS               | 50+            | 70+            |

---

## 2. Análisis del Negocio

### 2.1 Stakeholders Detallados

#### 2.1.1 Administrador de Plataforma (SaaS Owner)

**Responsabilidades:**

- Gestión de tenants y planes de suscripción
- Configuración de pasarelas de pago globales
- Monitoreo de KPIs y health del sistema
- Soporte técnico escalado y cumplimiento regulatorio
- Gestión de facturación de la plataforma
  **Herramientas:**
- Dashboard ejecutivo con métricas en tiempo real
- Panel de gestión de tenants
- Sistema de tickets de soporte
- Herramientas de análisis de negocio
  **KPIs:**
- MRR (Monthly Recurring Revenue)
- Churn rate por segmento
- Tiempo de resolución de incidentes
- Utilización de recursos por tenant

#### 2.1.2 Administrador de Negocio (Tenant Owner)

**Responsabilidades:**

- Configuración de sedes, servicios y precios
- Gestión de equipos y permisos
- Definición de políticas de negocio
- Análisis de performance y reportes
  **Herramientas:**
- Panel de configuración de negocio
- Reportes avanzados y dashboards
- Gestión de usuarios y roles
- Configuración de integraciones
  **KPIs:**
- Revenue per booking
- Utilización de recursos
- Customer satisfaction score
- Efficiency metrics

#### 2.1.3 Usuarios de Negocio (Staff/Empleados)

**Categorías:**

- **Recepcionistas**: Gestión diaria de reservas
- **Profesionales**: Gestión de agenda personal
- **Gerentes de Sede**: Supervisión operativa
- **Personal de Soporte**: Atención al cliente
  **Herramientas:**
- Calendario inteligente con drag & drop
- App móvil para gestión en tiempo real
- Sistema de check-in/check-out
- Chat interno para coordinación

#### 2.1.4 Clientes Registrados

**Características:**

- Perfil completo con historial
- Métodos de pago almacenados
- Preferencias y favoritos
- Programa de fidelidad
  **Beneficios:**
- Reserva en 1 clic
- Descuentos por fidelidad
- Recordatorios personalizados
- Reprogramación flexible

#### 2.1.5 Clientes No Registrados (Guest)

**Características:**

- Datos mínimos requeridos (nombre, contacto)
- Validación por OTP
- Sin historial persistente
- Experiencia simplificada
  **Limitaciones:**
- Sin beneficios de fidelidad
- Políticas de cancelación más estrictas
- Verificación adicional requerida

### 2.2 Modelo de Negocio

#### 2.2.1 Estructura de Precios

| Plan         | Precio Base | Reservas/Mes | Fee Transacción | Usuarios  | Soporte         |
| ------------ | ----------- | ------------ | --------------- | --------- | --------------- |
| Starter      | $49/mes     | 200          | 3.5% + $0.30    | 3         | Email           |
| Professional | $149/mes    | 1,000        | 2.9% + $0.30    | 10        | Chat + Email    |
| Business     | $399/mes    | 5,000        | 2.4% + $0.30    | 25        | Teléfono + Chat |
| Enterprise   | Custom      | Unlimited    | 1.9% + $0.30    | Unlimited | Dedicated       |

#### 2.2.2 Fuentes de Ingresos

1. **Suscripciones mensuales/anuales**
2. **Comisiones por transacción**
3. **Add-ons premium** (integraciones, reportes avanzados)
4. **Servicios profesionales** (implementación, training)

---

## 3. Arquitectura del Sistema

> **Normativo openSaaS:** esta sección (§3.1–§3.2) describía microservicios. Queda **derogada como arquitectura**. La arquitectura vigente es **monolito modular openSaaS** (`Platform/Central/Tenant`, Actions + DTOs + Contracts, Livewire SSR). El diagrama y principios siguientes se conservan solo como **referencia del dominio** (qué capacidades necesita Reasy), no como topología a implementar. No implementar Kong, Kafka, sharding, ni servicios separados.

### 3.1 Arquitectura de Alto Nivel (referencia de dominio, no topología)

```mermaid
graph TB
    subgraph "Edge Layer"
        CDN[CloudFlare CDN]
        WAF[Web Application Firewall]
        LB[Load Balancer]
    end

    subgraph "Application Layer"
        subgraph "Frontend"
            WEB[Web App - React]
            MOBILE[Mobile App - React Native]
            WIDGET[Embeddable Widget]
            ADMIN[Admin Panels]
        end

        subgraph "API Gateway"
            KONG[Kong Gateway]
            RATE[Rate Limiter]
            AUTH[Auth Service]
        end

        subgraph "Microservices"
            TENANT[Tenant Service]
            BOOKING[Booking Service]
            PAYMENT[Payment Service]
            NOTIFICATION[Notification Service]
            CALENDAR[Calendar Service]
            ANALYTICS[Analytics Service]
        end
    end

    subgraph "Data Layer"
        POSTGRES[(PostgreSQL Cluster)]
        REDIS[(Redis Cluster)]
        ELASTIC[(Elasticsearch)]
        S3[(Object Storage)]
    end

    subgraph "External Services"
        STRIPE[Stripe/PayPal]
        TWILIO[Twilio/SendGrid]
        GCAL[Google Calendar]
    end

    CDN --> WAF --> LB --> KONG
    KONG --> TENANT & BOOKING & PAYMENT & NOTIFICATION & CALENDAR & ANALYTICS
    MICROSERVICES --> POSTGRES & REDIS & ELASTIC
    PAYMENT --> STRIPE
    NOTIFICATION --> TWILIO
    CALENDAR --> GCAL
```

### 3.2 Principios Arquitectónicos

> **Normativo:** los principios vigentes son los de `docs/ARCHITECTURE_RULES.md` (Modular Monolith First, una responsabilidad por módulo, Actions + DTOs, comunicación solo vía Contracts/Events/Actions públicas, RLS como garantía real). Los sub-apartados 3.2.1–3.2.3 siguientes son referencia histórica del diseño anterior (microservicios/sharding) y **no deben implementarse**.

#### 3.2.1 Microservicios (derogado — referencia histórica)

- **Domain-Driven Design**: Cada servicio representa un contexto acotado
- **API-First**: Contratos bien definidos entre servicios
- **Autonomous Teams**: Cada equipo es propietario de su servicio
- **Decentralized Data**: Cada servicio maneja su propia base de datos

#### 3.2.2 Escalabilidad

- **Horizontal Scaling**: Servicios stateless con auto-scaling
- **Database Sharding**: Por tenant_id para distribución de carga
- **Caching Strategy**: Multi-layer caching (CDN, API, Database)
- **Async Processing**: Event-driven architecture para operaciones no críticas

#### 3.2.3 Resiliencia

- **Circuit Breakers**: Prevención de fallos en cascada
- **Bulkheads**: Aislamiento de recursos críticos
- **Timeouts & Retries**: Manejo inteligente de fallos temporales
- **Graceful Degradation**: Funcionalidad limitada en caso de fallos

---

## 4. Especificaciones Técnicas Detalladas

### 4.1 Servicios Core

#### 4.1.1 Tenant Service

**Responsabilidad**: Gestión de multi-tenancy y planes

**APIs Principales**:

- `POST /tenants` - Crear nuevo tenant
- `GET /tenants/{id}/limits` - Obtener límites actuales
- `POST /tenants/{id}/upgrade` - Cambiar plan
- `GET /tenants/{id}/usage` - Métricas de uso
  **Modelo de Datos**:

```sql
CREATE TABLE tenants (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    plan_id VARCHAR(50) NOT NULL,
    status tenant_status DEFAULT 'active',
    limits JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tenant_usage (
    tenant_id UUID REFERENCES tenants(id),
    metric_name VARCHAR(100) NOT NULL,
    current_value INTEGER NOT NULL,
    reset_at TIMESTAMP NOT NULL,
    PRIMARY KEY (tenant_id, metric_name)
);
```

#### 4.1.2 Booking Service

**Responsabilidad**: Gestión del ciclo de vida de reservas

**Máquina de Estados**:

```mermaid
stateDiagram-v2
    [*] --> Draft
    Draft --> Pending_Payment: require_deposit = true
    Draft --> Confirmed: require_deposit = false
    Pending_Payment --> Confirmed: payment_success
    Pending_Payment --> Expired: payment_timeout
    Confirmed --> Rescheduled: reschedule_request
    Confirmed --> Cancelled: cancel_request
    Confirmed --> No_Show: mark_no_show
    Confirmed --> Completed: mark_completed
    Rescheduled --> Confirmed
    Cancelled --> [*]
    No_Show --> [*]
    Completed --> [*]
    Expired --> [*]
```

**Business Rules Engine**:

```python
class BookingRules:
    def can_cancel(self, booking: Booking, user: User) -> tuple[bool, str]:
        # Verificar ventana de cancelación
        if booking.start_time - timezone.now() < booking.service.cancellation_window:
            if user.has_permission('cancel_anytime'):
                return True, "Admin override"
            return False, "Outside cancellation window"
        return True, "Within cancellation window"

    def calculate_refund(self, booking: Booking) -> Decimal:
        if booking.status == 'no_show':
            return Decimal('0.00')

        time_until_booking = booking.start_time - timezone.now()
        policy = booking.service.cancellation_policy

        for rule in policy.rules:
            if time_until_booking >= rule.min_hours:
                return booking.paid_amount * rule.refund_percentage / 100

        return Decimal('0.00')
```

#### 4.1.3 Calendar Service

**Responsabilidad**: Gestión de disponibilidad y scheduling

**Algoritmo de Disponibilidad**:

```python
class AvailabilityEngine:
    def get_available_slots(self, service_id: str, resource_id: str,
                          date_from: datetime, date_to: datetime) -> List[TimeSlot]:

        # 1. Obtener horarios base del recurso
        base_schedule = self.get_resource_schedule(resource_id, date_from, date_to)

        # 2. Aplicar excepciones (vacaciones, feriados)
        schedule_with_exceptions = self.apply_exceptions(base_schedule, resource_id)

        # 3. Generar slots según duración del servicio
        possible_slots = self.generate_slots(schedule_with_exceptions, service_id)

        # 4. Filtrar slots ya reservados
        available_slots = self.filter_booked_slots(possible_slots, resource_id)

        # 5. Aplicar buffers (setup/cleanup time)
        final_slots = self.apply_buffers(available_slots, service_id)

        return final_slots
```

**Cache Strategy**:

- TTL de 5 minutos para disponibilidad
- Invalidación por eventos (nueva reserva, cancelación)
- Warmup cache para fechas populares

### 4.2 Sistema de Pagos

#### 4.2.1 Payment Service Architecture

> **Normativo:** en openSaaS no hay `Payment Service` separado. Los pagos de reservas del negocio se apoyan en **Billing Core** (`Central/Billing` + `docs/BILLING.md`): Clave redirect primero, dLocal Smart Fields + MIT segundo, Stripe diferido. El diagrama siguiente es referencia del flujo genérico, no topología a implementar.

```mermaid
graph LR
    subgraph "Payment Service"
        PG[Payment Gateway]
        PE[Payment Engine]
        RM[Refund Manager]
        WM[Webhook Manager]
    end

    subgraph "Payment Providers"
        STRIPE[Stripe]
        PAYPAL[PayPal]
        ADYEN[Adyen]
    end

    subgraph "Internal Services"
        BOOKING[Booking Service]
        TENANT[Tenant Service]
        NOTIFICATION[Notification Service]
    end

    PE --> STRIPE & PAYPAL & ADYEN
    PG --> PE
    RM --> PE
    WM --> BOOKING & NOTIFICATION
    BOOKING --> PG
```

#### 4.2.2 Flujo de Pagos

```mermaid
sequenceDiagram
    participant C as Cliente
    participant UI as Frontend
    participant API as Payment API
    participant PS as Payment Service
    participant STRIPE as Stripe
    participant BS as Booking Service

    C->>UI: Crear reserva con depósito
    UI->>API: POST /payments/intent
    API->>PS: Crear payment intent
    PS->>STRIPE: Create payment intent
    STRIPE-->>PS: Intent created
    PS-->>API: Client secret
    API-->>UI: Payment intent
    UI->>STRIPE: Confirm payment (3DS)
    STRIPE->>PS: Webhook payment_succeeded
    PS->>BS: Confirm booking
    BS->>C: Enviar confirmación
```

#### 4.2.3 Manejo de Reembolsos

```python
class RefundManager:
    async def process_refund(self, booking_id: str, reason: str) -> RefundResult:
        booking = await self.booking_service.get_booking(booking_id)
        refund_amount = self.calculate_refund_amount(booking, reason)

        if refund_amount <= 0:
            return RefundResult(success=False, reason="No refund applicable")

        # Crear refund en el proveedor de pagos
        refund = await self.payment_provider.create_refund(
            payment_id=booking.payment_id,
            amount=refund_amount,
            reason=reason
        )

        # Actualizar estado del booking
        await self.booking_service.update_booking_status(
            booking_id, 'cancelled', refund_id=refund.id
        )

        # Notificar al cliente
        await self.notification_service.send_refund_notification(
            booking.customer_id, refund
        )

        return RefundResult(success=True, refund_id=refund.id)
```

### 4.3 Sistema de Notificaciones

#### 4.3.1 Arquitectura Event-Driven

```mermaid
graph TD
    subgraph "Event Sources"
        BOOKING[Booking Events]
        PAYMENT[Payment Events]
        CALENDAR[Calendar Events]
    end

    subgraph "Message Broker"
        KAFKA[Apache Kafka]
    end

    subgraph "Notification Service"
        ROUTER[Event Router]
        TEMPLATE[Template Engine]
        DELIVERY[Delivery Manager]
    end

    subgraph "Channels"
        EMAIL[Email - SendGrid]
        SMS[SMS - Twilio]
        WHATSAPP[WhatsApp Business]
        PUSH[Push Notifications]
        WEBHOOK[Webhooks]
    end

    BOOKING & PAYMENT & CALENDAR --> KAFKA
    KAFKA --> ROUTER
    ROUTER --> TEMPLATE
    TEMPLATE --> DELIVERY
    DELIVERY --> EMAIL & SMS & WHATSAPP & PUSH & WEBHOOK
```

#### 4.3.2 Template System

```typescript
interface NotificationTemplate {
    id: string;
    name: string;
    event_type: string;
    channels: Channel[];
    template_data: {
        subject?: string;
        body: string;
        variables: string[];
    };
    conditions?: Condition[];
    scheduling?: SchedulingRule;
}

interface SchedulingRule {
    type: "immediate" | "scheduled" | "recurring";
    delay_minutes?: number;
    trigger_time?: string; // "24h_before", "2h_before"
}
```

---

## 5. Diseño de Base de Datos

> **Normativo openSaaS:** todo el modelo siguiente debe leerse con estas reglas, que prevalecen sobre el SQL literal:
>
> - Toda tabla de producto con datos de un negocio lleva `tenant_id uuid NOT NULL` (`business_id` = `tenant_id` a nivel lógico), índice por `tenant_id`, `ENABLE + FORCE ROW LEVEL SECURITY` y política `tenant_id = app.current_tenant_id()`. Sin políticas que autoricen con `app.tenant_id IS NULL`.
> - Propagación solo vía `SET LOCAL app.tenant_id` dentro de transacción explícita (`TenantContext` `scoped()`). Prohibido `SET SESSION`.
> - Dinero en `amount_cents int`, no `DECIMAL(10,2)`. El SQL con `DECIMAL` es referencia histórica.
> - `businesses` es la extensión de producto del `Tenant` de openSaaS (`tenants` en `Central/Provisioning`), no una tabla paralela sin FK. `locations/services/resources/schedules/bookings/customers/payments/notifications` cuelgan de `tenant_id` directa o transitivamente.
> - Prohibido particionamiento por tenant (§5.2) y sharding hasta demanda real con ADR. PostgreSQL + RLS single-DB escala sin cambiar arquitectura.

### 5.1 Modelo de Datos Expandido (referencia — aplicar reglas RLS/centavos al implementar)

```sql
-- ==========================================
-- TENANT MANAGEMENT
-- ==========================================

CREATE TYPE tenant_status AS ENUM ('active', 'suspended', 'cancelled');
CREATE TYPE subscription_status AS ENUM ('active', 'past_due', 'cancelled', 'trialing');

CREATE TABLE tenants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    domain VARCHAR(255),
    plan_id VARCHAR(50) NOT NULL,
    status tenant_status DEFAULT 'active',
    settings JSONB DEFAULT '{}',
    limits JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    plan_id VARCHAR(50) NOT NULL,
    status subscription_status DEFAULT 'active',
    current_period_start TIMESTAMP WITH TIME ZONE NOT NULL,
    current_period_end TIMESTAMP WITH TIME ZONE NOT NULL,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    stripe_subscription_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- BUSINESS STRUCTURE
-- ==========================================

CREATE TABLE businesses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT,
    logo_url VARCHAR(500),
    website VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address JSONB,
    timezone VARCHAR(50) DEFAULT 'UTC',
    currency VARCHAR(3) DEFAULT 'USD',
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    UNIQUE(tenant_id, slug)
);

CREATE TABLE locations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_id UUID NOT NULL REFERENCES businesses(id),
    name VARCHAR(255) NOT NULL,
    address JSONB NOT NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    timezone VARCHAR(50),
    settings JSONB DEFAULT '{}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- SERVICES AND RESOURCES
-- ==========================================

CREATE TYPE service_status AS ENUM ('active', 'inactive', 'archived');
CREATE TYPE resource_type AS ENUM ('staff', 'room', 'equipment');
CREATE TYPE booking_status AS ENUM (
    'draft', 'pending_payment', 'confirmed', 'rescheduled',
    'cancelled', 'no_show', 'completed', 'expired'
);

CREATE TABLE service_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_id UUID NOT NULL REFERENCES businesses(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    color VARCHAR(7), -- Hex color
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE services (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_id UUID NOT NULL REFERENCES businesses(id),
    category_id UUID REFERENCES service_categories(id),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    duration_minutes INTEGER NOT NULL,
    buffer_before_minutes INTEGER DEFAULT 0,
    buffer_after_minutes INTEGER DEFAULT 0,
    capacity INTEGER DEFAULT 1,
    status service_status DEFAULT 'active',
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE service_pricing (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    service_id UUID NOT NULL REFERENCES services(id),
    name VARCHAR(255) NOT NULL, -- "Standard", "Peak Hours", etc.
    base_price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    deposit_type VARCHAR(20) CHECK (deposit_type IN ('none', 'percentage', 'fixed')),
    deposit_amount DECIMAL(10,2),
    is_default BOOLEAN DEFAULT FALSE,
    conditions JSONB, -- Time-based, location-based conditions
    valid_from TIMESTAMP WITH TIME ZONE,
    valid_until TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE resources (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    location_id UUID NOT NULL REFERENCES locations(id),
    name VARCHAR(255) NOT NULL,
    type resource_type NOT NULL,
    description TEXT,
    capacity INTEGER DEFAULT 1,
    skills JSONB DEFAULT '[]', -- For staff: ["massage", "facial"]
    equipment JSONB DEFAULT '[]', -- Required equipment
    is_active BOOLEAN DEFAULT TRUE,
    settings JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- SCHEDULING SYSTEM
-- ==========================================

CREATE TABLE schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resource_id UUID NOT NULL REFERENCES resources(id),
    name VARCHAR(255) NOT NULL,
    recurrence_rule TEXT, -- RFC 5545 RRULE
    start_date DATE NOT NULL,
    end_date DATE,
    time_slots JSONB NOT NULL, -- [{"start": "09:00", "end": "17:00", "days": [1,2,3,4,5]}]
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE schedule_exceptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    resource_id UUID NOT NULL REFERENCES resources(id),
    exception_date DATE NOT NULL,
    type VARCHAR(20) CHECK (type IN ('unavailable', 'custom_hours', 'holiday')),
    custom_hours JSONB, -- For custom_hours type
    reason VARCHAR(255),
    created_by UUID NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- CUSTOMER MANAGEMENT
-- ==========================================

CREATE TYPE customer_status AS ENUM ('active', 'inactive', 'banned');
CREATE TYPE customer_type AS ENUM ('registered', 'guest');

CREATE TABLE customers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    type customer_type NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255),
    date_of_birth DATE,
    gender VARCHAR(20),
    status customer_status DEFAULT 'active',
    preferences JSONB DEFAULT '{}',
    marketing_consent BOOLEAN DEFAULT FALSE,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    phone_verified_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),

    -- Constraints for data integrity
    CHECK (
        (type = 'registered' AND email IS NOT NULL) OR
        (type = 'guest' AND (email IS NOT NULL OR phone IS NOT NULL))
    )
);

CREATE UNIQUE INDEX idx_customers_tenant_email ON customers(tenant_id, email)
WHERE email IS NOT NULL;

-- ==========================================
-- BOOKING SYSTEM
-- ==========================================

CREATE TABLE bookings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    business_id UUID NOT NULL REFERENCES businesses(id),
    location_id UUID NOT NULL REFERENCES locations(id),
    service_id UUID NOT NULL REFERENCES services(id),
    resource_id UUID NOT NULL REFERENCES resources(id),
    customer_id UUID NOT NULL REFERENCES customers(id),

    -- Booking details
    status booking_status DEFAULT 'draft',
    booking_number VARCHAR(50) UNIQUE NOT NULL,
    start_time TIMESTAMP WITH TIME ZONE NOT NULL,
    end_time TIMESTAMP WITH TIME ZONE NOT NULL,
    actual_start_time TIMESTAMP WITH TIME ZONE,
    actual_end_time TIMESTAMP WITH TIME ZONE,

    -- Pricing information
    base_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    deposit_required DECIMAL(10,2) DEFAULT 0,
    deposit_paid DECIMAL(10,2) DEFAULT 0,
    currency VARCHAR(3) NOT NULL,

    -- Metadata
    source VARCHAR(50) DEFAULT 'web', -- web, mobile, admin, api
    notes TEXT,
    internal_notes TEXT,
    metadata JSONB DEFAULT '{}',

    -- Audit fields
    created_by UUID,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    cancelled_at TIMESTAMP WITH TIME ZONE,
    cancelled_by UUID,
    cancellation_reason TEXT
);

-- ==========================================
-- PAYMENT SYSTEM
-- ==========================================

CREATE TYPE payment_status AS ENUM (
    'pending', 'processing', 'succeeded', 'failed',
    'cancelled', 'refunded', 'partially_refunded'
);

CREATE TYPE payment_type AS ENUM ('deposit', 'full_payment', 'refund', 'penalty');

CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    booking_id UUID REFERENCES bookings(id),
    customer_id UUID NOT NULL REFERENCES customers(id),

    -- Payment details
    type payment_type NOT NULL,
    status payment_status DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,

    -- Provider information
    provider VARCHAR(50) NOT NULL, -- stripe, paypal, etc.
    provider_payment_id VARCHAR(255),
    provider_customer_id VARCHAR(255),
    payment_method_id VARCHAR(255),

    -- Metadata
    description TEXT,
    metadata JSONB DEFAULT '{}',
    failure_reason TEXT,

    -- Timestamps
    processed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE refunds (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id UUID NOT NULL REFERENCES payments(id),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    reason TEXT,
    provider_refund_id VARCHAR(255),
    status payment_status DEFAULT 'pending',
    processed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- NOTIFICATION SYSTEM
-- ==========================================

CREATE TYPE notification_channel AS ENUM ('email', 'sms', 'whatsapp', 'push', 'webhook');
CREATE TYPE notification_status AS ENUM ('pending', 'sent', 'delivered', 'failed', 'bounced');

CREATE TABLE notification_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    channel notification_channel NOT NULL,
    subject VARCHAR(500),
    body TEXT NOT NULL,
    variables JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    customer_id UUID REFERENCES customers(id),
    booking_id UUID REFERENCES bookings(id),
    template_id UUID REFERENCES notification_templates(id),

    channel notification_channel NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    body TEXT NOT NULL,
    status notification_status DEFAULT 'pending',

    -- Delivery information
    provider_message_id VARCHAR(255),
    sent_at TIMESTAMP WITH TIME ZONE,
    delivered_at TIMESTAMP WITH TIME ZONE,
    opened_at TIMESTAMP WITH TIME ZONE,
    clicked_at TIMESTAMP WITH TIME ZONE,
    error_message TEXT,

    scheduled_for TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- AUDIT AND COMPLIANCE
-- ==========================================

CREATE TYPE audit_action AS ENUM (
    'create', 'read', 'update', 'delete',
    'login', 'logout', 'export', 'import'
);

CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID REFERENCES tenants(id),
    user_id UUID,
    entity_type VARCHAR(100) NOT NULL,
    entity_id UUID,
    action audit_action NOT NULL,
    old_values JSONB,
    new_values JSONB,
    ip_address INET,
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ==========================================
-- INDEXES FOR PERFORMANCE
-- ==========================================

-- Bookings indexes
CREATE INDEX idx_bookings_tenant_id ON bookings(tenant_id);
CREATE INDEX idx_bookings_resource_time ON bookings(resource_id, start_time, end_time);
CREATE INDEX idx_bookings_customer_status ON bookings(customer_id, status);
CREATE INDEX idx_bookings_status_start_time ON bookings(status, start_time);
CREATE INDEX idx_bookings_created_at ON bookings(created_at DESC);

-- Availability queries
CREATE INDEX idx_schedules_resource_dates ON schedules(resource_id, start_date, end_date);
CREATE INDEX idx_schedule_exceptions_resource_date ON schedule_exceptions(resource_id, exception_date);

-- Customer lookups
CREATE INDEX idx_customers_tenant_phone ON customers(tenant_id, phone) WHERE phone IS NOT NULL;
CREATE INDEX idx_customers_status ON customers(status) WHERE status = 'active';

-- Notifications
CREATE INDEX idx_notifications_scheduled ON notifications(scheduled_for) WHERE status = 'pending';
CREATE INDEX idx_notifications_customer ON notifications(customer_id, created_at DESC);

-- Payments
CREATE INDEX idx_payments_booking ON payments(booking_id);
CREATE INDEX idx_payments_customer ON payments(customer_id, created_at DESC);
CREATE INDEX idx_payments_provider ON payments(provider, provider_payment_id);

-- Audit logs
CREATE INDEX idx_audit_logs_tenant_time ON audit_logs(tenant_id, created_at DESC);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
```

### 5.2 Estrategia de Particionamiento

```sql
-- Particionamiento por tenant para bookings (grandes volúmenes)
CREATE TABLE bookings_partitioned (
    LIKE bookings INCLUDING ALL
) PARTITION BY HASH (tenant_id);

-- Crear particiones dinámicamente
CREATE TABLE bookings_part_0 PARTITION OF bookings_partitioned
    FOR VALUES WITH (modulus 16, remainder 0);
-- ... repeat for remainder 1-15

-- Particionamiento temporal para audit_logs
CREATE TABLE audit_logs_partitioned (
    LIKE audit_logs INCLUDING ALL
) PARTITION BY RANGE (created_at);

-- Particiones mensuales
CREATE TABLE audit_logs_2025_01 PARTITION OF audit_logs_partitioned
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
```

---

## 6. APIs y Contratos

### 6.1 Estándares de API

#### 6.1.1 REST API Guidelines

- **Versionado**: `/api/v1`, `/api/v2`
- **HTTP Status Codes**: Uso semánticamente correcto
- **Content-Type**: `application/json` por defecto
- **Rate Limiting**: Headers `X-RateLimit-*`
- **Pagination**: Cursor-based para mejor performance
- **Filtering**: Query parameters estándar

#### 6.1.2 Response Format

```typescript
interface APIResponse<T> {
    success: boolean;
    data?: T;
    error?: {
        code: string;
        message: string;
        details?: any;
    };
    metadata?: {
        pagination?: PaginationInfo;
        filters?: FilterInfo;
        timestamp: string;
    };
}

interface PaginationInfo {
    cursor: string;
    hasMore: boolean;
    total?: number;
}
```

### 6.2 Core APIs

#### 6.2.1 Booking API

```yaml
openapi: 3.0.3
info:
  title: Reasy Booking API
  version: 1.0.0

paths:
  /api/v1/bookings:
    post:
      summary: Create new booking
      requestBody:
        content:
          application/json:
            schema:
              type: object
              required: [service_id, resource_id, start_time, customer]
              properties:
                service_id:
                  type: string
                  format: uuid
                resource_id:
                  type: string
                  format: uuid
                start_time:
                  type: string
                  format: date-time
                customer:
                  oneOf:
                    - $ref: '#/components/schemas/RegisteredCustomer'
                    - $ref: '#/components/schemas/GuestCustomer'
                notes:
                  type: string
                  maxLength: 1000
                require_deposit:
                  type: boolean
                  default: false
      responses:
        '201':
          description: Booking created successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/BookingResponse'
        '400':
          description: Invalid request data
        '409':
          description: Time slot not available
        '402':
          description: Payment required

    get:
      summary: List bookings
      parameters:
        - name: status
          in: query
          schema:
            type: array
            items:
              type: string
              enum: [draft, confirmed, cancelled, completed]
        - name: from_date
          in: query
          schema:
            type: string
            format: date
        - name: to_date
          in: query
          schema:
            type: string
            format: date
        - name: cursor
          in: query
          schema:
            type: string
        - name: limit
          in: query
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 20
      responses:
        '200':
          description: List of bookings
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/BookingResponse'
                  metadata:
                    $ref: '#/components/schemas/PaginationMetadata'

  /api/v1/bookings/{booking_id}:
    get:
      summary: Get booking details
      parameters:
        - name: booking_id
          in: path
          required: true
          schema:
            type: string
            format: uuid
      responses:
        '200':
          description: Booking details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/BookingResponse'

    patch:
      summary: Update booking
      parameters:
        - name: booking_id
          in: path
          required: true
          schema:
            type: string
            format: uuid
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                action:
                  type: string
                  enum: [cancel, reschedule, confirm, mark_no_show, complete]
                new_start_time:
                  type: string
                  format: date-time
                  description: Required for reschedule action
                cancellation_reason:
                  type: string
                  description: Optional for cancel action
                notes:
                  type: string
      responses:
        '200':
          description: Booking updated successfully
        '400':
          description: Invalid action or parameters
        '403':
          description: Action not allowed

components:
  schemas:
    BookingResponse:
      type: object
      properties:
        id:
          type: string
          format: uuid
        booking_number:
          type: string
        status:
          type: string
          enum: [draft, pending_payment, confirmed, rescheduled, cancelled, no_show, completed]
        service:
          $ref: '#/components/schemas/ServiceSummary'
        resource:
          $ref: '#/components/schemas/ResourceSummary'
        customer:
          $ref: '#/components/schemas/CustomerSummary'
        start_time:
          type: string
          format: date-time
        end_time:
          type: string
          format: date-time
        pricing:
          $ref: '#/components/schemas/PricingInfo'
        created_at:
          type: string
          format: date-time
        updated_at:
          type: string
          format: date-time

    RegisteredCustomer:
      type: object
      required: [customer_id]
      properties:
        customer_id:
          type: string
          format: uuid

    GuestCustomer:
      type: object
      required: [first_name, email_or_phone]
      properties:
        first_name:
          type: string
          minLength: 1
          maxLength: 255
        last_name:
          type: string
          maxLength: 255
        email:
          type: string
          format: email
        phone:
          type: string
          pattern: '^\+?[1-9]\d{1,14}
      oneOf:
        - required: [email]
        - required: [phone]
```

#### 6.2.2 Availability API

```typescript
// GET /api/v1/availability
interface AvailabilityRequest {
    service_id: string;
    resource_id?: string; // Optional - if not provided, returns all resources
    from_date: string; // ISO date
    to_date: string; // ISO date
    duration_minutes?: number; // Override service duration
}

interface AvailabilityResponse {
    service: ServiceSummary;
    resources: ResourceAvailability[];
    business_hours: BusinessHours;
}

interface ResourceAvailability {
    resource: ResourceSummary;
    dates: DateAvailability[];
}

interface DateAvailability {
    date: string; // ISO date
    slots: TimeSlot[];
    exceptions?: ScheduleException[];
}

interface TimeSlot {
    start_time: string; // ISO datetime
    end_time: string; // ISO datetime
    available: boolean;
    booking_id?: string; // If not available
    price?: PriceInfo;
}
```

#### 6.2.3 Payment API

```typescript
// POST /api/v1/payments/intent
interface PaymentIntentRequest {
    booking_id: string;
    amount: number;
    currency: string;
    payment_method_id?: string; // For saved payment methods
    save_payment_method?: boolean;
    return_url?: string; // For 3DS redirects
}

interface PaymentIntentResponse {
    intent_id: string;
    client_secret: string;
    status: "requires_payment_method" | "requires_confirmation" | "processing";
    next_action?: {
        type: "redirect_to_url" | "use_stripe_sdk";
        redirect?: {
            return_url: string;
            url: string;
        };
    };
}

// POST /api/v1/payments/{payment_id}/refund
interface RefundRequest {
    amount?: number; // Partial refund if specified
    reason?: string;
    reversal_reason?: "duplicate" | "fraudulent" | "requested_by_customer";
}

interface RefundResponse {
    refund_id: string;
    amount: number;
    currency: string;
    status: "pending" | "succeeded" | "failed";
    estimated_arrival?: string; // ISO datetime
}
```

### 6.3 Webhook System

#### 6.3.1 Webhook Events

```typescript
interface WebhookEvent {
    id: string;
    type: string;
    created: number; // Unix timestamp
    api_version: string;
    data: {
        object: any;
        previous_attributes?: any;
    };
    livemode: boolean;
    pending_webhooks: number;
    request: {
        id: string;
        idempotency_key?: string;
    };
}

// Event Types
type EventType =
    | "booking.created"
    | "booking.updated"
    | "booking.cancelled"
    | "booking.completed"
    | "booking.no_show"
    | "payment.succeeded"
    | "payment.failed"
    | "payment.refunded"
    | "customer.created"
    | "customer.updated";
```

#### 6.3.2 Webhook Security

```python
import hmac
import hashlib
from datetime import datetime, timedelta

class WebhookVerifier:
    def __init__(self, webhook_secret: str):
        self.secret = webhook_secret.encode()

    def verify_signature(self, payload: bytes, signature: str, timestamp: str) -> bool:
        # Verify timestamp (prevent replay attacks)
        webhook_time = datetime.fromtimestamp(int(timestamp))
        if datetime.utcnow() - webhook_time > timedelta(minutes=5):
            return False

        # Verify signature
        expected_signature = hmac.new(
            self.secret,
            timestamp.encode() + payload,
            hashlib.sha256
        ).hexdigest()

        return hmac.compare_digest(f"sha256={expected_signature}", signature)
```

---

## 7. Seguridad y Cumplimiento

### 7.1 Arquitectura de Seguridad

```mermaid
graph TB
    subgraph "Edge Security"
        WAF[Web Application Firewall]
        DDOS[DDoS Protection]
        RATE[Rate Limiting]
    end

    subgraph "Application Security"
        AUTH[Authentication Service]
        AUTHZ[Authorization Service]
        JWT[JWT Validation]
        MFA[Multi-Factor Auth]
    end

    subgraph "Data Security"
        ENCRYPT[Encryption at Rest]
        TLS[TLS Encryption]
        TOKENIZE[PCI Tokenization]
        HASH[Password Hashing]
    end

    subgraph "Monitoring & Compliance"
        AUDIT[Audit Logging]
        SIEM[SIEM Integration]
        GDPR[GDPR Tools]
        PCI[PCI Compliance]
    end
```

### 7.2 Authentication & Authorization

> **Normativo openSaaS:** prohibido JWT unificado multi-rol y tabla única con `type`. Identidades separadas: `central` (`CentralUser`) y `tenant` (`User`). §7.2.1–§7.2.2 son referencia histórica.

#### 7.2.1 JWT Token Structure (derogado — referencia histórica)

```typescript
interface AccessToken {
    // Standard claims
    iss: string; // issuer: "reservehub.com"
    sub: string; // subject: user_id
    aud: string; // audience: "api"
    exp: number; // expiration timestamp
    iat: number; // issued at timestamp
    jti: string; // jwt id (for revocation)

    // Custom claims
    tenant_id: string;
    user_type: "platform_admin" | "business_admin" | "staff" | "customer";
    permissions: string[];
    session_id: string;
    mfa_verified?: boolean;
}

interface RefreshToken {
    iss: string;
    sub: string;
    exp: number; // Longer expiration (30 days)
    token_type: "refresh";
    session_id: string;
    device_id?: string;
}
```

#### 7.2.2 Role-Based Access Control (RBAC)

```typescript
interface Role {
    id: string;
    name: string;
    tenant_id?: string; // null for platform-wide roles
    permissions: Permission[];
    is_system_role: boolean;
}

interface Permission {
    resource: string; // 'booking', 'customer', 'payment'
    actions: string[]; // ['create', 'read', 'update', 'delete']
    conditions?: PermissionCondition[];
}

interface PermissionCondition {
    field: string;
    operator: "equals" | "in" | "not_in" | "contains";
    value: any;
}

// Example: Staff can only manage bookings for their assigned resources
const staffBookingPermission: Permission = {
    resource: "booking",
    actions: ["read", "update"],
    conditions: [
        {
            field: "resource.assigned_staff_ids",
            operator: "contains",
            value: "{{user.id}}",
        },
    ],
};
```

### 7.3 Data Protection

#### 7.3.1 Encryption Strategy

```python
# Encryption at Rest
class DataEncryption:
    def __init__(self, master_key: str):
        self.fernet = Fernet(master_key.encode())

    def encrypt_pii(self, data: str) -> str:
        """Encrypt personally identifiable information"""
        return self.fernet.encrypt(data.encode()).decode()

    def decrypt_pii(self, encrypted_data: str) -> str:
        """Decrypt PII data"""
        return self.fernet.decrypt(encrypted_data.encode()).decode()

# Field-level encryption for sensitive data
class EncryptedField:
    def __init__(self, field_name: str):
        self.field_name = field_name
        self.encryptor = DataEncryption(settings.ENCRYPTION_KEY)

    def __set__(self, instance, value):
        if value:
            encrypted_value = self.encryptor.encrypt_pii(value)
            setattr(instance, f'_{self.field_name}', encrypted_value)

    def __get__(self, instance, owner):
        encrypted_value = getattr(instance, f'_{self.field_name}', None)
        if encrypted_value:
            return self.encryptor.decrypt_pii(encrypted_value)
        return None

# Usage in models
class Customer:
    email = EncryptedField('email')
    phone = EncryptedField('phone')
    # ... other fields
```

#### 7.3.2 PCI DSS Compliance

```mermaid
graph LR
    subgraph "Cardholder Data Environment (CDE)"
        APP[Application]
        DB[(Database)]
    end

    subgraph "Payment Processors"
        STRIPE[Stripe]
        PAYPAL[PayPal]
    end

    subgraph "Tokenization"
        VAULT[Token Vault]
    end

    APP --> VAULT
    VAULT --> STRIPE
    VAULT --> PAYPAL
    APP -.-> DB

    note1[No PAN stored<br/>Only tokens]
    note2[TLS 1.2+ everywhere<br/>Network segmentation]
```

**PCI Requirements Implementation:**

1. **Requirement 1-2: Firewall & Default Passwords**
    - Web Application Firewall (WAF) rules
    - Network segmentation between environments
    - Default credentials changed on all systems
2. **Requirement 3-4: Data Protection & Encryption**
    - No PAN storage (tokenization only)
    - TLS 1.2+ for all data transmission
    - Strong cryptography for any stored sensitive data
3. **Requirement 7-8: Access Controls**
    - Need-to-know access restrictions
    - Strong authentication mechanisms
    - Regular access reviews
4. **Requirement 10-12: Monitoring & Policies**
    - Comprehensive audit logging
    - Regular security testing
    - Information security policies

### 7.4 GDPR Compliance

#### 7.4.1 Data Subject Rights

```typescript
interface GDPRService {
    // Right to Access (Article 15)
    exportUserData(
        userId: string,
        format: "json" | "csv",
    ): Promise<UserDataExport>;

    // Right to Rectification (Article 16)
    updateUserData(userId: string, updates: UserDataUpdate): Promise<void>;

    // Right to Erasure (Article 17)
    deleteUserData(
        userId: string,
        reason: ErasureReason,
    ): Promise<DeletionReport>;

    // Right to Data Portability (Article 20)
    exportPortableData(userId: string): Promise<PortableDataExport>;

    // Right to Object (Article 21)
    optOutProcessing(
        userId: string,
        processingType: ProcessingType,
    ): Promise<void>;
}

interface UserDataExport {
    user_id: string;
    export_date: string;
    data: {
        personal_info: PersonalInfo;
        bookings: BookingInfo[];
        payments: PaymentInfo[];
        communications: CommunicationInfo[];
    };
    retention_policy: RetentionInfo;
}

interface DeletionReport {
    user_id: string;
    deletion_date: string;
    deleted_records: {
        table: string;
        count: number;
        anonymized: boolean;
    }[];
    retained_records: {
        table: string;
        count: number;
        retention_reason: string;
        deletion_scheduled: string;
    }[];
}
```

#### 7.4.2 Consent Management

```sql
CREATE TABLE consent_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    customer_id UUID NOT NULL REFERENCES customers(id),
    consent_type VARCHAR(50) NOT NULL, -- 'marketing', 'analytics', 'functional'
    granted BOOLEAN NOT NULL,
    purpose TEXT NOT NULL,
    legal_basis VARCHAR(50) NOT NULL, -- 'consent', 'legitimate_interest', etc.
    source VARCHAR(100), -- 'registration_form', 'booking_flow', etc.
    ip_address INET,
    user_agent TEXT,
    granted_at TIMESTAMP WITH TIME ZONE,
    withdrawn_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

---

## 8. Estrategia de Despliegue

### 8.1 Arquitectura Cloud-Native

```mermaid
graph TB
    subgraph "Production Environment"
        subgraph "Kubernetes Cluster"
            NGINX[NGINX Ingress]
            subgraph "Application Pods"
                API1[API Pod 1]
                API2[API Pod 2]
                API3[API Pod 3]
                WORKER1[Worker Pod 1]
                WORKER2[Worker Pod 2]
            end

            subgraph "Databases"
                PG_PRIMARY[(PostgreSQL Primary)]
                PG_REPLICA[(PostgreSQL Replica)]
                REDIS_CLUSTER[(Redis Cluster)]
            end
        end

        subgraph "Managed Services"
            RDS[(AWS RDS)]
            ELASTIC_CACHE[(ElastiCache)]
            S3[(S3 Storage)]
            SQS[(SQS Queues)]
        end
    end

    NGINX --> API1 & API2 & API3
    API1 & API2 & API3 --> RDS
    WORKER1 & WORKER2 --> SQS
```

### 8.2 Infrastructure as Code

#### 8.2.1 Terraform Configuration

```hcl
# main.tf
terraform {
  required_version = ">= 1.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.0"
    }
  }

  backend "s3" {
    bucket = "reservehub-terraform-state"
    key    = "infrastructure/terraform.tfstate"
    region = "us-west-2"
  }
}

module "vpc" {
  source = "./modules/vpc"

  name               = var.environment
  cidr               = var.vpc_cidr
  availability_zones = var.availability_zones

  enable_nat_gateway = true
  enable_vpn_gateway = false

  tags = local.common_tags
}

module "eks" {
  source = "./modules/eks"

  cluster_name    = "${var.environment}-reservehub"
  cluster_version = "1.27"

  vpc_id     = module.vpc.vpc_id
  subnet_ids = module.vpc.private_subnets

  node_groups = {
    main = {
      desired_size = var.node_group_desired_size
      max_size     = var.node_group_max_size
      min_size     = var.node_group_min_size

      instance_types = ["t3.large", "t3.xlarge"]
      capacity_type  = "SPOT"

      k8s_labels = {
        role = "worker"
      }
    }
  }

  tags = local.common_tags
}

module "rds" {
  source = "./modules/rds"

  identifier = "${var.environment}-reservehub-db"

  engine         = "postgres"
  engine_version = "15.3"
  instance_class = var.db_instance_class

  allocated_storage     = var.db_allocated_storage
  max_allocated_storage = var.db_max_allocated_storage
  storage_encrypted     = true

  db_name  = "reservehub"
  username = "reservehub"
  password = var.db_password

  vpc_security_group_ids = [aws_security_group.rds.id]
  subnet_group_name      = aws_db_subnet_group.main.name

  backup_retention_period = 7
  backup_window          = "03:00-04:00"
  maintenance_window     = "sun:04:00-sun:05:00"

  enabled_cloudwatch_logs_exports = ["postgresql"]

  tags = local.common_tags
}
```

#### 8.2.2 Kubernetes Manifests

```yaml
# api-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
    name: reservehub-api
    namespace: reservehub
spec:
    replicas: 3
    selector:
        matchLabels:
            app: reservehub-api
    template:
        metadata:
            labels:
                app: reservehub-api
        spec:
            containers:
                - name: api
                  image: reservehub/api:latest
                  ports:
                      - containerPort: 8000
                  env:
                      - name: DATABASE_URL
                        valueFrom:
                            secretKeyRef:
                                name: database-secret
                                key: url
                      - name: REDIS_URL
                        valueFrom:
                            secretKeyRef:
                                name: redis-secret
                                key: url
                  resources:
                      requests:
                          memory: "512Mi"
                          cpu: "250m"
                      limits:
                          memory: "1Gi"
                          cpu: "500m"
                  livenessProbe:
                      httpGet:
                          path: /health
                          port: 8000
                      initialDelaySeconds: 30
                      periodSeconds: 10
                  readinessProbe:
                      httpGet:
                          path: /ready
                          port: 8000
                      initialDelaySeconds: 5
                      periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
    name: reservehub-api-service
    namespace: reservehub
spec:
    selector:
        app: reservehub-api
    ports:
        - protocol: TCP
          port: 80
          targetPort: 8000
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
    name: reservehub-api-hpa
    namespace: reservehub
spec:
    scaleTargetRef:
        apiVersion: apps/v1
        kind: Deployment
        name: reservehub-api
    minReplicas: 3
    maxReplicas: 20
    metrics:
        - type: Resource
          resource:
              name: cpu
              target:
                  type: Utilization
                  averageUtilization: 70
        - type: Resource
          resource:
              name: memory
              target:
                  type: Utilization
                  averageUtilization: 80
```

### 8.3 CI/CD Pipeline

#### 8.3.1 GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Build and Deploy

on:
    push:
        branches: [main, develop]
    pull_request:
        branches: [main]

env:
    REGISTRY: ghcr.io
    IMAGE_NAME: ${{ github.repository }}

jobs:
    test:
        runs-on: ubuntu-latest
        services:
            postgres:
                image: postgres:15
                env:
                    POSTGRES_PASSWORD: postgres
                    POSTGRES_DB: test_db
                options: >-
                    --health-cmd pg_isready
                    --health-interval 10s
                    --health-timeout 5s
                    --health-retries 5
            redis:
                image: redis:7
                options: >-
                    --health-cmd "redis-cli ping"
                    --health-interval 10s
                    --health-timeout 5s
                    --health-retries 5

        steps:
            - uses: actions/checkout@v4

            - name: Set up Python
              uses: actions/setup-python@v4
              with:
                  python-version: "3.11"

            - name: Install dependencies
              run: |
                  python -m pip install --upgrade pip
                  pip install -r requirements.txt
                  pip install -r requirements-dev.txt

            - name: Run tests
              run: |
                  pytest --cov=. --cov-report=xml

            - name: Upload coverage
              uses: codecov/codecov-action@v3

            - name: Security scan
              run: |
                  bandit -r . -f json -o bandit-report.json
                  safety check

    build:
        needs: test
        runs-on: ubuntu-latest
        if: github.event_name == 'push'

        steps:
            - uses: actions/checkout@v4

            - name: Log in to Container Registry
              uses: docker/login-action@v3
              with:
                  registry: ${{ env.REGISTRY }}
                  username: ${{ github.actor }}
                  password: ${{ secrets.GITHUB_TOKEN }}

            - name: Extract metadata
              id: meta
              uses: docker/metadata-action@v5
              with:
                  images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
                  tags: |
                      type=ref,event=branch
                      type=ref,event=pr
                      type=sha,prefix={{branch}}-

            - name: Build and push Docker image
              uses: docker/build-push-action@v5
              with:
                  context: .
                  push: true
                  tags: ${{ steps.meta.outputs.tags }}
                  labels: ${{ steps.meta.outputs.labels }}

    deploy:
        needs: build
        runs-on: ubuntu-latest
        if: github.ref == 'refs/heads/main'

        steps:
            - uses: actions/checkout@v4

            - name: Configure AWS credentials
              uses: aws-actions/configure-aws-credentials@v4
              with:
                  aws-access-key-id: ${{ secrets.AWS_ACCESS_KEY_ID }}
                  aws-secret-access-key: ${{ secrets.AWS_SECRET_ACCESS_KEY }}
                  aws-region: us-west-2

            - name: Update kubeconfig
              run: |
                  aws eks update-kubeconfig --region us-west-2 --name production-reservehub

            - name: Deploy to Kubernetes
              run: |
                  kubectl set image deployment/reservehub-api reservehub-api=${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:main-${{ github.sha }}
                  kubectl rollout status deployment/reservehub-api
                  kubectl get pods -l app=reservehub-api
```

### 8.4 Blue-Green Deployment Strategy

```yaml
# blue-green-deploy.yaml
apiVersion: argoproj.io/v1alpha1
kind: Rollout
metadata:
    name: reservehub-api-rollout
spec:
    replicas: 5
    strategy:
        blueGreen:
            activeService: reservehub-api-active
            previewService: reservehub-api-preview
            autoPromotionEnabled: false
            scaleDownDelaySeconds: 30
            prePromotionAnalysis:
                templates:
                    - templateName: success-rate
                args:
                    - name: service-name
                      value: reservehub-api-preview
            postPromotionAnalysis:
                templates:
                    - templateName: success-rate
                    - templateName: response-time
                args:
                    - name: service-name
                      value: reservehub-api-active
    selector:
        matchLabels:
            app: reservehub-api
    template:
        metadata:
            labels:
                app: reservehub-api
        spec:
            containers:
                - name: api
                  image: reservehub/api:latest
                  ports:
                      - containerPort: 8000
```

---

## 9. Monitoreo y Observabilidad

### 9.1 Arquitectura de Observabilidad

```mermaid
graph TB
    subgraph "Application Layer"
        API[API Services]
        WORKER[Background Workers]
        DB[(Databases)]
    end

    subgraph "Observability Stack"
        OTEL[OpenTelemetry Collector]
        PROM[Prometheus]
        GRAFANA[Grafana]
        JAEGER[Jaeger]
        ELK[ELK Stack]
    end

    subgraph "Alerting"
        AM[AlertManager]
        SLACK[Slack]
        PAGER[PagerDuty]
        EMAIL[Email]
    end

    API --> OTEL
    WORKER --> OTEL
    DB --> OTEL
    OTEL --> PROM
    OTEL --> JAEGER
    OTEL --> ELK
    PROM --> GRAFANA
    PROM --> AM
    AM --> SLACK & PAGER & EMAIL
```

### 9.2 Métricas y KPIs

#### 9.2.1 SLIs (Service Level Indicators)

```python
# Business Metrics
class BusinessMetrics:
    def __init__(self):
        self.booking_conversion_rate = Gauge('booking_conversion_rate_percent')
        self.revenue_per_booking = Gauge('revenue_per_booking_usd')
        self.no_show_rate = Gauge('no_show_rate_percent')
        self.customer_satisfaction = Gauge('customer_satisfaction_score')

    def track_booking_funnel(self, step: str, tenant_id: str):
        counter = Counter(
            'booking_funnel_total',
            'Booking funnel steps',
            ['step', 'tenant_id']
        )
        counter.labels(step=step, tenant_id=tenant_id).inc()

# Technical Metrics
class TechnicalMetrics:
    def __init__(self):
        self.request_duration = Histogram(
            'http_request_duration_seconds',
            'HTTP request duration',
            ['method', 'endpoint', 'status_code']
        )

        self.database_query_duration = Histogram(
            'database_query_duration_seconds',
            'Database query duration',
            ['query_type', 'table']
        )

        self.cache_hit_rate = Gauge(
            'cache_hit_rate_percent',
            'Cache hit rate by type',
            ['cache_type']
        )

        self.active_connections = Gauge(
            'database_connections_active',
            'Active database connections'
        )

# Custom middleware for automatic instrumentation
from starlette.middleware.base import BaseHTTPMiddleware
import time

class MetricsMiddleware(BaseHTTPMiddleware):
    def __init__(self, app, metrics: TechnicalMetrics):
        super().__init__(app)
        self.metrics = metrics

    async def dispatch(self, request, call_next):
        start_time = time.time()

        response = await call_next(request)

        duration = time.time() - start_time
        self.metrics.request_duration.labels(
            method=request.method,
            endpoint=request.url.path,
            status_code=response.status_code
        ).observe(duration)

        return response
```

#### 9.2.2 SLOs (Service Level Objectives)

```yaml
# slo-definitions.yaml
slos:
    api_availability:
        description: "API should be available 99.9% of the time"
        sli: "sum(rate(http_requests_total{status_code!~'5..'}[5m])) / sum(rate(http_requests_total[5m]))"
        target: 0.999
        window: 30d

    api_latency:
        description: "95% of API requests should complete within 500ms"
        sli: "histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))"
        target: 0.5
        window: 30d

    booking_success_rate:
        description: "95% of booking attempts should succeed"
        sli: "sum(rate(booking_attempts_total{status='success'}[5m])) / sum(rate(booking_attempts_total[5m]))"
        target: 0.95
        window: 7d

    payment_success_rate:
        description: "99% of payment processing should succeed"
        sli: "sum(rate(payment_attempts_total{status='success'}[5m])) / sum(rate(payment_attempts_total[5m]))"
        target: 0.99
        window: 30d
```

### 9.3 Alerting Rules

#### 9.3.1 Prometheus Alerting Rules

```yaml
# alerts.yml
groups:
    - name: reservehub.rules
      rules:
          # High Error Rate
          - alert: HighErrorRate
            expr: |
                (
                  sum(rate(http_requests_total{status_code=~"5.."}[5m])) by (service)
                  /
                  sum(rate(http_requests_total[5m])) by (service)
                ) > 0.05
            for: 2m
            labels:
                severity: critical
                service: "{{ $labels.service }}"
            annotations:
                summary: "High error rate detected for {{ $labels.service }}"
                description: "Error rate is {{ $value | humanizePercentage }} for service {{ $labels.service }}"

          # High Response Time
          - alert: HighResponseTime
            expr: |
                histogram_quantile(0.95, 
                  sum(rate(http_request_duration_seconds_bucket[5m])) by (le, service)
                ) > 1
            for: 5m
            labels:
                severity: warning
                service: "{{ $labels.service }}"
            annotations:
                summary: "High response time for {{ $labels.service }}"
                description: "95th percentile response time is {{ $value }}s"

          # Database Connection Pool Exhaustion
          - alert: DatabaseConnectionPoolHigh
            expr: database_connections_active / database_connections_max > 0.8
            for: 2m
            labels:
                severity: warning
            annotations:
                summary: "Database connection pool usage is high"
                description: "Connection pool is {{ $value | humanizePercentage }} full"

          # Low Booking Conversion Rate
          - alert: LowBookingConversionRate
            expr: booking_conversion_rate_percent < 15
            for: 10m
            labels:
                severity: warning
                team: business
            annotations:
                summary: "Booking conversion rate is unusually low"
                description: "Conversion rate is {{ $value }}%, below threshold of 15%"

          # Payment Processing Issues
          - alert: PaymentProcessingFailure
            expr: |
                (
                  sum(rate(payment_attempts_total{status="failed"}[10m]))
                  /
                  sum(rate(payment_attempts_total[10m]))
                ) > 0.05
            for: 5m
            labels:
                severity: critical
                team: payments
            annotations:
                summary: "Payment processing failure rate is high"
                description: "{{ $value | humanizePercentage }} of payments are failing"

    # Business Alerts
    - name: business.rules
      rules:
          - alert: UnusuallyHighCancellationRate
            expr: cancellation_rate_percent > 25
            for: 15m
            labels:
                severity: warning
                team: business
            annotations:
                summary: "Cancellation rate is unusually high"
                description: "Current cancellation rate is {{ $value }}%"

          - alert: RevenueDropDetected
            expr: |
                (
                  revenue_hourly - revenue_hourly offset 1d
                ) / revenue_hourly offset 1d < -0.2
            for: 30m
            labels:
                severity: critical
                team: business
            annotations:
                summary: "Significant revenue drop detected"
                description: "Revenue dropped by {{ $value | humanizePercentage }} compared to yesterday"
```

### 9.4 Distributed Tracing

#### 9.4.1 OpenTelemetry Configuration

```python
from opentelemetry import trace
from opentelemetry.exporter.jaeger.thrift import JaegerExporter
from opentelemetry.sdk.trace import TracerProvider
from opentelemetry.sdk.trace.export import BatchSpanProcessor
from opentelemetry.instrumentation.fastapi import FastAPIInstrumentor
from opentelemetry.instrumentation.sqlalchemy import SQLAlchemyInstrumentor
from opentelemetry.instrumentation.redis import RedisInstrumentor

def setup_tracing():
    # Configure tracer provider
    trace.set_tracer_provider(TracerProvider())

    # Configure Jaeger exporter
    jaeger_exporter = JaegerExporter(
        agent_host_name="jaeger-agent",
        agent_port=6831,
    )

    # Add span processor
    span_processor = BatchSpanProcessor(jaeger_exporter)
    trace.get_tracer_provider().add_span_processor(span_processor)

    # Auto-instrument frameworks
    FastAPIInstrumentor.instrument()
    SQLAlchemyInstrumentor.instrument()
    RedisInstrumentor.instrument()

# Custom spans for business logic
class BookingService:
    def __init__(self):
        self.tracer = trace.get_tracer(__name__)

    async def create_booking(self, booking_data: dict):
        with self.tracer.start_as_current_span("create_booking") as span:
            span.set_attribute("booking.service_id", booking_data["service_id"])
            span.set_attribute("booking.customer_type", booking_data["customer_type"])

            try:
                # Check availability
                with self.tracer.start_as_current_span("check_availability"):
                    available = await self.availability_service.check_slot(
                        booking_data["resource_id"],
                        booking_data["start_time"]
                    )
                    span.set_attribute("availability.result", available)

                if not available:
                    span.set_attribute("booking.result", "slot_unavailable")
                    raise BookingNotAvailableError()

                # Create booking record
                with self.tracer.start_as_current_span("create_booking_record"):
                    booking = await self.booking_repository.create(booking_data)
                    span.set_attribute("booking.id", str(booking.id))

                # Process payment if required
                if booking_data.get("require_deposit"):
                    with self.tracer.start_as_current_span("process_payment"):
                        payment_result = await self.payment_service.process_deposit(
                            booking.id, booking_data["payment_method"]
                        )
                        span.set_attribute("payment.result", payment_result.status)

                span.set_attribute("booking.result", "success")
                return booking

            except Exception as e:
                span.record_exception(e)
                span.set_attribute("booking.result", "error")
                raise
```

### 9.5 Log Management

#### 9.5.1 Structured Logging

```python
import structlog
import logging
from datetime import datetime

# Configure structured logging
structlog.configure(
    processors=[
        structlog.stdlib.filter_by_level,
        structlog.stdlib.add_logger_name,
        structlog.stdlib.add_log_level,
        structlog.stdlib.PositionalArgumentsFormatter(),
        structlog.processors.TimeStamper(fmt="iso"),
        structlog.processors.StackInfoRenderer(),
        structlog.processors.format_exc_info,
        structlog.processors.JSONRenderer()
    ],
    context_class=dict,
    logger_factory=structlog.stdlib.LoggerFactory(),
    wrapper_class=structlog.stdlib.BoundLogger,
    cache_logger_on_first_use=True,
)

class LoggingService:
    def __init__(self):
        self.logger = structlog.get_logger()

    def log_booking_event(self, event_type: str, booking_id: str, **kwargs):
        self.logger.info(
            "booking_event",
            event_type=event_type,
            booking_id=booking_id,
            tenant_id=kwargs.get("tenant_id"),
            customer_id=kwargs.get("customer_id"),
            **kwargs
        )

    def log_payment_event(self, event_type: str, payment_id: str, **kwargs):
        self.logger.info(
            "payment_event",
            event_type=event_type,
            payment_id=payment_id,
            amount=kwargs.get("amount"),
            currency=kwargs.get("currency"),
            provider=kwargs.get("provider"),
            **kwargs
        )

    def log_security_event(self, event_type: str, user_id: str, **kwargs):
        self.logger.warning(
            "security_event",
            event_type=event_type,
            user_id=user_id,
            ip_address=kwargs.get("ip_address"),
            user_agent=kwargs.get("user_agent"),
            **kwargs
        )

# Middleware for request logging
class RequestLoggingMiddleware(BaseHTTPMiddleware):
    def __init__(self, app):
        super().__init__(app)
        self.logger = structlog.get_logger()

    async def dispatch(self, request, call_next):
        start_time = datetime.utcnow()

        # Log request
        self.logger.info(
            "http_request_started",
            method=request.method,
            path=request.url.path,
            query_params=str(request.query_params),
            user_agent=request.headers.get("user-agent"),
            ip_address=request.client.host if request.client else None
        )

        response = await call_next(request)

        duration = (datetime.utcnow() - start_time).total_seconds()

        # Log response
        self.logger.info(
            "http_request_completed",
            method=request.method,
            path=request.url.path,
            status_code=response.status_code,
            duration_seconds=duration
        )

        return response
```

---

## 10. Plan de Pruebas

### 10.1 Estrategia de Testing

```mermaid
graph TD
    subgraph "Testing Pyramid"
        UNIT[Unit Tests<br/>70% Coverage]
        INTEGRATION[Integration Tests<br/>20% Coverage]
        E2E[End-to-End Tests<br/>10% Coverage]
    end

    subgraph "Specialized Testing"
        PERFORMANCE[Performance Testing]
        SECURITY[Security Testing]
        ACCESSIBILITY[Accessibility Testing]
        USABILITY[Usability Testing]
    end

    subgraph "Testing Environments"
        LOCAL[Local Development]
        STAGING[Staging Environment]
        PRODUCTION[Production Monitoring]
    end
```

### 10.2 Test Implementation

#### 10.2.1 Unit Tests

```python
# tests/unit/test_booking_service.py
import pytest
from unittest.mock import Mock, AsyncMock
from datetime import datetime, timedelta
import uuid

from services.booking_service import BookingService
from models.booking import Booking
from exceptions import BookingNotAvailableError, PaymentRequiredError

class TestBookingService:
    @pytest.fixture
    def booking_service(self):
        return BookingService(
            availability_service=Mock(),
            booking_repository=Mock(),
            payment_service=Mock(),
            notification_service=Mock()
        )

    @pytest.fixture
    def valid_booking_data(self):
        return {
            "service_id": str(uuid.uuid4()),
            "resource_id": str(uuid.uuid4()),
            "customer_id": str(uuid.uuid4()),
            "start_time": datetime.utcnow() + timedelta(days=1),
            "require_deposit": False
        }

    async def test_create_booking_success(self, booking_service, valid_booking_data):
        # Arrange
        booking_service.availability_service.check_slot = AsyncMock(return_value=True)
        expected_booking = Booking(id=uuid.uuid4(), **valid_booking_data)
        booking_service.booking_repository.create = AsyncMock(return_value=expected_booking)

        # Act
        result = await booking_service.create_booking(valid_booking_data)

        # Assert
        assert result.id == expected_booking.id
        booking_service.availability_service.check_slot.assert_called_once()
        booking_service.booking_repository.create.assert_called_once()

    async def test_create_booking_slot_unavailable(self, booking_service, valid_booking_data):
        # Arrange
        booking_service.availability_service.check_slot = AsyncMock(return_value=False)

        # Act & Assert
        with pytest.raises(BookingNotAvailableError):
            await booking_service.create_booking(valid_booking_data)

    async def test_create_booking_with_deposit(self, booking_service, valid_booking_data):
        # Arrange
        valid_booking_data["require_deposit"] = True
        valid_booking_data["payment_method"] = "pm_1234567890"

        booking_service.availability_service.check_slot = AsyncMock(return_value=True)
        expected_booking = Booking(id=uuid.uuid4(), **valid_booking_data)
        booking_service.booking_repository.create = AsyncMock(return_value=expected_booking)

        payment_result = Mock()
        payment_result.status = "succeeded"
        booking_service.payment_service.process_deposit = AsyncMock(return_value=payment_result)

        # Act
        result = await booking_service.create_booking(valid_booking_data)

        # Assert
        assert result.id == expected_booking.id
        booking_service.payment_service.process_deposit.assert_called_once()

    @pytest.mark.parametrize("payment_status,should_raise", [
        ("succeeded", False),
        ("failed", True),
        ("requires_action", True)
    ])
    async def test_deposit_payment_scenarios(self, booking_service, valid_booking_data,
                                           payment_status, should_raise):
        # Arrange
        valid_booking_data["require_deposit"] = True
        valid_booking_data["payment_method"] = "pm_1234567890"

        booking_service.availability_service.check_slot = AsyncMock(return_value=True)
        expected_booking = Booking(id=uuid.uuid4(), **valid_booking_data)
        booking_service.booking_repository.create = AsyncMock(return_value=expected_booking)

        payment_result = Mock()
        payment_result.status = payment_status
        booking_service.payment_service.process_deposit = AsyncMock(return_value=payment_result)

        # Act & Assert
        if should_raise:
            with pytest.raises(PaymentRequiredError):
                await booking_service.create_booking(valid_booking_data)
        else:
            result = await booking_service.create_booking(valid_booking_data)
            assert result.id == expected_booking.id
```

#### 10.2.2 Integration Tests

```python
# tests/integration/test_booking_flow.py
import pytest
from httpx import AsyncClient
from datetime import datetime, timedelta
import asyncio

from main import app
from database import get_database
from tests.fixtures import create_test_tenant, create_test_service

@pytest.mark.asyncio
class TestBookingIntegration:

    @pytest.fixture
    async def client(self):
        async with AsyncClient(app=app, base_url="http://test") as ac:
            yield ac

    @pytest.fixture
    async def test_data(self):
        # Set up test tenant, business, service, resource
        tenant = await create_test_tenant()
        business = await create_test_business(tenant.id)
        service = await create_test_service(business.id)
        resource = await create_test_resource(business.id)

        yield {
            "tenant_id": tenant.id,
            "service_id": service.id,
            "resource_id": resource.id
        }

        # Cleanup
        await cleanup_test_data(tenant.id)

    async def test_guest_booking_flow(self, client: AsyncClient, test_data):
        """Test complete guest booking flow without deposit"""

        # Step 1: Check availability
        availability_response = await client.get(
            "/api/v1/availability",
            params={
                "service_id": test_data["service_id"],
                "from_date": (datetime.now() + timedelta(days=1)).date().isoformat(),
                "to_date": (datetime.now() + timedelta(days=2)).date().isoformat()
            }
        )
        assert availability_response.status_code == 200

        available_slots = availability_response.json()["data"]["resources"][0]["dates"][0]["slots"]
        available_slot = next(slot for slot in available_slots if slot["available"])

        # Step 2: Create booking
        booking_data = {
            "service_id": test_data["service_id"],
            "resource_id": test_data["resource_id"],
            "start_time": available_slot["start_time"],
            "customer": {
                "first_name": "John",
                "last_name": "Doe",
                "email": "john.doe@example.com",
                "phone": "+1234567890"
            },
            "notes": "Test booking"
        }

        booking_response = await client.post("/api/v1/bookings", json=booking_data)
        assert booking_response.status_code == 201

        booking = booking_response.json()["data"]
        assert booking["status"] == "confirmed"
        assert booking["customer"]["first_name"] == "John"

        # Step 3: Verify booking was created
        get_response = await client.get(f"/api/v1/bookings/{booking['id']}")
        assert get_response.status_code == 200
        assert get_response.json()["data"]["id"] == booking["id"]

    async def test_booking_with_deposit_flow(self, client: AsyncClient, test_data):
        """Test booking flow with deposit payment"""

        # Update service to require deposit
        await client.patch(
            f"/api/v1/services/{test_data['service_id']}",
            json={"deposit_required": 25.00}
        )

        # Create payment method (mock)
        payment_method_response = await client.post(
            "/api/v1/payment-methods",
            json={
                "type": "card",
                "card": {
                    "number": "4242424242424242",
                    "exp_month": 12,
                    "exp_year": 2025,
                    "cvc": "123"
                }
            }
        )
        payment_method = payment_method_response.json()["data"]

        # Create booking with payment
        booking_data = {
            "service_id": test_data["service_id"],
            "resource_id": test_data["resource_id"],
            "start_time": (datetime.now() + timedelta(days=1, hours=10)).isoformat(),
            "customer": {
                "first_name": "Jane",
                "last_name": "Smith",
                "email": "jane.smith@example.com"
            },
            "payment_method_id": payment_method["id"]
        }

        booking_response = await client.post("/api/v1/bookings", json=booking_data)
        assert booking_response.status_code == 201

        booking = booking_response.json()["data"]
        assert booking["status"] == "confirmed"
        assert booking["deposit_paid"] == 25.00
```

#### 10.2.3 End-to-End Tests

```python
# tests/e2e/test_user_journeys.py
import pytest
from playwright.async_api import async_playwright, Page
import asyncio

@pytest.mark.e2e
class TestUserJourneys:

    @pytest.fixture
    async def page(self):
        async with async_playwright() as p:
            browser = await p.chromium.launch(headless=True)
            page = await browser.new_page()
            yield page
            await browser.close()

    async def test_complete_guest_booking_journey(self, page: Page):
        """Test complete user journey from service selection to confirmation"""

        # Navigate to booking widget
        await page.goto("http://localhost:3000/book/demo-business")

        # Select service
        await page.click('[data-testid="service-card"]:first-child')

        # Select date and time
        await page.click('[data-testid="date-picker"] .next-month')
        await page.click('[data-testid="available-slot"]:first-child')

        # Fill customer information
        await page.fill('[data-testid="first-name"]', 'John')
        await page.fill('[data-testid="last-name"]', 'Doe')
        await page.fill('[data-testid="email"]', 'john.doe@example.com')
        await page.fill('[data-testid="phone"]', '+1234567890')

        # Verify OTP (mock)
        await page.click('[data-testid="send-otp"]')
        await page.fill('[data-testid="otp-code"]', '123456')
        await page.click('[data-testid="verify-otp"]')

        # Confirm booking
        await page.click('[data-testid="confirm-booking"]')

        # Verify success page
        await page.wait_for_selector('[data-testid="booking-confirmed"]')

        booking_number = await page.text_content('[data-testid="booking-number"]')
        assert booking_number.startswith('BK-')

        # Verify confirmation email was sent (check mock)
        # This would typically check an email testing service

    async def test_admin_dashboard_operations(self, page: Page):
        """Test admin operations on the dashboard"""

        # Login as business admin
        await page.goto("http://localhost:3000/admin/login")
        await page.fill('[data-testid="email"]', 'admin@demo.com')
        await page.fill('[data-testid="password"]', 'password123')
        await page.click('[data-testid="login-button"]')

        # Navigate to bookings
        await page.click('[data-testid="nav-bookings"]')

        # Filter today's bookings
        await page.click('[data-testid="filter-today"]')

        # Select a booking and mark as completed
        await page.click('[data-testid="booking-row"]:first-child')
        await page.click('[data-testid="mark-completed"]')
        await page.click('[data-testid="confirm-action"]')

        # Verify status updated
        status = await page.text_content('[data-testid="booking-status"]')
        assert status == 'Completed'
```

### 10.3 Performance Testing

#### 10.3.1 Load Testing with K6

```javascript
// load-test.js
import http from "k6/http";
import { check, sleep } from "k6";
import { Rate, Trend } from "k6/metrics";

// Custom metrics
const errorRate = new Rate("errors");
const bookingDuration = new Trend("booking_duration");

export const options = {
    stages: [
        { duration: "5m", target: 20 }, // Ramp up to 20 users
        { duration: "10m", target: 50 }, // Stay at 50 users
        { duration: "5m", target: 100 }, // Ramp up to 100 users
        { duration: "10m", target: 100 }, // Stay at 100 users
        { duration: "5m", target: 0 }, // Ramp down
    ],
    thresholds: {
        http_req_duration: ["p(95)<500"], // 95% of requests should be below 500ms
        errors: ["rate<0.05"], // Error rate should be below 5%
    },
};

const BASE_URL = "https://api.reservehub.com/api/v1";

export default function () {
    const params = {
        headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${__ENV.API_TOKEN}`,
        },
    };

    // Test availability endpoint
    const availabilityResponse = http.get(
        `${BASE_URL}/availability?service_id=${__ENV.TEST_SERVICE_ID}&from_date=2025-09-10&to_date=2025-09-11`,
        params,
    );

    const availabilityCheck = check(availabilityResponse, {
        "availability status is 200": (r) => r.status === 200,
        "availability response time < 500ms": (r) => r.timings.duration < 500,
    });

    errorRate.add(!availabilityCheck);

    if (availabilityCheck) {
        const availability = JSON.parse(availabilityResponse.body);
        const availableSlots =
            availability.data.resources[0].dates[0].slots.filter(
                (s) => s.available,
            );

        if (availableSlots.length > 0) {
            // Create a booking
            const bookingData = {
                service_id: __ENV.TEST_SERVICE_ID,
                resource_id: __ENV.TEST_RESOURCE_ID,
                start_time: availableSlots[0].start_time,
                customer: {
                    first_name: `User${__VU}`,
                    last_name: "Test",
                    email: `user${__VU}@loadtest.com`,
                    phone: `+123456789${__VU}`,
                },
            };

            const bookingStart = Date.now();
            const bookingResponse = http.post(
                `${BASE_URL}/bookings`,
                JSON.stringify(bookingData),
                params,
            );
            const bookingEnd = Date.now();

            bookingDuration.add(bookingEnd - bookingStart);

            const bookingCheck = check(bookingResponse, {
                "booking status is 201": (r) => r.status === 201,
                "booking has ID": (r) =>
                    JSON.parse(r.body).data.id !== undefined,
            });

            errorRate.add(!bookingCheck);

            // Cleanup: Cancel the booking
            if (bookingCheck) {
                const booking = JSON.parse(bookingResponse.body);
                http.patch(
                    `${BASE_URL}/bookings/${booking.data.id}`,
                    JSON.stringify({
                        action: "cancel",
                        cancellation_reason: "load test",
                    }),
                    params,
                );
            }
        }
    }

    sleep(1); // Wait 1 second between iterations
}
```

#### 10.3.2 Database Performance Testing

```python
# tests/performance/test_database_performance.py
import pytest
import asyncio
import time
from concurrent.futures import ThreadPoolExecutor
import statistics

from database import get_database
from services.availability_service import AvailabilityService

class TestDatabasePerformance:

    @pytest.mark.performance
    async def test_availability_query_performance(self):
        """Test availability query performance under load"""

        availability_service = AvailabilityService()

        async def single_availability_check():
            start_time = time.time()
            await availability_service.get_available_slots(
                service_id="test-service-id",
                resource_id="test-resource-id",
                from_date="2025-09-10",
                to_date="2025-09-17"
            )
            return time.time() - start_time

        # Run 100 concurrent availability checks
        tasks = [single_availability_check() for _ in range(100)]
        durations = await asyncio.gather(*tasks)

        # Performance assertions
        avg_duration = statistics.mean(durations)
        p95_duration = statistics.quantiles(durations, n=20)[18]  # 95th percentile

        assert avg_duration < 0.1, f"Average duration {avg_duration}s exceeds 100ms"
        assert p95_duration < 0.2, f"95th percentile {p95_duration}s exceeds 200ms"
        assert max(durations) < 1.0, f"Max duration {max(durations)}s exceeds 1s"

    @pytest.mark.performance
    async def test_booking_creation_throughput(self):
        """Test booking creation throughput"""

        from services.booking_service import BookingService

        booking_service = BookingService()

        async def create_test_booking(booking_index):
            booking_data = {
                "service_id": "test-service-id",
                "resource_id": "test-resource-id",
                "start_time": f"2025-09-10T{10 + booking_index % 8:02d}:00:00Z",
                "customer": {
                    "first_name": f"Customer{booking_index}",
                    "email": f"customer{booking_index}@test.com"
                }
            }

            start_time = time.time()
            try:
                await booking_service.create_booking(booking_data)
                return time.time() - start_time, True
            except Exception:
                return time.time() - start_time, False

        # Create 50 bookings concurrently
        start_time = time.time()
        tasks = [create_test_booking(i) for i in range(50)]
        results = await asyncio.gather(*tasks, return_exceptions=True)
        total_time = time.time() - start_time

        successful_results = [(duration, success) for duration, success in results if isinstance(results, tuple) and success]

        throughput = len(successful_results) / total_time
        success_rate = len(successful_results) / len(results)

        assert throughput > 10, f"Throughput {throughput} bookings/s is below minimum of 10/s"
        assert success_rate > 0.95, f"Success rate {success_rate} is below 95%"
```

### 10.4 Security Testing

#### 10.4.1 Automated Security Scanning

```python
# tests/security/test_security.py
import pytest
import requests
import jwt
from datetime import datetime, timedelta

class TestSecurity:

    @pytest.mark.security
    def test_sql_injection_protection(self):
        """Test SQL injection protection on various endpoints"""

        malicious_payloads = [
            "'; DROP TABLE bookings; --",
            "' OR '1'='1",
            "1' UNION SELECT * FROM users --"
        ]

        base_url = "https://api.reservehub.com/api/v1"

        for payload in malicious_payloads:
            # Test search endpoint
            response = requests.get(
                f"{base_url}/bookings",
                params={"search": payload},
                headers={"Authorization": f"Bearer {self.get_test_token()}"}
            )

            # Should not return SQL error or unexpected data
            assert response.status_code in [200, 400], "Unexpected status code for SQL injection test"
            assert "sql" not in response.text.lower(), "SQL error detected in response"
            assert "error" not in response.json().get("error", {}).get("message", "").lower()

    @pytest.mark.security
    def test_jwt_token_validation(self):
        """Test JWT token validation and expiration"""

        # Test expired token
        expired_payload = {
            "sub": "test-user",
            "exp": datetime.utcnow() - timedelta(hours=1)
        }
        expired_token = jwt.encode(expired_payload, "secret", algorithm="HS256")

        response = requests.get(
            "https://api.reservehub.com/api/v1/bookings",
            headers={"Authorization": f"Bearer {expired_token}"}
        )

        assert response.status_code == 401, "Expired token should be rejected"

    @pytest.mark.security
    def test_rate_limiting(self):
        """Test rate limiting on API endpoints"""

        base_url = "https://api.reservehub.com/api/v1"

        # Make requests rapidly to trigger rate limiting
        for i in range(105):  # Assuming limit is 100/minute
            response = requests.get(f"{base_url}/availability")

            if i > 100:
                assert response.status_code == 429, "Rate limiting not triggered"
                assert "X-RateLimit-Reset" in response.headers

    @pytest.mark.security
    def test_authorization_checks(self):
        """Test proper authorization for tenant isolation"""

        # Create tokens for different tenants
        tenant1_token = self.create_token_for_tenant("tenant-1")
        tenant2_token = self.create_token_for_tenant("tenant-2")

        # Create booking with tenant1 token
        booking_data = {
            "service_id": "tenant1-service-id",
            "resource_id": "tenant1-resource-id",
            "start_time": "2025-09-10T10:00:00Z",
            "customer": {"first_name": "Test", "email": "test@example.com"}
        }

        response = requests.post(
            "https://api.reservehub.com/api/v1/bookings",
            json=booking_data,
            headers={"Authorization": f"Bearer {tenant1_token}"}
        )

        assert response.status_code == 201
        booking_id = response.json()["data"]["id"]

        # Try to access booking with tenant2 token
        response = requests.get(
            f"https://api.reservehub.com/api/v1/bookings/{booking_id}",
            headers={"Authorization": f"Bearer {tenant2_token}"}
        )

        assert response.status_code == 404, "Cross-tenant access should be denied"

    def create_token_for_tenant(self, tenant_id: str) -> str:
        payload = {
            "sub": f"user-{tenant_id}",
            "tenant_id": tenant_id,
            "exp": datetime.utcnow() + timedelta(hours=1)
        }
        return jwt.encode(payload, "secret", algorithm="HS256")
```

---

## 11. Documentación para Desarrolladores

### 11.1 Guía de Configuración Local

#### 11.1.1 Prerequisites

> **Normativo openSaaS:** stack PHP/Laravel, no Python/Node como runtime del producto.

```bash
# Required software versions (openSaaS)
- PHP 8.3+ / Laravel 13+
- Livewire 4 + FluxUI 2 + TailwindCSS
- PostgreSQL 15+ (RLS obligatorio)
- Redis 7+ (cache, colas, locks, sesiones)
- Docker & Docker Compose
- Pest + Pint + Larastan
```

#### 11.1.2 Setup Instructions

```bash
# 1. Clone repository
git clone https://github.com/company/reservehub.git
cd reservehub

# 2. Setup backend
cd backend
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
pip install -r requirements.txt
pip install -r requirements-dev.txt

# 3. Setup database
docker-compose up -d postgres redis
alembic upgrade head

# 4. Load sample data
python scripts/load_sample_data.py

# 5. Start backend services
uvicorn main:app --reload --port 8000

# 6. Setup frontend (new terminal)
cd ../frontend
npm install
npm run dev

# 7. Run tests
pytest
npm test
```

#### 11.1.3 Environment Configuration

```bash
# .env.local
DATABASE_URL=postgresql://reservehub:password@localhost:5432/reservehub_dev
REDIS_URL=redis://localhost:6379/0

# JWT Configuration
JWT_SECRET_KEY=your-super-secret-key-change-in-production
JWT_ALGORITHM=HS256
JWT_ACCESS_TOKEN_EXPIRE_MINUTES=30
JWT_REFRESH_TOKEN_EXPIRE_DAYS=30

# Payment Providers
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Email/SMS
SENDGRID_API_KEY=SG....
TWILIO_ACCOUNT_SID=AC...
TWILIO_AUTH_TOKEN=...

# External Services
GOOGLE_CALENDAR_CLIENT_ID=...
GOOGLE_CALENDAR_CLIENT_SECRET=...

# Development
DEBUG=true
LOG_LEVEL=debug
```

### 11.2 Código de Ejemplo

> **Normativo openSaaS:** los ejemplos siguientes en Python/FastAPI/React son **referencia histórica del dominio**. La implementación real usa Actions PHP (`final readonly class` + `execute(DTO)`), Livewire 4 + FluxUI 2, Jobs `TenantAware`, Policies + RLS. No crear microservicios nuevos; la lógica de reservas vive en la capa de producto como módulos con `Domain/Application/Infrastructure/Interface`, comunicados vía Contracts/Events.

#### 11.2.1 Crear un Nuevo Microservicio (derogado como patrón — ver nota anterior)

```python
# services/notification_service.py
from abc import ABC, abstractmethod
from typing import Dict, List
import asyncio
from datetime import datetime

from models.notification import Notification, NotificationTemplate
from providers.email_provider import EmailProvider
from providers.sms_provider import SMSProvider
from utils.template_engine import TemplateEngine

class NotificationChannel(ABC):
    @abstractmethod
    async def send(self, notification: Notification) -> bool:
        pass

class EmailChannel(NotificationChannel):
    def __init__(self, provider: EmailProvider):
        self.provider = provider

    async def send(self, notification: Notification) -> bool:
        try:
            await self.provider.send_email(
                to=notification.recipient,
                subject=notification.subject,
                html_body=notification.body
            )
            return True
        except Exception as e:
            # Log error
            return False

class NotificationService:
    def __init__(self):
        self.channels = {
            'email': EmailChannel(EmailProvider()),
            'sms': SMSChannel(SMSProvider()),
            'whatsapp': WhatsAppChannel(WhatsAppProvider())
        }
        self.template_engine = TemplateEngine()

    async def send_notification(self,
                              template_id: str,
                              recipient: str,
                              channel: str,
                              variables: Dict) -> bool:

        # Get template
        template = await self.get_template(template_id)
        if not template:
            raise ValueError(f"Template {template_id} not found")

        # Render template
        subject = self.template_engine.render(template.subject, variables)
        body = self.template_engine.render(template.body, variables)

        # Create notification record
        notification = Notification(
            template_id=template_id,
            channel=channel,
            recipient=recipient,
            subject=subject,
            body=body,
            variables=variables
        )

        # Send via appropriate channel
        channel_handler = self.channels.get(channel)
        if not channel_handler:
            raise ValueError(f"Channel {channel} not supported")

        success = await channel_handler.send(notification)

        # Update notification status
        notification.status = 'sent' if success else 'failed'
        notification.sent_at = datetime.utcnow() if success else None

        await self.save_notification(notification)
        return success

    async def send_booking_confirmation(self, booking_id: str):
        """Example: Send booking confirmation notifications"""

        booking = await self.get_booking(booking_id)
        variables = {
            'customer_name': booking.customer.first_name,
            'service_name': booking.service.name,
            'booking_date': booking.start_time.strftime('%Y-%m-%d'),
            'booking_time': booking.start_time.strftime('%H:%M'),
            'location_name': booking.location.name,
            'booking_number': booking.booking_number
        }

        # Send email confirmation
        await self.send_notification(
            template_id='booking_confirmation_email',
            recipient=booking.customer.email,
            channel='email',
            variables=variables
        )

        # Send SMS if phone provided
        if booking.customer.phone:
            await self.send_notification(
                template_id='booking_confirmation_sms',
                recipient=booking.customer.phone,
                channel='sms',
                variables=variables
            )
```

#### 11.2.2 Agregar Nuevo Endpoint

```python
# routers/analytics.py
from fastapi import APIRouter, Depends, Query
from typing import Optional, List
from datetime import date

from dependencies import get_current_user, get_database
from services.analytics_service import AnalyticsService
from schemas.analytics import (
    RevenueMetrics, BookingMetrics, CustomerMetrics,
    MetricsFilters, DateRange
)

router = APIRouter(prefix="/analytics", tags=["analytics"])

@router.get("/revenue", response_model=RevenueMetrics)
async def get_revenue_metrics(
    from_date: date = Query(..., description="Start date for metrics"),
    to_date: date = Query(..., description="End date for metrics"),
    location_ids: Optional[List[str]] = Query(None, description="Filter by locations"),
    service_ids: Optional[List[str]] = Query(None, description="Filter by services"),
    current_user = Depends(get_current_user),
    db = Depends(get_database)
):
    """Get revenue metrics for the specified period"""

    analytics_service = AnalyticsService(db)

    filters = MetricsFilters(
        date_range=DateRange(from_date=from_date, to_date=to_date),
        location_ids=location_ids,
        service_ids=service_ids,
        tenant_id=current_user.tenant_id  # Ensure tenant isolation
    )

    metrics = await analytics_service.get_revenue_metrics(filters)
    return metrics

@router.get("/bookings/funnel", response_model=List[BookingMetrics])
async def get_booking_funnel(
    from_date: date = Query(...),
    to_date: date = Query(...),
    current_user = Depends(get_current_user),
    db = Depends(get_database)
):
    """Get booking conversion funnel metrics"""

    analytics_service = AnalyticsService(db)

    funnel_data = await analytics_service.get_booking_funnel(
        tenant_id=current_user.tenant_id,
        from_date=from_date,
        to_date=to_date
    )

    return funnel_data

# Include in main application
from main import app
app.include_router(router)
```

#### 11.2.3 Frontend Component Example (referencia histórica React — implementar en Livewire + FluxUI)

```typescript
// components/BookingForm.tsx
import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useBooking } from '@/hooks/useBooking';
import { usePayment } from '@/hooks/usePayment';
import { Service, TimeSlot, Customer } from '@/types';

const bookingSchema = z.object({
  firstName: z.string().min(1, 'First name is required'),
  lastName: z.string().optional(),
  email: z.string().email('Invalid email address'),
  phone: z.string().regex(/^\+?[1-9]\d{1,14}$/, 'Invalid phone number'),
  notes: z.string().optional(),
});

type BookingFormData = z.infer<typeof bookingSchema>;

interface BookingFormProps {
  service: Service;
  selectedSlot: TimeSlot;
  onSuccess: (bookingId: string) => void;
  onError: (error: string) => void;
}

export const BookingForm: React.FC<BookingFormProps> = ({
  service,
  selectedSlot,
  onSuccess,
  onError
}) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [requiresPayment, setRequiresPayment] = useState(false);

  const { createBooking } = useBooking();
  const { processPayment } = usePayment();

  const {
    register,
    handleSubmit,
    formState: { errors },
    watch
  } = useForm<BookingFormData>({
    resolver: zodResolver(bookingSchema)
  });

  useEffect(() => {
    // Check if service requires deposit
    setRequiresPayment(service.depositRequired > 0);
  }, [service]);

  const onSubmit = async (data: BookingFormData) => {
    setIsSubmitting(true);

    try {
      // Create booking
      const bookingResult = await createBooking({
        serviceId: service.id,
        resourceId: selectedSlot.resourceId,
        startTime: selectedSlot.startTime,
        customer: {
          firstName: data.firstName,
          lastName: data.lastName,
          email: data.email,
          phone: data.phone,
          type: 'guest'
        },
        notes: data.notes,
        requireDeposit: requiresPayment
      });

      if (requiresPayment) {
        // Process payment for deposit
        const paymentResult = await processPayment({
          bookingId: bookingResult.id,
          amount: service.depositRequired,
          currency: service.currency,
          savePaymentMethod: false
        });

        if (!paymentResult.success) {
          throw new Error(paymentResult.error || 'Payment failed');
        }
      }

      onSuccess(bookingResult.id);
    } catch (error) {
      onError(error instanceof Error ? error.message : 'Booking failed');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div>
          <Input
            {...register('firstName')}
            placeholder="First Name *"
            error={errors.firstName?.message}
          />
        </div>
        <div>
          <Input
            {...register('lastName')}
            placeholder="Last Name"
            error={errors.lastName?.message}
          />
        </div>
      </div>

      <Input
        {...register('email')}
        type="email"
        placeholder="Email Address *"
        error={errors.email?.message}
      />

      <Input
        {...register('phone')}
        type="tel"
        placeholder="Phone Number *"
        error={errors.phone?.message}
      />

      <textarea
        {...register('notes')}
        placeholder="Special requests or notes"
        className="w-full p-2 border rounded-md"
        rows={3}
      />

      {requiresPayment && (
        <div className="bg-blue-50 p-4 rounded-md">
          <p className="text-sm text-blue-800">
            This service requires a deposit of ${service.depositRequired} to confirm your booking.
          </p>
        </div>
      )}

      <Button
        type="submit"
        disabled={isSubmitting}
        className="w-full"
      >
        {isSubmitting
          ? 'Processing...'
          : requiresPayment
            ? `Book & Pay ${service.depositRequired}`
            : 'Confirm Booking'
        }
      </Button>
    </form>
  );
};
```

### 11.3 Guías de Contribución

#### 11.3.1 Git Workflow

```bash
# Feature development workflow
git checkout main
git pull origin main
git checkout -b feature/booking-reminders

# Make changes
git add .
git commit -m "feat: add booking reminder system

- Add reminder scheduling service
- Implement email/SMS reminder templates
- Add configuration for reminder timing
- Include tests for reminder logic"

git push origin feature/booking-reminders

# Create PR via GitHub/GitLab
```

#### 11.3.2 Code Standards

```python
# Python Code Style Guidelines

# 1. Use type hints
def create_booking(booking_data: dict) -> Booking:
    pass

# 2. Document functions with docstrings
def calculate_refund_amount(booking: Booking, cancellation_time: datetime) -> Decimal:
    """
    Calculate refund amount based on cancellation policy.

    Args:
        booking: The booking being cancelled
        cancellation_time: When the cancellation is requested

    Returns:
        Decimal: Amount to be refunded

    Raises:
        ValidationError: If booking is not cancellable
    """
    pass

# 3. Use descriptive variable names
customer_email = booking.customer.email  # Good
ce = booking.customer.email  # Bad

# 4. Handle errors explicitly
try:
    payment_result = await payment_service.charge_card(amount)
    return payment_result
except PaymentDeclinedError as e:
    logger.warning("Payment declined", extra={"reason": e.decline_reason})
    raise BookingPaymentError("Your payment was declined") from e
except Exception as e:
    logger.error("Unexpected payment error", exc_info=True)
    raise BookingPaymentError("Payment processing failed") from e
```

### 11.4 Troubleshooting Guide

#### 11.4.1 Problemas Comunes

**Problema: Disponibilidad no se actualiza después de crear reserva**

```python
# Solución: Verificar invalidación de cache
@cache_key("availability:{service_id}:{resource_id}:{date}")
async def get_availability(service_id: str, resource_id: str, date: str):
    # Implementation
    pass

# Al crear booking, invalidar cache
async def create_booking(booking_data: dict):
    booking = await booking_repository.create(booking_data)

    # Invalidate availability cache
    cache_key = f"availability:{booking.service_id}:{booking.resource_id}:{booking.date}"
    await cache.delete(cache_key)

    return booking
```

**Problema: Pagos duplicados**

```python
# Solución: Implementar idempotencia
from fastapi import Header

@router.post("/payments")
async def process_payment(
    payment_data: PaymentRequest,
    idempotency_key: str = Header(..., alias="Idempotency-Key")
):
    # Check if already processed
    existing_payment = await payment_repository.get_by_idempotency_key(idempotency_key)
    if existing_payment:
        return existing_payment

    # Process new payment
    payment = await payment_service.process(payment_data, idempotency_key)
    return payment
```

**Problema: Deadlocks en la base de datos**

```python
# Solución: Ordenar locks consistentemente
async def transfer_booking(from_slot_id: str, to_slot_id: str):
    # Always lock slots in consistent order to prevent deadlocks
    slot_ids = sorted([from_slot_id, to_slot_id])

    async with database.transaction():
        for slot_id in slot_ids:
            await database.execute(
                "SELECT * FROM slots WHERE id = $1 FOR UPDATE",
                slot_id
            )

        # Perform transfer
        await update_booking_slot(from_slot_id, to_slot_id)
```

### 11.5 Deployment Checklist

#### 11.5.1 Pre-Deployment

- [ ] All tests passing (unit, integration, e2e)
- [ ] Security scan completed
- [ ] Performance benchmarks meet requirements
- [ ] Database migrations tested
- [ ] Feature flags configured
- [ ] Monitoring dashboards updated
- [ ] Documentation updated
- [ ] Rollback plan prepared

#### 11.5.2 Deployment Steps

```bash
# 1. Deploy database changes
kubectl apply -f k8s/migrations-job.yaml
kubectl wait --for=condition=complete job/db-migration

# 2. Deploy new application version
kubectl set image deployment/api api=reservehub/api:v1.2.3
kubectl rollout status deployment/api

# 3. Run smoke tests
kubectl apply -f k8s/smoke-tests-job.yaml

# 4. Update frontend
kubectl set image deployment/frontend frontend=reservehub/frontend:v1.2.3

# 5. Verify deployment
kubectl get pods -l app=reservehub
curl https://api.reservehub.com/health
```

#### 11.5.3 Post-Deployment

- [ ] Health checks passing
- [ ] Key metrics within normal ranges
- [ ] Error rates normal
- [ ] Performance metrics stable
- [ ] User acceptance testing
- [ ] Monitor for 24 hours
- [ ] Update documentation
- [ ] Communicate to stakeholders

---

## Conclusión

Esta documentación técnica completa de Reasy proporciona una base sólida para el desarrollo, implementación y mantenimiento de un sistema SaaS de gestión de reservas empresarial. La arquitectura propuesta es escalable, segura y mantiene altos estándares de calidad.

### Próximos Pasos Recomendados:

1. **Fase 1** (0-3 meses): Implementar MVP con funcionalidades core
2. **Fase 2** (3-6 meses): Añadir funciones avanzadas y optimizaciones
3. **Fase 3** (6-9 meses): Marketplace y funcionalidades enterprise

### Recursos Adicionales:

- **Repositorio de Código**: `https://github.com/company/reservehub`
- **Documentación API**: `https://docs.reservehub.com`
- **Status Page**: `https://status.reservehub.com`
- **Community Forum**: `https://community.reservehub.com`

---

## 12. Análisis de Costos y ROI

### 12.1 Estructura de Costos de Infraestructura

#### 12.1.1 Costos por Ambiente

```yaml
# Infrastructure Costs (Monthly USD)
environments:
    development:
        compute: $200
        database: $100
        storage: $50
        networking: $30
        monitoring: $50
        total: $430

    staging:
        compute: $500
        database: $300
        storage: $100
        networking: $80
        monitoring: $100
        total: $1,080

    production:
        compute: $2,000
        database: $1,200
        storage: $400
        networking: $300
        monitoring: $300
        redis: $200
        cdn: $150
        backup: $100
        total: $4,650

# Third-party Services
third_party_monthly:
    stripe_processing: "2.9% + $0.30 per transaction"
    sendgrid_email: $89.95 # Pro plan
    twilio_sms: "$0.0075 per SMS"
    monitoring_tools: $200
    security_tools: $150
    total_fixed: $440
```

#### 12.1.2 Escalabilidad de Costos

```python
class CostCalculator:
    def __init__(self):
        self.base_costs = {
            'compute_per_pod': 50,  # USD/month
            'database_connection': 10,  # USD/month per 100 connections
            'storage_gb': 0.10,  # USD/month per GB
            'bandwidth_gb': 0.09,  # USD per GB
        }

    def calculate_monthly_cost(self, metrics: dict) -> dict:
        """Calculate infrastructure costs based on usage metrics"""

        # Compute costs (auto-scaling pods)
        avg_pods = metrics['avg_pods']
        compute_cost = avg_pods * self.base_costs['compute_per_pod']

        # Database costs
        db_connections = metrics['max_db_connections']
        db_cost = (db_connections / 100) * self.base_costs['database_connection']

        # Storage costs
        storage_gb = metrics['storage_used_gb']
        storage_cost = storage_gb * self.base_costs['storage_gb']

        # Bandwidth costs
        bandwidth_gb = metrics['bandwidth_gb']
        bandwidth_cost = bandwidth_gb * self.base_costs['bandwidth_gb']

        # Transaction fees
        transactions = metrics['monthly_transactions']
        transaction_fees = transactions * 0.029 + transactions * 0.30

        return {
            'compute': compute_cost,
            'database': db_cost,
            'storage': storage_cost,
            'bandwidth': bandwidth_cost,
            'transaction_fees': transaction_fees,
            'total': compute_cost + db_cost + storage_cost + bandwidth_cost + transaction_fees
        }

# Example cost projection
def project_costs_by_scale():
    scenarios = {
        'startup': {
            'tenants': 100,
            'monthly_bookings': 10000,
            'storage_gb': 50,
            'avg_pods': 5
        },
        'growth': {
            'tenants': 1000,
            'monthly_bookings': 100000,
            'storage_gb': 500,
            'avg_pods': 20
        },
        'enterprise': {
            'tenants': 10000,
            'monthly_bookings': 1000000,
            'storage_gb': 2000,
            'avg_pods': 100
        }
    }

    calculator = CostCalculator()
    for scenario, metrics in scenarios.items():
        costs = calculator.calculate_monthly_cost(metrics)
        print(f"{scenario.upper()}: ${costs['total']:.2f}/month")
```

### 12.2 Modelo de Monetización

#### 12.2.1 Revenue Streams

```python
class RevenueModel:
    def __init__(self):
        self.plans = {
            'starter': {
                'monthly_fee': 49,
                'transaction_fee': 0.035,
                'fixed_fee': 0.30,
                'bookings_limit': 200,
                'users_limit': 3
            },
            'professional': {
                'monthly_fee': 149,
                'transaction_fee': 0.029,
                'fixed_fee': 0.30,
                'bookings_limit': 1000,
                'users_limit': 10
            },
            'business': {
                'monthly_fee': 399,
                'transaction_fee': 0.024,
                'fixed_fee': 0.30,
                'bookings_limit': 5000,
                'users_limit': 25
            },
            'enterprise': {
                'monthly_fee': 'custom',
                'transaction_fee': 0.019,
                'fixed_fee': 0.30,
                'bookings_limit': 'unlimited',
                'users_limit': 'unlimited'
            }
        }

    def calculate_tenant_revenue(self, plan: str, monthly_bookings: int, avg_booking_value: float) -> dict:
        """Calculate monthly revenue from a tenant"""

        plan_config = self.plans[plan]

        # Subscription revenue
        subscription_revenue = plan_config['monthly_fee']

        # Transaction revenue
        transaction_revenue = (
            monthly_bookings * plan_config['transaction_fee'] * avg_booking_value +
            monthly_bookings * plan_config['fixed_fee']
        )

        total_revenue = subscription_revenue + transaction_revenue

        return {
            'subscription': subscription_revenue,
            'transactions': transaction_revenue,
            'total': total_revenue,
            'bookings': monthly_bookings
        }

# Revenue projections
def project_revenue():
    model = RevenueModel()

    # Year 1 projections
    year_1_tenants = {
        'starter': {'count': 400, 'avg_bookings': 150, 'avg_value': 75},
        'professional': {'count': 80, 'avg_bookings': 600, 'avg_value': 120},
        'business': {'count': 15, 'avg_bookings': 2000, 'avg_value': 200},
        'enterprise': {'count': 3, 'avg_bookings': 8000, 'avg_value': 300}
    }

    total_mrr = 0
    for plan, data in year_1_tenants.items():
        revenue_per_tenant = model.calculate_tenant_revenue(
            plan, data['avg_bookings'], data['avg_value']
        )
        plan_mrr = revenue_per_tenant['total'] * data['count']
        total_mrr += plan_mrr

        print(f"{plan}: ${plan_mrr:,.2f} MRR ({data['count']} tenants)")

    print(f"Total MRR: ${total_mrr:,.2f}")
    print(f"Projected ARR: ${total_mrr * 12:,.2f}")
```

### 12.3 ROI Analysis

#### 12.3.1 Business Impact Metrics

```python
class ROICalculator:
    def calculate_customer_roi(self, before_metrics: dict, after_metrics: dict) -> dict:
        """Calculate ROI for customers using Reasy"""

        # Time savings
        booking_time_saved = (before_metrics['avg_booking_time_minutes'] -
                             after_metrics['avg_booking_time_minutes'])
        monthly_bookings = after_metrics['monthly_bookings']
        staff_hourly_rate = before_metrics['staff_hourly_rate']

        time_savings_value = (
            (booking_time_saved / 60) * monthly_bookings * staff_hourly_rate * 12
        )

        # No-show reduction
        no_show_reduction = (before_metrics['no_show_rate'] -
                           after_metrics['no_show_rate'])
        avg_booking_value = after_metrics['avg_booking_value']
        no_show_savings = no_show_reduction * monthly_bookings * avg_booking_value * 12

        # Increased bookings (conversion improvement)
        conversion_improvement = (after_metrics['conversion_rate'] -
                                before_metrics['conversion_rate'])
        monthly_visitors = after_metrics['monthly_website_visitors']
        increased_revenue = (conversion_improvement * monthly_visitors *
                           avg_booking_value * 12)

        # Total annual savings
        total_annual_savings = (time_savings_value + no_show_savings +
                              increased_revenue)

        # ReserveHub annual cost
        annual_cost = after_metrics['reservehub_monthly_cost'] * 12

        # ROI calculation
        roi_percentage = ((total_annual_savings - annual_cost) / annual_cost) * 100

        return {
            'time_savings_value': time_savings_value,
            'no_show_savings': no_show_savings,
            'increased_revenue': increased_revenue,
            'total_savings': total_annual_savings,
            'annual_cost': annual_cost,
            'net_benefit': total_annual_savings - annual_cost,
            'roi_percentage': roi_percentage,
            'payback_months': annual_cost / (total_annual_savings / 12)
        }

# Example ROI calculation for a typical customer
example_roi = ROICalculator().calculate_customer_roi(
    before_metrics={
        'avg_booking_time_minutes': 8,
        'no_show_rate': 0.20,
        'conversion_rate': 0.12,
        'staff_hourly_rate': 25
    },
    after_metrics={
        'avg_booking_time_minutes': 2,
        'no_show_rate': 0.08,
        'conversion_rate': 0.18,
        'monthly_bookings': 500,
        'avg_booking_value': 120,
        'monthly_website_visitors': 3000,
        'reservehub_monthly_cost': 399
    }
)

print(f"Customer ROI: {example_roi['roi_percentage']:.1f}%")
print(f"Payback period: {example_roi['payback_months']:.1f} months")
```

---

## 13. Plan de Marketing Técnico y Go-to-Market

### 13.1 Segmentación de Mercado

#### 13.1.1 Vertical Markets

```yaml
target_verticals:
    healthcare:
        - medical_clinics
        - dental_practices
        - mental_health_providers
        - physical_therapy
        - veterinary_clinics

    beauty_wellness:
        - hair_salons
        - nail_salons
        - spas
        - massage_therapy
        - fitness_studios

    professional_services:
        - legal_consultations
        - financial_advisors
        - real_estate_agents
        - consulting_services
        - coaching_services

    education_training:
        - tutoring_centers
        - language_schools
        - driving_schools
        - certification_programs
        - workshops

    hospitality_events:
        - restaurants
        - event_venues
        - meeting_rooms
        - coworking_spaces
        - recreational_facilities

market_sizing:
    total_addressable_market: "$12.8B" # Global appointment scheduling software
    serviceable_addressable_market: "$3.2B" # SMB segment
    serviceable_obtainable_market: "$160M" # 5-year target (5% SAM)
```

#### 13.1.2 Customer Personas

```typescript
interface CustomerPersona {
    name: string;
    role: string;
    businessSize: string;
    painPoints: string[];
    goals: string[];
    techSavviness: "low" | "medium" | "high";
    budget: string;
    decisionProcess: string[];
}

const customerPersonas: CustomerPersona[] = [
    {
        name: "Sarah - Small Business Owner",
        role: "Owner/Manager of beauty salon",
        businessSize: "1-5 employees",
        painPoints: [
            "Spending too much time on phone booking appointments",
            "Frequent no-shows impacting revenue",
            "Double bookings causing customer frustration",
            "Difficulty tracking customer history",
        ],
        goals: [
            "Reduce time spent on administrative tasks",
            "Increase booking conversion rate",
            "Improve customer experience",
            "Grow revenue by 20%",
        ],
        techSavviness: "medium",
        budget: "$50-200/month",
        decisionProcess: ["Research online", "Free trial", "Quick decision"],
    },

    {
        name: "Dr. Michael - Healthcare Practice Manager",
        role: "Practice Manager at medical clinic",
        businessSize: "10-50 employees",
        painPoints: [
            "Complex scheduling with multiple providers",
            "Insurance verification requirements",
            "Patient no-shows",
            "Compliance and audit requirements",
        ],
        goals: [
            "Optimize provider utilization",
            "Reduce patient wait times",
            "Ensure compliance",
            "Integrate with existing EMR",
        ],
        techSavviness: "high",
        budget: "$200-800/month",
        decisionProcess: [
            "Committee evaluation",
            "Pilot program",
            "Gradual rollout",
        ],
    },
];
```

### 13.2 Estrategia de Adquisición

#### 13.2.1 Marketing Channels

```python
class MarketingChannelROI:
    def __init__(self):
        self.channels = {
            'google_ads': {
                'monthly_spend': 15000,
                'leads_per_month': 500,
                'conversion_rate': 0.08,
                'avg_ltv': 2400,
                'cac': 375
            },
            'facebook_ads': {
                'monthly_spend': 8000,
                'leads_per_month': 400,
                'conversion_rate': 0.06,
                'avg_ltv': 2000,
                'cac': 333
            },
            'content_marketing': {
                'monthly_spend': 5000,
                'leads_per_month': 200,
                'conversion_rate': 0.12,
                'avg_ltv': 3000,
                'cac': 208
            },
            'partner_referrals': {
                'monthly_spend': 3000,
                'leads_per_month': 100,
                'conversion_rate': 0.25,
                'avg_ltv': 3600,
                'cac': 120
            },
            'webinars': {
                'monthly_spend': 4000,
                'leads_per_month': 150,
                'conversion_rate': 0.15,
                'avg_ltv': 2800,
                'cac': 178
            }
        }

    def calculate_channel_roi(self, channel: str) -> dict:
        data = self.channels[channel]
        monthly_customers = data['leads_per_month'] * data['conversion_rate']
        monthly_revenue = monthly_customers * data['avg_ltv']
        roi = (monthly_revenue - data['monthly_spend']) / data['monthly_spend']

        return {
            'channel': channel,
            'monthly_spend': data['monthly_spend'],
            'monthly_customers': monthly_customers,
            'monthly_revenue': monthly_revenue,
            'cac': data['cac'],
            'ltv_cac_ratio': data['avg_ltv'] / data['cac'],
            'roi': roi
        }

    def optimize_budget_allocation(self, total_budget: float) -> dict:
        """Optimize budget allocation across channels based on ROI"""
        channel_rois = {}
        for channel in self.channels:
            channel_rois[channel] = self.calculate_channel_roi(channel)

        # Sort channels by LTV:CAC ratio
        sorted_channels = sorted(
            channel_rois.items(),
            key=lambda x: x[1]['ltv_cac_ratio'],
            reverse=True
        )

        return {
            'recommended_allocation': sorted_channels,
            'top_channel': sorted_channels[0][0],
            'total_projected_customers': sum(
                data['monthly_customers'] for _, data in sorted_channels
            )
        }
```

#### 13.2.2 Sales Funnel Optimization

```python
class SalesFunnelAnalytics:
    def __init__(self):
        self.funnel_stages = {
            'visitors': {'conversion_rate': 1.0, 'avg_time_spent': '2m'},
            'leads': {'conversion_rate': 0.15, 'avg_time_spent': '10m'},
            'trial_signup': {'conversion_rate': 0.40, 'avg_time_spent': '14d'},
            'qualified_demo': {'conversion_rate': 0.60, 'avg_time_spent': '45m'},
            'proposal': {'conversion_rate': 0.45, 'avg_time_spent': '7d'},
            'closed_won': {'conversion_rate': 0.30, 'avg_time_spent': '30d'}
        }

    def calculate_funnel_metrics(self, monthly_visitors: int) -> dict:
        """Calculate conversion through each funnel stage"""
        current_volume = monthly_visitors
        funnel_data = {}

        for stage, data in self.funnel_stages.items():
            if stage == 'visitors':
                stage_volume = current_volume
            else:
                stage_volume = current_volume * data['conversion_rate']

            funnel_data[stage] = {
                'volume': stage_volume,
                'conversion_rate': data['conversion_rate'],
                'drop_off': current_volume - stage_volume if stage != 'visitors' else 0
            }

            current_volume = stage_volume

        return funnel_data

    def identify_optimization_opportunities(self, funnel_data: dict) -> list:
        """Identify stages with highest improvement potential"""
        opportunities = []

        for stage, data in funnel_data.items():
            if stage != 'visitors' and data['conversion_rate'] < 0.5:
                opportunities.append({
                    'stage': stage,
                    'current_conversion': data['conversion_rate'],
                    'potential_improvement': 0.5 - data['conversion_rate'],
                    'impact_customers': data['volume'] * (0.5 - data['conversion_rate'])
                })

        return sorted(opportunities, key=lambda x: x['impact_customers'], reverse=True)
```

### 13.3 Pricing Strategy

#### 13.3.1 Value-Based Pricing Model

````python
class PricingStrategy:
    def __init__(self):
        self.value_metrics = {
            'time_savings': {
                'hours_saved_per_month': 40,
                'hourly_rate': 25,
                'monthly_value': 1000
            },
            'no_show_reduction': {
                'bookings_per_month': 500,
                'no_show_rate_improvement': 0.12,
                'avg_booking_value': 100,
                'monthly_value': 600
            },
            'conversion_improvement': {
                'monthly_visitors': 2000,
                'conversion_improvement': 0.05,
                'avg_booking_value': 100,
                'monthly_value': 1000
            }
        }

    def calculate_customer_value(self, business_size: str) -> dict:
        """Calculate monthly value delivered to customer"""
        multipliers = {
            'small': 1.0,
            'medium': 2.5,
            'large': 5.0,
            'enterprise': 10.0
        }

        multiplier = multipliers.get(business_size, 1.0)
        total_monthly_value = 0

        value_breakdown = {}
        for metric, data in self.value_metrics.items():
            metric_value = data['monthly_value'] * multiplier
            value_breakdown[metric] = metric_value
            total_monthly_value += metric_value

        return {
            'total_monthly_value': total_monthly_value,
            'breakdown': value_breakdown,
            'recommended_price': total_monthly_value * 0.15,  # 15% of value
            'price_ceiling': total_monthly_value * 0.3,  # 30% of value
            'value_multiplier': multiplier
        }

    def competitive_analysis(self) -> dict:
        """Analyze competitor pricing for positioning"""
        competitors = {
            'acuity_scheduling': {'starter': 14, 'professional': 23, 'powerhouse': 45},
            'calendly': {'basic': 8, 'essential': 12, 'professional': 20},
---

## 14. Casos de Uso Expandidos y Complementarios

### **Análisis de los Casos de Uso Existentes**

Los casos de uso proporcionados son sólidos y cubren los flujos principales. Sin embargo, para crear un sistema verdaderamente empresarial, sugiero las siguientes **adiciones y mejoras**:

---

### **1. Administrador de Plataforma (SaaS Owner) - AMPLIADO**

#### **Requisitos Funcionales Adicionales:**

**RF1.8 - Gestión de Compliance y Auditoría:**
- Debe poder generar reportes de compliance (GDPR, PCI-DSS) para todos los tenants
- Acceso a logs de auditoría centralizados con capacidad de búsqueda y filtrado
- Herramientas para gestionar solicitudes de eliminación de datos (GDPR)

**RF1.9 - Monitoreo de Salud del Sistema:**
- Dashboard de infraestructura con métricas de performance en tiempo real
- Alertas automáticas para incidentes críticos
- Capacidad de ejecutar maintenance mode por tenant o globally

**RF1.10 - Gestión de Integraciones y Partners:**
- Panel para gestionar integraciones de terceros (Stripe, Twilio, etc.)
- Sistema de webhooks para partners y desarrolladores externos
- Gestión de API keys y rate limits por tenant

**RF1.11 - Análisis Predictivo:**
- Identificación de tenants en riesgo de churn
- Análisis de patrones de uso para optimización
- Recomendaciones automáticas de upgrade de plan

```python
# Ejemplo: Sistema de Early Warning para Churn
class ChurnPredictionService:
    def analyze_tenant_risk(self, tenant_id: str) -> dict:
        """Analiza el riesgo de churn de un tenant"""

        metrics = self.get_tenant_metrics(tenant_id)

        risk_factors = {
            'usage_decline': self.calculate_usage_trend(metrics),
            'support_tickets': self.analyze_support_patterns(tenant_id),
            'payment_issues': self.check_payment_history(tenant_id),
            'feature_adoption': self.measure_feature_adoption(tenant_id),
            'login_frequency': self.track_admin_engagement(tenant_id)
        }

        risk_score = self.calculate_composite_risk(risk_factors)

        return {
            'tenant_id': tenant_id,
            'risk_score': risk_score,  # 0-100
            'risk_level': self.categorize_risk(risk_score),
            'factors': risk_factors,
            'recommended_actions': self.generate_retention_actions(risk_factors)
        }
````

---

### **2. Administrador de Negocio (Tenant Owner) - AMPLIADO**

#### **Requisitos Funcionales Adicionales:**

**RF2.13 - Gestión Financiera Avanzada:**

- Panel de facturación con detalle de transacciones por período
- Reportes de impuestos con soporte para múltiples jurisdicciones
- Gestión de descuentos, promociones y códigos de cupón
- Análisis de rentabilidad por servicio y recurso
  **RF2.14 - Gestión de Inventario y Recursos:**
- Control de stock para productos relacionados (ej. productos de spa)
- Gestión de equipamiento con calendarios de mantenimiento
- Reserva de salas/recursos compartidos
  **RF2.15 - Automatización de Marketing:**
- Campañas automáticas de email marketing
- Seguimiento de clientes inactivos
- Sistema de referidos y loyalty programs básicos
- Integración con redes sociales para promociones
  **RF2.16 - Gestión de Waitlists:**
- Lista de espera automática para horarios ocupados
- Notificación automática cuando se libera un slot
- Priorización de waitlist (clientes VIP, orden de llegada)

```typescript
// Ejemplo: Sistema de Waitlist
interface WaitlistEntry {
    id: string;
    customer_id: string;
    service_id: string;
    preferred_times: TimeRange[];
    priority_level: "standard" | "premium" | "vip";
    created_at: Date;
    notification_preferences: {
        email: boolean;
        sms: boolean;
        advance_notice_minutes: number;
    };
}

class WaitlistService {
    async addToWaitlist(entry: WaitlistEntry): Promise<void> {
        await this.validateWaitlistEntry(entry);
        await this.waitlistRepository.create(entry);
        await this.notificationService.confirmWaitlistAddition(entry);
    }

    async processSlotAvailability(slot: TimeSlot): Promise<void> {
        const waitlistEntries = await this.getMatchingWaitlistEntries(slot);
        const prioritizedEntries = this.prioritizeEntries(waitlistEntries);

        for (const entry of prioritizedEntries) {
            const notified = await this.notificationService.sendSlotAvailable(
                entry,
                slot,
                entry.notification_preferences.advance_notice_minutes,
            );

            if (notified) {
                await this.markAsNotified(entry.id, slot.id);
                break; // Solo notificar al primero en la lista
            }
        }
    }
}
```

---

### **3. Usuarios de Negocio (Staff/Empleados) - AMPLIADO**

#### **Requisitos Funcionales Adicionales:**

**RF3.6 - Gestión de Comisiones:**

- Seguimiento de ingresos generados por empleado
- Cálculo automático de comisiones según reglas configurables
- Reportes de performance individual
  **RF3.7 - Comunicación Interna:**
- Chat interno entre empleados del negocio
- Notas compartidas sobre clientes
- Sistema de handoffs entre turnos
  **RF3.8 - Gestión de Tareas:**
- Lista de tareas pendientes (follow-ups, llamadas)
- Recordatorios personalizados
- Integración con el calendario para bloquear tiempo
  **RF3.9 - Check-in/Check-out de Clientes:**
- Funcionalidad móvil para marcar llegada de clientes
- Gestión de sala de espera virtual
- Tiempo real de servicios vs. tiempo planificado

---

### **4. Clientes Registrados - AMPLIADO**

#### **Requisitos Funcionales Adicionales:**

**RF4.7 - Programa de Fidelidad:**

- Acumulación de puntos por reservas completadas
- Niveles de membresía (Bronze, Silver, Gold)
- Beneficios personalizados según nivel
  **RF4.8 - Preferencias Avanzadas:**
- Profesionales favoritos
- Horarios preferidos
- Servicios frecuentes para booking rápido
- Recordatorios personalizados
  **RF4.9 - Social Features:**
- Compartir experiencias en redes sociales
- Referir amigos con incentivos
- Reviews y ratings de servicios
  **RF4.10 - Gestión de Suscripciones:**
- Membresías mensuales o anuales
- Paquetes de servicios (ej. 10 sesiones de yoga)
- Auto-renovación y gestión de billing

```python
# Ejemplo: Sistema de Loyalty
class LoyaltyProgram:
    def __init__(self):
        self.tiers = {
            'bronze': {'min_points': 0, 'discount': 0, 'perks': []},
            'silver': {'min_points': 500, 'discount': 0.05, 'perks': ['priority_booking']},
            'gold': {'min_points': 1500, 'discount': 0.10, 'perks': ['priority_booking', 'free_cancellation']},
            'platinum': {'min_points': 3000, 'discount': 0.15, 'perks': ['priority_booking', 'free_cancellation', 'concierge_service']}
        }

    def calculate_points(self, booking_amount: Decimal, service_type: str) -> int:
        base_points = int(booking_amount)  # 1 point per dollar

        # Bonus points for specific services
        multipliers = {
            'premium': 2.0,
            'package': 1.5,
            'regular': 1.0
        }

        return int(base_points * multipliers.get(service_type, 1.0))

    def determine_tier(self, total_points: int) -> str:
        for tier in ['platinum', 'gold', 'silver', 'bronze']:
            if total_points >= self.tiers[tier]['min_points']:
                return tier
        return 'bronze'

    def apply_tier_benefits(self, customer: Customer, booking: Booking) -> Booking:
        tier = self.determine_tier(customer.loyalty_points)
        benefits = self.tiers[tier]

        # Apply discount
        if benefits['discount'] > 0:
            booking.discount_amount = booking.total_amount * Decimal(str(benefits['discount']))
            booking.final_amount = booking.total_amount - booking.discount_amount

        # Apply perks
        if 'free_cancellation' in benefits['perks']:
            booking.free_cancellation_until = booking.start_time  # Can cancel anytime

        return booking
```

---

### **5. Clientes No Registrados (Guest) - AMPLIADO**

#### **Requisitos Funcionales Adicionales:**

**RF5.7 - Conversión a Cliente Registrado:**

- Incentivos post-servicio para crear cuenta
- Importación automática de historial de reservas al registrarse
- Ofertas especiales para primera reserva como miembro
  **RF5.8 - Booking Múltiple:**
- Reservar múltiples servicios en una sesión
- Reservas para múltiples personas (grupo familiar)
- Coordinación automática de horarios para servicios consecutivos

---

### **NUEVOS CASOS DE USO CRÍTICOS**

### **6. Super Admin/Soporte Técnico**

Personal de soporte que necesita acceso limitado para resolver problemas específicos.

#### **Requisitos Funcionales:**

**RF6.1 - Soporte Multitenancy:**

- Acceso de solo lectura a datos de tenant específicos (con autorización)
- Capacidad de impersonar usuarios para debugging
- Logs de todas las acciones de soporte
  **RF6.2 - Troubleshooting Tools:**
- Panel de diagnóstico de problemas comunes
- Herramientas de limpieza de datos inconsistentes
- Capacidad de reenviar notificaciones fallidas

### **7. Desarrollador/Integrador de Terceros**

Desarrolladores que construyen integraciones con Reasy.

#### **Requisitos Funcionales:**

**RF7.1 - Developer Portal:**

- Documentación interactiva de APIs
- Sandbox environment para pruebas
- Gestión de API keys y webhooks
  **RF7.2 - Marketplace de Apps:**
- Directorio de integraciones disponibles
- Proceso de instalación de apps de terceros
- Revenue sharing para partners

### **8. Auditor/Compliance Officer**

Personal encargado de auditorías y cumplimiento regulatorio.

#### **Requisitos Funcionales:**

**RF8.1 - Audit Trail:**

- Acceso completo a logs inmutables
- Reportes de cumplimiento automatizados
- Exportación de datos en formatos estándar
  **RF8.2 - Privacy Management:**
- Dashboard de solicitudes GDPR
- Herramientas de anonimización de datos
- Gestión de retención de datos

---

### **Flujos de Negocio Adicionales Críticos**

#### **Gestión de Crisis y Contingencias**

```python
class CrisisManagementService:
    async def handle_mass_cancellation(self, reason: str, affected_slots: List[str]):
        """Maneja cancelaciones masivas (ej. emergencia, enfermedad)"""

        affected_bookings = await self.get_bookings_by_slots(affected_slots)

        for booking in affected_bookings:
            # Cancelar automáticamente
            await self.booking_service.cancel_booking(
                booking.id,
                reason=reason,
                refund_policy='full_refund'  # Política especial de crisis
            )

            # Notificar cliente con opciones de reprogramación
            await self.notification_service.send_crisis_notification(
                booking.customer_id,
                {
                    'reason': reason,
                    'refund_amount': booking.paid_amount,
                    'rescheduling_link': self.generate_priority_rescheduling_link(booking)
                }
            )

            # Agregar a waitlist prioritaria
            await self.waitlist_service.add_priority_entry(booking)

    async def emergency_broadcast(self, tenant_id: str, message: str):
        """Envía mensaje de emergencia a todos los clientes con reservas próximas"""

        upcoming_bookings = await self.get_upcoming_bookings(
            tenant_id,
            hours_ahead=48
        )

        for booking in upcoming_bookings:
            await self.notification_service.send_emergency_alert(
                booking.customer_id,
                message
            )
```

#### **Análisis de Fraude**

```python
class FraudDetectionService:
    def analyze_booking_risk(self, booking_request: dict) -> dict:
        """Analiza el riesgo de fraude en una reserva"""

        risk_factors = []
        risk_score = 0

        # Múltiples reservas desde la misma IP en corto tiempo
        recent_bookings = self.get_recent_bookings_by_ip(
            booking_request['ip_address'],
            hours=1
        )
        if len(recent_bookings) > 3:
            risk_factors.append('multiple_bookings_same_ip')
            risk_score += 30

        # Email/teléfono temporal o sospechoso
        if self.is_temporary_email(booking_request['customer']['email']):
            risk_factors.append('temporary_email')
            risk_score += 20

        # Patrones de cancelación sospechosos
        customer_history = self.get_customer_history(
            booking_request['customer']['email']
        )
        if customer_history and customer_history['cancellation_rate'] > 0.8:
            risk_factors.append('high_cancellation_rate')
            risk_score += 25

        return {
            'risk_score': min(risk_score, 100),
            'risk_level': self.categorize_risk(risk_score),
            'factors': risk_factors,
            'recommended_action': self.get_recommended_action(risk_score)
        }

    def get_recommended_action(self, risk_score: int) -> str:
        if risk_score >= 70:
            return 'block'
        elif risk_score >= 40:
            return 'require_verification'
        else:
            return 'approve'
```

---

### **Métricas y KPIs Adicionales por Stakeholder**

#### **Para Administrador de Plataforma:**

- **Platform Health Score** (composite de uptime, performance, satisfaction)
- **Tenant Lifetime Value (TLV)**
- **Feature Adoption Rate** por tenant y plan
- **Support Ticket Resolution Time**
- **Churn Risk Distribution**

#### **Para Administrador de Negocio:**

- **Revenue per Available Hour (RevPAH)**
- **Customer Acquisition Cost (CAC) por canal**
- **Staff Utilization Rate**
- **Service Profitability Analysis**
- **Customer Satisfaction Score (CSAT) por servicio**

#### **Para Staff:**

- **Personal Revenue Generated**
- **Customer Retention Rate** (clientes que repiten con el mismo staff)
- **Average Service Duration** vs planificado
- **Upselling Success Rate**

---

### **Integraciones Adicionales Recomendadas**

```yaml
recommended_integrations:
    accounting:
        - quickbooks
        - xero
        - freshbooks

    marketing:
        - mailchimp
        - constant_contact
        - hubspot

    communication:
        - slack
        - microsoft_teams
        - whatsapp_business

    pos_systems:
        - square
        - toast
        - lightspeed

    review_management:
        - google_my_business
        - yelp
        - trustpilot

    analytics:
        - google_analytics
        - facebook_pixel
        - hotjar
```

Estas ampliaciones transformarían Reasy de un sistema básico de reservas a una **plataforma empresarial completa** que puede competir con soluciones establecidas como Acuity, Calendly Pro, o Square Appointments, pero con capacidades superiores de personalización y escalabilidad.

**Última actualización**: Septiembre 2025
**Versión**: 2.1
**Mantenido por**: Equipo de Arquitectura Reasy
