# Product Requirements Document (PRD)
## Reasy — Plataforma SaaS de Gestión de Reservas Multi-Tenant

---

| Campo | Detalle |
|---|---|
| **Producto** | Reasy |
| **Versión del documento** | 1.0 |
| **Estado** | En revisión |
| **Fecha** | Septiembre 2025 |
| **Product Owner** | Por definir |
| **Última actualización** | Septiembre 2025 |

---

## Historial de Versiones

| Versión | Fecha | Autor | Cambios |
|---|---|---|---|
| 0.1 | Ago 2025 | Equipo de Producto | Borrador inicial |
| 0.5 | Sep 2025 | Equipo de Arquitectura | Incorporación de requisitos técnicos |
| 1.0 | Sep 2025 | Equipo de Producto | Versión completa para aprobación |

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Contexto y Oportunidad de Mercado](#2-contexto-y-oportunidad-de-mercado)
3. [Objetivos del Producto](#3-objetivos-del-producto)
4. [Usuarios y Personas](#4-usuarios-y-personas)
5. [Alcance del Producto](#5-alcance-del-producto)
6. [Arquitectura de Información y Flujos Principales](#6-arquitectura-de-información-y-flujos-principales)
7. [Requisitos Funcionales Detallados](#7-requisitos-funcionales-detallados)
8. [Requisitos No Funcionales](#8-requisitos-no-funcionales)
9. [Modelo de Datos Conceptual](#9-modelo-de-datos-conceptual)
10. [Integraciones Externas](#10-integraciones-externas)
11. [Modelo de Negocio y Monetización](#11-modelo-de-negocio-y-monetización)
12. [Métricas de Éxito (KPIs)](#12-métricas-de-éxito-kpis)
13. [Priorización y Roadmap](#13-priorización-y-roadmap)
14. [Riesgos y Mitigaciones](#14-riesgos-y-mitigaciones)
15. [Supuestos y Restricciones](#15-supuestos-y-restricciones)
16. [Criterios de Aceptación por Feature](#16-criterios-de-aceptación-por-feature)
17. [Glosario](#17-glosario)

---

## 1. Resumen Ejecutivo

### 1.1 Qué es Reasy

Reasy es una plataforma **SaaS multi-tenant** de gestión de reservas y citas diseñada para negocios de servicios: salones de belleza, barberías, clínicas, estudios de fitness, terapeutas, y cualquier negocio que venda tiempo de sus recursos (personal, salas, equipamiento) a clientes.

El sistema permite que múltiples negocios independientes (tenants) operen dentro de una única instalación del software, completamente aislados entre sí, con configuraciones, precios, políticas y datos propios.

### 1.2 El Problema Central

Los negocios de servicios en mercados latinoamericanos gestionan su agenda de tres formas igualmente disfuncionales: WhatsApp, llamadas telefónicas o cuadernos. El resultado es predecible:

- **Tasa de no-shows del 20–30%** sin mecanismo de penalización efectivo.
- **Pérdida de $500–$2,000 USD/mes** por capacidad desperdiciada en negocios medianos.
- **Imposibilidad de escalar** sin incrementar personal administrativo linealmente.
- **Cero datos históricos** para tomar decisiones de negocio.
- **Doble-bookings** como evento recurrente que destruye la experiencia del cliente.

### 1.3 La Solución

Reasy automatiza el ciclo completo:

```
[Cliente descubre slot] → [Reserva online en <3 min] → [Paga depósito opcional]
        → [Recibe confirmación inmediata] → [Recibe recordatorios automáticos]
        → [Se presenta / No-show] → [Negocio procesa reembolso o retiene depósito]
        → [Datos fluyen a analytics] → [Negocio toma mejores decisiones]
```

### 1.4 Propuesta de Valor por Actor

| Actor | Problema resuelto | Beneficio cuantificable |
|---|---|---|
| Negocio (Tenant) | No-shows, doble-bookings, gestión manual | −60% no-shows, +35% conversión de reservas |
| Cliente final | Proceso de reserva engorroso, incertidumbre | Reserva en <3 minutos, confirmación instantánea |
| Plataforma (Reasy) | Mercado LATAM sub-atendido | MRR escalable, bajo CAC por vertical |

---

## 2. Contexto y Oportunidad de Mercado

### 2.1 Tamaño del Mercado

| Segmento | Valor | Fuente |
|---|---|---|
| TAM Global (software de citas) | $12.8B USD | Proyección 2024 |
| SAM (SMB en LATAM) | $3.2B USD | Estimación interna |
| SOM (objetivo 5 años) | $160M USD | 5% del SAM |
| CAGR esperado | 13.1% | Hasta 2030 |

### 2.2 Análisis Competitivo

| Competidor | Precio base | Fortaleza | Debilidad crítica para LATAM |
|---|---|---|---|
| Calendly | $8–20/mes | UX simple, brand recognition | Sin depósitos, sin multi-recurso real, en inglés |
| Acuity Scheduling | $16–61/mes | Features completos | Sin localización LATAM, sin multi-sede real |
| Square Appointments | Gratis–$60/mes | Integración POS-pagos | Solo Square Payments, USA-céntrico |
| Fresha | Comisión por venta | Foco beauty, gratuito | Modelo agresivo de fees, dependencia total |
| Soluciones locales | Variable | Precio accesible | Tecnología obsoleta, sin API, sin escalabilidad |

**Ventana de oportunidad:** Ningún competidor tiene arquitectura multi-tenant nativa con soporte genuino para mercados hispanohablantes, políticas de depósito granulares por servicio, y modelo de precios accesible para negocios LATAM.

### 2.3 Verticales Objetivo (Fase 1)

1. **Beauty & Wellness**: Salones de belleza, barberías, spas, nail salons.
2. **Fitness & Bienestar**: Estudios de yoga, personal trainers, fisioterapeutas.
3. **Servicios Profesionales**: Consultores, coaches, abogados, contadores.

**Mercado geográfico inicial**: Panamá → Colombia → México (expansión por MRR).

---

## 3. Objetivos del Producto

### 3.1 Objetivos de Negocio

| ID | Objetivo | Métrica | Target Año 1 | Target Año 3 |
|---|---|---|---|---|
| OBJ-01 | Crecer base de tenants activos | Tenants activos | 500 | 5,000 |
| OBJ-02 | Generar MRR sostenible | MRR (USD) | $74,500 | $745,000 |
| OBJ-03 | Demostrar valor a tenants | Reducción de no-shows | ≥50% vs baseline | ≥60% |
| OBJ-04 | Construir confianza del mercado | Uptime mensual | 99.5% | 99.9% |
| OBJ-05 | Posicionarse como líder LATAM | NPS de tenants | ≥40 | ≥65 |

### 3.2 Objetivos de Producto (MVP)

- Que un negocio pueda hacer onboarding completo y recibir su primera reserva en **<30 minutos** desde el registro.
- Que un cliente final pueda completar una reserva **sin crear una cuenta** en menos de **3 minutos**.
- Que el sistema **nunca genere doble-bookings** bajo ninguna condición de carga.
- Que los depósitos se procesen y los reembolsos se ejecuten **automáticamente** según la política configurada.

### 3.3 Lo que Reasy NO es (Anti-objetivos)

- No es un sistema de punto de venta (POS).
- No es una plataforma de marketing / CRM completo.
- No es un sistema de gestión de empleados (RRHH).
- No reemplaza herramientas de contabilidad (QuickBooks, Xero).
- No tiene app móvil nativa en v1 (PWA + responsive).

---

## 4. Usuarios y Personas

### 4.1 Mapa de Actores

```
┌─────────────────────────────────────────────────────┐
│                  PLATAFORMA REASY                   │
│                                                     │
│  [Admin Plataforma] ──────── gestiona ──────────┐  │
│                                                  ↓  │
│                              ┌──── [Tenant A] ───┐  │
│                              │  [Admin Negocio]  │  │
│                              │  [Staff]          │  │
│                              │  [Clientes]       │  │
│                              └───────────────────┘  │
│                              ┌──── [Tenant B] ───┐  │
│                              │       ...         │  │
│                              └───────────────────┘  │
│                                                     │
│  [Super Admin] ────── soporte cross-tenant          │
│  [Auditor] ─────────── compliance read-only         │
│  [Desarrollador] ───── API / webhooks               │
└─────────────────────────────────────────────────────┘
```

### 4.2 Persona 1: El Dueño del Negocio (Tenant Owner)

**Nombre ficticio:** Carlos Herrera  
**Negocio:** Barbería "El Corte Fino" — 4 barberos, 1 sede en Ciudad de Panamá  
**Edad:** 38 años  
**Tech savviness:** Media — usa WhatsApp, Instagram, tiene un sitio web básico

**Contexto actual:**
- Gestiona 80–120 citas por semana vía WhatsApp.
- Sus barberos atienden a clientes sin previo aviso, generando tiempos muertos.
- Sufre 15–20 no-shows por semana; pierde ~$600/mes en slots vacíos.
- No tiene datos históricos de qué servicio es más rentable.

**Lo que necesita:**
- Configurar el sistema una vez y que funcione solo.
- Ver cuánto está generando cada barbero y qué servicios más se piden.
- Que los clientes paguen algo al reservar para que no fallen.
- Notificaciones automáticas sin tener que enviarlas manualmente.

**Frustraciones con soluciones actuales:**
- Calendly está en inglés y no tiene la opción de cobrar depósito.
- Las soluciones locales son lentas y no tienen app.
- No puede pagarse $200/mes por una herramienta cuando factura $4,000.

**Cita representativa:**
> *"Necesito algo que me avise cuando alguien cancela, que le cobre algo por adelantado, y que mis clientes puedan reservar solos. Que no sea complicado."*

---

### 4.3 Persona 2: El Empleado / Profesional (Staff)

**Nombre ficticio:** Valentina Ríos  
**Rol:** Estilista en salón de belleza, 3 años de experiencia  
**Edad:** 26 años  
**Tech savviness:** Alta en redes sociales, media en herramientas de trabajo

**Contexto actual:**
- Su agenda la maneja la recepcionista; a veces llega a trabajar y hay sorpresas.
- Usa el celular para todo; preferiría ver su agenda ahí.
- Quiere saber cuánto generó para calcular sus comisiones.

**Lo que necesita:**
- Ver su agenda del día en el celular antes de salir de casa.
- Recibir notificación inmediata cuando le asignan o cambian una cita.
- Poder marcar cuando un cliente llegó y cuando terminó el servicio.
- Ver sus ingresos y comisiones acumuladas.

---

### 4.4 Persona 3: El Cliente Registrado

**Nombre ficticio:** Ana González  
**Perfil:** Profesional, 32 años, clienta frecuente de salón  
**Tech savviness:** Alta — compra online, usa apps bancarias

**Lo que necesita:**
- Reservar cuando se le ocurre (domingo a las 11pm).
- No tener que hablar con nadie para reservar.
- Recibir recordatorio el día anterior.
- Poder cancelar o cambiar sin drama si tiene un imprevisto.

**Frustración actual:**
> *"Tengo que escribirle a la recepcionista por WhatsApp y a veces me contesta al día siguiente. Para entonces ya conseguí turno en otro salón."*

---

### 4.5 Persona 4: El Cliente Guest

**Nombre ficticio:** Roberto Méndez  
**Perfil:** Nuevo cliente, 28 años, vio el salón en Instagram  
**Tech savviness:** Media

**Lo que necesita:**
- Reservar sin crear otra cuenta ni contraseña que va a olvidar.
- Que el proceso sea rápido y claro.
- Recibir confirmación de que su cita está agendada.

---

### 4.6 Persona 5: El Administrador de Plataforma

**Nombre ficticio:** Diego Morales  
**Rol:** Operations Manager en Reasy  
**Edad:** 34 años  
**Tech savviness:** Muy alta

**Lo que necesita:**
- Ver en un dashboard cuántos tenants activos hay y cuánto MRR generan.
- Detectar tenants en riesgo de churn antes de que cancelen.
- Poder suspender o modificar un tenant sin tocar código.
- Alertas automáticas si algo falla en producción.

---

## 5. Alcance del Producto

### 5.1 Módulos del Sistema

```
REASY
├── MODULE: Platform Admin (SaaS layer)
│   ├── Tenant Management
│   ├── Subscription Plans
│   ├── Platform Billing
│   ├── System Health Dashboard
│   └── Compliance & Audit (cross-tenant)
│
├── MODULE: Business Admin (Tenant layer)
│   ├── Business Configuration
│   ├── Locations & Resources
│   ├── Services & Pricing
│   ├── Staff Management
│   ├── Schedule Engine
│   ├── Booking Management
│   ├── Customer CRM
│   ├── Payments & Refunds
│   ├── Notifications Configuration
│   ├── Analytics Dashboard
│   └── Waitlist Management
│
├── MODULE: Staff Portal
│   ├── Personal Calendar
│   ├── Appointment Management
│   ├── Customer Check-in
│   └── Commission Tracker
│
├── MODULE: Customer Portal
│   ├── Booking Flow (Guest + Registered)
│   ├── Self-Management (cancel/reschedule)
│   ├── Payment & History
│   └── Loyalty Program
│
├── MODULE: Public API
│   ├── REST API v1
│   ├── Webhooks
│   └── Developer Portal
│
└── SHARED: Infrastructure
    ├── Auth & RBAC
    ├── Multi-tenancy Isolation (RLS)
    ├── Notification Dispatch
    ├── Payment Processing
    └── Audit Trail
```

### 5.2 Límites del Sistema (In/Out of Scope)

#### En Scope (v1 — MVP)
- Gestión completa del ciclo de vida de reserva con máquina de estados.
- Flujo de reserva para clientes guest y registrados (web + widget).
- Cobro de depósitos opcionales mediante Stripe.
- Reembolsos automáticos según política de cancelación configurada.
- Notificaciones por email (confirmación, recordatorio, cancelación, reembolso).
- Motor de disponibilidad en tiempo real con protección contra race conditions.
- Multi-tenant con aislamiento completo de datos por RLS en PostgreSQL.
- Panel administrativo para negocio con gestión de servicios, recursos y horarios.
- Dashboard analítico básico (ocupación, ingresos, no-shows).
- RBAC completo (Admin, Gerente, Recepcionista, Profesional, Cliente).
- Audit trail de acciones críticas.
- OTP para verificación de clientes guest.
- Múltiples sedes por negocio.
- Gestión de waitlist básica.

#### Fuera de Scope (v1)
- App móvil nativa (iOS / Android).
- Notificaciones por SMS y WhatsApp (v1.5).
- Programa de fidelización con puntos (v1.5).
- Análisis predictivo de churn (v2).
- Marketplace de integraciones (v2).
- Integración con Google Calendar / Outlook (v1.5).
- Facturación multi-jurisdiccional avanzada (v2).
- POS / integración con hardware de punto de venta (fuera del roadmap actual).
- Chat interno entre staff (v1.5).
- Soporte para múltiples idiomas en la UI (v1.5).

---

## 6. Arquitectura de Información y Flujos Principales

### 6.1 Jerarquía de Entidades

```
Platform
└── Tenant (Negocio)
    ├── Business (Perfil del negocio)
    │   ├── Location (Sede)
    │   │   ├── Resource (Personal / Sala / Equipo)
    │   │   │   └── Schedule (Horario base + Excepciones)
    │   │   └── Service (disponible en esta sede)
    │   ├── Service
    │   │   ├── ServicePricing (precios por contexto)
    │   │   └── CancellationPolicy (reglas por servicio)
    │   └── User (Empleado con rol)
    │
    ├── Customer (Cliente del negocio — separado de User)
    │   └── Booking
    │       └── Payment → Refund
    │
    ├── NotificationTemplate (plantillas personalizadas)
    └── WaitlistEntry
```

**Decisión de diseño crítica:** `Customer` y `User` son entidades separadas. Un cliente no tiene acceso al panel administrativo. Un empleado no aparece en el historial de reservas como cliente. Esta separación es intencional por razones de privacidad (GDPR) y coherencia de dominio.

### 6.2 Flujo Principal: Reserva de Cliente Guest

```
1. Cliente accede al widget de reserva del negocio
   └── Widget cargado en <1s desde CDN

2. Selección de servicio
   └── Lista con nombre, duración, precio, descripción

3. Selección de recurso (opcional)
   └── "Cualquier disponible" o selección específica

4. Selección de fecha y slot
   └── Calendario con slots en tiempo real (caché 5 min, invalidación inmediata)
   └── Motor de disponibilidad: horario base − excepciones − bookings activos − buffers

5. Ingreso de datos del cliente
   └── Nombre, email ó teléfono (mínimo uno válido)
   └── Validación en tiempo real (formato, duplicados)

6. Verificación OTP
   └── Código enviado a email/SMS
   └── Ventana: 10 minutos, máximo 3 intentos

7. Confirmación y pago (si aplica depósito)
   ├── Sin depósito → Booking creado en estado "Confirmed"
   └── Con depósito → Stripe Checkout → webhook payment_intent.succeeded
                   → Booking transiciona a "Confirmed"
                   └── Timeout 15 min sin pago → Booking "Expired"

8. Confirmación enviada
   └── Email con detalles + enlace de gestión único (token firmado, expira en 7 días)

9. Recordatorios automáticos
   └── 24 horas antes (email)
   └── 2 horas antes (email)
```

### 6.3 Máquina de Estados del Booking

```
                    ┌─────────────────────────────────┐
                    │                                 │
              ┌─────▼──────┐                         │
              │   DRAFT    │ (creado, pendiente pago o │
              └─────┬──────┘  confirmación inmediata) │
                    │                                 │
        ┌───────────┼────────────┐                   │
        │           │            │                   │
[depósito requerido] │  [sin depósito]               │
        │           │            │                   │
┌───────▼────────┐  │   ┌────────▼────────┐          │
│PENDING_PAYMENT │  │   │   CONFIRMED     │          │
└───────┬────────┘  │   └────────┬────────┘          │
        │           └────────────┘                   │
  ┌─────┴──────┐           │                         │
  │            │     ┌─────┼──────────┐              │
[pago OK] [timeout]  │     │          │              │
  │            │  [reagen.] [cancel] [fecha pasa]    │
  │       ┌────▼──┐  │     │          │              │
  │       │EXPIRED│  │  ┌──▼──────┐   │              │
  │       └───────┘  │  │CANCELLED│   │              │
  │                  │  └─────────┘   │              │
  └──────────────────┘           ┌────▼─────┐        │
        ↓ CONFIRMED              │          │        │
   (ver arriba)            [asistió]  [no asistió]  │
                                 │          │        │
                          ┌──────▼──┐ ┌────▼──────┐ │
                          │COMPLETED│ │ NO_SHOW   │ │
                          └─────────┘ └───────────┘ │
                                                    │
                          ┌───────────┐             │
                          │RESCHEDULED├─────────────┘
                          └─────┬─────┘  (→ nuevo CONFIRMED)
                                │
                         (crea nuevo Booking)
```

### 6.4 Flujo de Cancelación y Reembolso

```
Cliente / Admin solicita cancelación
      │
      ▼
¿Está dentro de la ventana de cancelación con reembolso?
      │
      ├── SÍ → ¿Cuánto tiempo antes?
      │         ├── >48h → 100% reembolso
      │         ├── 24–48h → 50% reembolso (configurable)
      │         └── <24h → 0% reembolso (configurable)
      │
      └── NO (cancelación tardía) → 0% reembolso (depósito retenido)
                                    │
                                    └── Excepción: override de Admin

Reembolso calculado → Stripe Refund API → Email de confirmación → Booking: CANCELLED
```

### 6.5 Flujo de Onboarding de Nuevo Tenant

```
1. Registro en reasy.app/register
   └── Nombre, email, negocio, país, teléfono
   └── Plan seleccionado (o free trial 14 días)

2. Verificación de email

3. Setup Wizard (obligatorio, no saltable):
   Paso 1: Perfil del negocio (nombre, logo, dirección, zona horaria, moneda)
   Paso 2: Primera sede/ubicación
   Paso 3: Primer servicio (nombre, duración, precio, política de depósito)
   Paso 4: Primer recurso/profesional (nombre, horario)
   Paso 5: Revisión y "Lanzar"

4. Dashboard con checklist de próximos pasos:
   ✅ Negocio configurado
   ⬜ Conectar Stripe (para cobrar depósitos)
   ⬜ Personalizar notificaciones
   ⬜ Compartir link de reservas
   ⬜ Invitar empleados

5. Link de widget/reservas disponible inmediatamente
```

---

## 7. Requisitos Funcionales Detallados

Los requisitos están organizados por módulo funcional. Cada requisito tiene: ID único, descripción, criterios de aceptación verificables, prioridad y dependencias.

---

### 7.1 Módulo: Gestión de Plataforma (Admin SaaS)

#### RF-PLT-001: Gestión de Ciclo de Vida de Tenants

**Descripción:** El Administrador de Plataforma puede crear, ver, editar, suspender y eliminar cuentas de tenants desde un panel centralizado.

**Prioridad:** 🔴 Crítica

**Criterios de Aceptación:**
- El admin puede crear un tenant ingresando: nombre, email del owner, plan asignado, país.
- Al crear el tenant, el sistema envía una invitación de onboarding al email del owner.
- El admin puede suspender un tenant: el sistema bloquea el acceso del tenant y sus usuarios, pero no elimina datos.
- El admin puede reactivar un tenant suspendido restaurando el acceso inmediatamente.
- El admin puede eliminar un tenant: el sistema archiva todos los datos durante 90 días antes de eliminarlos permanentemente.
- La búsqueda de tenants funciona por nombre, email, plan, estado y fecha de creación.
- El admin puede ver métricas de uso de cada tenant (reservas del mes, usuarios activos, ingresos generados) sin acceder a datos de clientes del tenant.
- Todas las acciones sobre tenants quedan registradas en el audit trail.

**Dependencias:** RF-SEC-001 (autenticación), RF-AUD-001 (audit trail)

---

#### RF-PLT-002: Gestión de Planes de Suscripción

**Descripción:** El sistema define y aplica planes de suscripción con límites configurables. Los tenants no pueden superar los límites de su plan.

**Prioridad:** 🔴 Crítica

**Planes predefinidos:**

| Plan | Precio | Reservas/mes | Usuarios | Sedes | Fee transacción |
|---|---|---|---|---|---|
| Starter | $49/mes | 200 | 3 | 1 | 3.5% + $0.30 |
| Professional | $149/mes | 1,000 | 10 | 3 | 2.9% + $0.30 |
| Business | $399/mes | 5,000 | 25 | Sin límite | 2.4% + $0.30 |
| Enterprise | Custom | Sin límite | Sin límite | Sin límite | 1.9% + $0.30 |

**Criterios de Aceptación:**
- Cuando un tenant alcanza el 80% de su límite de reservas, recibe notificación de advertencia.
- Cuando un tenant alcanza el 100% del límite, el sistema bloquea nuevas reservas pero no afecta las existentes.
- El bloqueo por límite muestra al admin del tenant un mensaje claro con opción de upgrade.
- El admin de plataforma puede crear planes personalizados (Enterprise) con cualquier combinación de límites.
- El cambio de plan aplica inmediatamente; el costo se proratea en el ciclo de facturación vigente.
- Los límites se aplican en tiempo real sin requierir reinicio del sistema.
- El admin de plataforma puede asignar manualmente cualquier plan a cualquier tenant (incluyendo free).

**Dependencias:** RF-PLT-001

---

#### RF-PLT-003: Dashboard de Métricas de Plataforma

**Descripción:** Panel ejecutivo con KPIs de la plataforma actualizados near-real-time.

**Prioridad:** 🟡 Alta

**Métricas requeridas:**
- MRR (Monthly Recurring Revenue) total y por plan.
- Número de tenants activos, suspendidos, en trial.
- Churn rate mensual (tenants que cancelaron vs. activos el mes anterior).
- Reservas totales procesadas en el mes (conteo y valor).
- Ingresos por comisiones de transacción del mes.
- Distribución de tenants por plan.
- Top 10 tenants por volumen de reservas.
- Alertas de tenants con uso >80% de su límite.

**Criterios de Aceptación:**
- El dashboard carga en <3 segundos.
- Las métricas de MRR y tenants activos se actualizan con máximo 5 minutos de retraso.
- Los gráficos muestran tendencias de los últimos 12 meses.
- Se puede filtrar por período (últimos 7 días, 30 días, 3 meses, año).
- El admin puede exportar los datos del dashboard en CSV.

---

#### RF-PLT-004: Sistema de Facturación de Plataforma

**Descripción:** Generación y gestión de facturas automáticas a tenants por suscripción y comisiones de transacción.

**Prioridad:** 🟡 Alta

**Criterios de Aceptación:**
- El sistema genera automáticamente una factura el primer día de cada ciclo de facturación.
- La factura incluye: fee de suscripción del plan + comisiones de transacción del período anterior.
- Los prorrateos por cambio de plan se calculan correctamente (días utilizados / días del período × precio).
- El admin de plataforma puede ver historial completo de facturas por tenant.
- El sistema reintenta el cobro automáticamente si falla (día 1, día 3, día 7 antes de suspender).
- Tenant recibe email de factura generada y de cobro fallido.
- El admin puede emitir créditos manuales o ajustes a la factura antes del cobro.

---

#### RF-PLT-005: Monitoreo de Salud del Sistema

**Descripción:** Panel de estado de infraestructura con alertas automáticas para incidentes críticos.

**Prioridad:** 🟡 Alta

**Criterios de Aceptación:**
- El panel muestra en tiempo real: CPU y memoria de servidores, latencia de API (p50, p95, p99), tasa de errores (4xx, 5xx), throughput de peticiones/minuto.
- Alertas automáticas por email/Slack cuando: error rate >2%, latencia p95 >1000ms, cualquier componente crítico no disponible.
- El admin puede activar "maintenance mode" por tenant específico (muestra mensaje de mantenimiento sin afectar otros tenants) o globalmente.
- El sistema muestra el historial de incidentes de los últimos 30 días.

---

#### RF-PLT-006: Análisis Predictivo de Churn (v1.5)

**Descripción:** Identificación proactiva de tenants con alta probabilidad de cancelar.

**Prioridad:** 🟢 Media (post-MVP)

**Señales de riesgo a monitorear:**
- Caída de >30% en reservas mensuales vs. promedio de últimos 3 meses.
- Aumento de tickets de soporte en los últimos 14 días.
- No ha iniciado sesión en más de 14 días.
- Tiene >2 intentos de cobro fallidos.
- No ha completado el setup wizard.

**Criterios de Aceptación:**
- El sistema asigna un "risk score" (0–100) a cada tenant activo.
- Tenants con score >70 aparecen destacados en el dashboard con acciones sugeridas.
- El equipo de CS puede registrar acciones tomadas sobre tenants en riesgo.

---

### 7.2 Módulo: Configuración del Negocio (Tenant Admin)

#### RF-BIZ-001: Perfil del Negocio

**Descripción:** El Administrador de Negocio configura la identidad y parámetros base del negocio.

**Prioridad:** 🔴 Crítica

**Campos requeridos:**
- Nombre del negocio (requerido)
- Logo (imagen, formatos: PNG/JPG/WebP, max 2MB, recomendado 400×400px)
- Descripción corta (max 500 caracteres)
- Dirección (calle, ciudad, país, código postal)
- Teléfono de contacto
- Email de contacto
- Sitio web (opcional)
- Zona horaria (selector con todas las IANA timezones)
- Moneda base (ISO 4217: USD, PAB, COP, MXN, etc.)
- Slug personalizado para la URL de reservas (ej: `reasy.app/barberia-carlos`)

**Criterios de Aceptación:**
- El slug es único en la plataforma; si ya existe, el sistema sugiere alternativas.
- La zona horaria afecta todos los horarios y notificaciones del negocio.
- El logo se redimensiona y optimiza automáticamente al subirlo.
- Los cambios se aplican inmediatamente; el widget de reservas refleja el logo y nombre actualizados en <60 segundos (propagación de caché CDN).
- El negocio puede tener un dominio personalizado (ej: `reservas.barberiacarlos.com`) apuntando a su página de reservas (configuración DNS documentada).

---

#### RF-BIZ-002: Gestión de Sedes / Ubicaciones

**Descripción:** Un negocio puede operar en múltiples ubicaciones físicas, cada una con su propia configuración independiente de horarios, servicios disponibles y recursos.

**Prioridad:** 🔴 Crítica

**Criterios de Aceptación:**
- El admin puede crear, editar y desactivar sedes.
- Cada sede tiene: nombre, dirección completa, teléfono, email, zona horaria propia (puede diferir de la del negocio), horario de operación base.
- Una sede desactivada no aparece en el widget de reservas pero conserva su historial.
- Los recursos (personal, salas) pueden asignarse a una sede específica o reasignarse entre sedes.
- El widget de reservas permite al cliente filtrar por sede si el negocio tiene más de una.
- El plan Starter solo permite 1 sede activa; planes superiores según los límites del plan.

---

#### RF-BIZ-003: Catálogo de Servicios

**Descripción:** Gestión completa del catálogo de servicios ofrecidos, con toda la configuración de precios, tiempos, capacidades y políticas.

**Prioridad:** 🔴 Crítica

**Atributos de un Servicio:**

| Campo | Tipo | Descripción |
|---|---|---|
| Nombre | String (max 100) | Requerido |
| Descripción | Text (max 1000) | Opcional, visible al cliente |
| Categoría | FK → ServiceCategory | Agrupación para el widget |
| Duración | Integer (minutos) | Requerido, múltiplo de 5 |
| Buffer antes | Integer (minutos) | Tiempo de preparación, default 0 |
| Buffer después | Integer (minutos) | Tiempo de limpieza/descanso, default 0 |
| Capacidad | Integer | 1 = individual, >1 = grupal |
| Precio base | Decimal(10,2) | Requerido |
| Tipo de depósito | Enum: none/percentage/fixed | Default: none |
| Valor de depósito | Decimal(10,2) | Requerido si tipo ≠ none |
| Política de cancelación | FK → CancellationPolicy | Reglas de reembolso |
| Estado | Enum: active/inactive/archived | Default: active |
| Visible en widget | Boolean | Default: true |
| Orden en widget | Integer | Para sorting manual |

**Criterios de Aceptación:**
- El admin puede crear servicios en <2 minutos usando el formulario guiado.
- Un servicio archivado no puede recibir nuevas reservas pero mantiene el historial.
- El sistema calcula y muestra el tiempo efectivo total del slot = duración + buffer antes + buffer después.
- Servicios grupales (capacidad >1) muestran cuántos lugares quedan disponibles en el widget.
- El cambio de precio no afecta reservas confirmadas existentes.
- El admin puede duplicar un servicio para crear variaciones rápidamente.
- El sistema alerta si se intenta inactivar un servicio con reservas futuras confirmadas.

---

#### RF-BIZ-004: Políticas de Cancelación

**Descripción:** Configuración granular de las reglas de reembolso según cuándo se cancela una reserva. Cada servicio puede tener su propia política.

**Prioridad:** 🔴 Crítica

**Estructura de una Política:**

```
CancellationPolicy "Estándar":
  Regla 1: Si cancela con >48h de anticipación → 100% de reembolso del depósito
  Regla 2: Si cancela con 24–48h de anticipación → 50% de reembolso
  Regla 3: Si cancela con <24h de anticipación → 0% de reembolso
  Regla 4: No-show → 0% de reembolso
```

**Criterios de Aceptación:**
- El admin puede crear múltiples políticas y asignar una por defecto y políticas específicas por servicio.
- Las reglas se evalúan en orden; la primera regla que aplica define el reembolso.
- El sistema calcula y muestra el monto de reembolso exacto en el momento de la cancelación.
- El admin puede hacer un "override manual" en cualquier cancelación, autorizando un reembolso diferente al calculado automáticamente (queda registrado en audit trail).
- Las políticas se muestran claramente al cliente antes de confirmar la reserva y en el email de confirmación.

---

#### RF-BIZ-005: Gestión de Recursos

**Descripción:** Los recursos son las entidades que pueden ser reservadas: personal (barberos, estilistas, terapeutas), salas (sala de masajes, sala VIP) o equipamiento (cámara, estudio).

**Prioridad:** 🔴 Crítica

**Tipos de recurso:**
- **Staff**: Personal del negocio. Puede tener un User asociado para acceso al panel.
- **Room**: Sala física. No tiene usuario.
- **Equipment**: Equipamiento. No tiene usuario.

**Atributos:**

| Campo | Tipo | Descripción |
|---|---|---|
| Nombre | String | Requerido (ej: "Valentina", "Sala VIP") |
| Tipo | Enum: staff/room/equipment | Requerido |
| Sede | FK → Location | Requerido |
| Servicios habilitados | M2M → Services | Qué puede atender |
| Capacidad | Integer | Default 1 |
| Habilidades/tags | JSON Array | Para filtrado en widget |
| Tarifa especial | Decimal | Sobreprecio si aplica |
| Foto/avatar | Image | Visible en widget para staff |
| Usuario del sistema | FK → User (nullable) | Para staff con acceso |
| Activo | Boolean | Default true |

**Criterios de Aceptación:**
- Un recurso solo aparece disponible para servicios que tiene explícitamente habilitados.
- Si un recurso se desactiva, sus reservas futuras no se cancelan automáticamente; el sistema alerta al admin para que las reasigne.
- El admin puede ver la agenda de cualquier recurso en vista calendario.
- Un recurso puede ser reasignado a otra sede sin perder historial.

---

#### RF-BIZ-006: Motor de Horarios y Disponibilidad

**Descripción:** El sistema calcula en tiempo real los slots disponibles para reserva, considerando múltiples capas de reglas.

**Prioridad:** 🔴 Crítica — Este es el corazón del producto. Una falla aquí es un falla catastrófica.

**Capas del cálculo de disponibilidad (en orden de evaluación):**

```
1. Horario base del recurso
   └── Ej: Lunes a Viernes 9:00–18:00

2. Excepciones de horario
   └── Ej: El 25 de diciembre NO trabaja
   └── Ej: El 15 de octubre trabaja solo 9:00–12:00

3. Bloqueos manuales
   └── Ej: "Reunión interna" de 14:00 a 15:00

4. Reservas activas confirmadas
   └── Ocupa el slot: inicio hasta fin + buffer after

5. Buffer antes del servicio
   └── El slot empieza buffer_before antes del inicio real

6. Slots resultantes
   └── Granularidad configurable (cada 15, 30, 60 min)
   └── Solo slots donde cabe completamente el servicio dentro del horario del recurso
```

**Protección contra Race Conditions (CRÍTICO):**

El sistema usa `SELECT FOR UPDATE SKIP LOCKED` en PostgreSQL para garantizar que dos clientes que ven el mismo slot disponible al mismo tiempo no puedan ambos confirmarlo. El flujo es:

```
1. Cliente A y Cliente B ven el mismo slot libre
2. Cliente A envía confirmación primero (llega al DB primero)
3. DB ejecuta SELECT FOR UPDATE → adquiere lock del slot
4. Cliente B envía confirmación
5. DB intenta SELECT FOR UPDATE → slot está lockeado → SKIP
6. Cliente B recibe error "Slot no disponible" y se le presentan alternativas
7. Cliente A completa su transacción → lock liberado
8. El slot ya no está disponible para nadie más
```

**Cache de Disponibilidad:**
- TTL de 5 minutos en Redis para resultados de disponibilidad.
- Invalidación inmediata al crear, confirmar, cancelar o modificar cualquier booking para el mismo recurso/fecha.
- Warmup de caché para los próximos 7 días al iniciar el servidor.

**Criterios de Aceptación:**
- La consulta de disponibilidad responde en <500ms (p95) incluyendo lectura de caché.
- Bajo carga de 100 usuarios simultáneos consultando disponibilidad del mismo recurso, el sistema NO genera doble-bookings. (Testeable con test de concurrencia).
- Los slots se recalculan correctamente cuando se agrega un buffer a un servicio existente.
- El calendario muestra slots en la zona horaria del cliente (detectada automáticamente, ajustable manualmente).

---

#### RF-BIZ-007: Panel de Gestión de Reservas (Admin)

**Descripción:** Vista completa de todas las reservas del negocio con capacidades de búsqueda, filtrado y acciones administrativas.

**Prioridad:** 🔴 Crítica

**Vistas disponibles:**
- **Vista calendario**: Semana y día, con reservas como bloques de colores por recurso o por estado.
- **Vista lista**: Tabla paginada con todas las reservas, ordenable y filtrable.

**Filtros disponibles:**
- Estado (draft, confirmed, cancelled, completed, no_show)
- Fecha (rango)
- Recurso
- Servicio
- Sede
- Cliente (búsqueda por nombre, email, teléfono)
- Fuente (web, admin, API)

**Acciones desde el panel:**
- Ver detalle completo de una reserva.
- Editar notas internas.
- Cambiar estado (confirm, cancel, complete, mark no-show).
- Reprogramar (selecciona nuevo slot; aplica validaciones de disponibilidad; notifica al cliente).
- Reasignar a otro recurso (si está disponible).
- Procesar reembolso manual con monto personalizado.

**Criterios de Aceptación:**
- La vista calendario soporta drag & drop para mover reservas (con validación de disponibilidad del nuevo slot).
- La búsqueda de clientes devuelve resultados en <300ms.
- El admin puede exportar la lista de reservas filtrada en CSV y PDF.
- Las acciones sobre reservas requieren confirmación cuando son destructivas (cancelación).
- Toda acción queda registrada en el historial de la reserva.

---

#### RF-BIZ-008: Creación Manual de Reservas (Admin)

**Descripción:** El admin o recepcionista puede crear reservas directamente desde el panel administrativo, saltando el flujo del widget.

**Prioridad:** 🔴 Crítica

**Flujo:**
1. Seleccionar servicio.
2. Seleccionar recurso (o "cualquier disponible").
3. Seleccionar fecha y slot (del calendario de disponibilidad real).
4. Buscar cliente existente o crear uno nuevo en el momento.
5. Configurar opciones: cobrar depósito ahora / omitir depósito / marcar como pagado en efectivo.
6. Confirmar y notificar al cliente (opcional).

**Criterios de Aceptación:**
- El admin puede crear una reserva en <90 segundos una vez que tiene clara la información.
- Si el admin selecciona un slot ya ocupado, el sistema lo advierte pero permite continuar si tiene permiso de "override" (rol Admin o Gerente).
- El override queda registrado en audit trail con justificación opcional.
- Las reservas creadas por admin pueden tener `require_deposit = false` independientemente de la política del servicio.
- La notificación al cliente es opcional al crear manualmente.

---

#### RF-BIZ-009: CRM de Clientes

**Descripción:** Base de datos de clientes del negocio con historial completo, notas y segmentación.

**Prioridad:** 🟡 Alta

**Perfil de Cliente incluye:**
- Datos básicos: nombre, email, teléfono, fecha de nacimiento (opcional).
- Tipo: registered / guest.
- Estado: active, inactive, banned.
- Tags y notas internas (visibles solo para el negocio, no para el cliente).
- Estadísticas: total de reservas, reservas completadas, no-shows, gasto total, valor promedio de reserva.
- Historial completo de reservas con todos los detalles.
- Preferencias: recurso favorito, servicio más reservado.
- Registro de consentimiento de marketing.

**Criterios de Aceptación:**
- El admin puede buscar clientes por nombre, email o teléfono con resultados en <300ms.
- El admin puede añadir, editar y eliminar notas y tags en cualquier perfil.
- Un cliente con estado "banned" no puede completar nuevas reservas en este negocio.
- El admin puede exportar la lista completa de clientes (con consentimiento de marketing = true solamente) en CSV, respetando GDPR.
- La fusión de perfiles duplicados (mismo email/teléfono) es una acción manual disponible para el admin.

---

#### RF-BIZ-010: Dashboard Analítico del Negocio

**Descripción:** Panel con métricas de rendimiento del negocio para toma de decisiones.

**Prioridad:** 🟡 Alta

**KPIs del Dashboard:**

**Bloque Ingresos:**
- Ingresos totales del período (completados).
- Ingresos por cobrar (confirmed, futuros).
- Depósitos retenidos (no-shows + cancelaciones tardías).
- Ingresos promedio por reserva.
- Ingresos por servicio (top 5).
- Ingresos por recurso/profesional.

**Bloque Ocupación:**
- Tasa de ocupación por recurso (horas reservadas / horas disponibles × 100).
- Horas pico de demanda (heatmap por hora/día de la semana).
- Servicios más populares (conteo de reservas).

**Bloque Calidad:**
- Tasa de no-shows (%).
- Tasa de cancelaciones (%).
- Tasa de conversión del widget (visitas → reservas completadas).
- Clientes nuevos vs. recurrentes.

**Bloque Tendencias:**
- Comparativa con período anterior (semana, mes).
- Gráfico de tendencia de reservas (últimas 12 semanas).

**Criterios de Aceptación:**
- El dashboard carga en <3 segundos.
- Las métricas son correctas con un máximo de 15 minutos de retraso (caché de analytics con TTL 15 min).
- El admin puede seleccionar el rango de fechas libremente.
- Se puede filtrar por sede y por recurso.
- El admin puede exportar cualquier sección como imagen (PNG) o datos como CSV.

---

#### RF-BIZ-011: Gestión de Notificaciones del Negocio

**Descripción:** Configuración de plantillas de comunicación con clientes: qué se envía, cuándo y cómo.

**Prioridad:** 🟡 Alta

**Eventos de notificación base:**

| Evento | Canal | Timing | Configurable |
|---|---|---|---|
| Reserva confirmada | Email | Inmediato | No (siempre) |
| Recordatorio de cita | Email | 24h antes | Sí (timing y texto) |
| Recordatorio de cita | Email | 2h antes | Sí (activable) |
| Cancelación por cliente | Email | Inmediato | No |
| Reembolso procesado | Email | Inmediato | No |
| No-show registrado | Email | Inmediato | Sí (activable) |
| Reserva reprogramada | Email | Inmediato | No |
| En lista de espera | Email | Inmediato | Sí |
| Slot disponible (waitlist) | Email | Inmediato | Sí |

**Editor de plantillas:**
- Editor visual (WYSIWYG) y modo código (HTML).
- Variables disponibles: `{{customer_name}}`, `{{service_name}}`, `{{booking_date}}`, `{{booking_time}}`, `{{resource_name}}`, `{{location_address}}`, `{{cancel_link}}`, `{{reschedule_link}}`, `{{business_name}}`, `{{business_logo}}`.
- Preview en tiempo real del email renderizado.
- Posibilidad de restaurar plantilla por defecto.

**Criterios de Aceptación:**
- Los emails se envían en <2 minutos desde el evento disparador.
- Las plantillas soportan el logo y colores del negocio (branding personalizado).
- Los enlaces de cancelación/gestión del email son únicos, firmados y expiran en 7 días.
- El admin puede enviar un email de prueba a su propio email antes de activar una plantilla.
- Si el envío de un email falla, el sistema reintenta 3 veces con backoff exponencial (5min, 15min, 45min). Después del tercer intento, lo marca como fallido y lo registra.

---

#### RF-BIZ-012: Gestión de Waitlist

**Descripción:** Cuando un cliente intenta reservar un slot ocupado, puede unirse a la lista de espera. Si el slot se libera (cancelación), el sistema notifica automáticamente a los clientes en la lista.

**Prioridad:** 🟡 Alta

**Criterios de Aceptación:**
- Cuando un slot está lleno, el widget muestra la opción "Unirse a la lista de espera para este horario".
- El cliente en waitlist recibe confirmación de que está en la lista, con su posición.
- Cuando se cancela una reserva, el sistema notifica al primer cliente de la waitlist con un enlace que reserva ese slot directamente (válido por 30 minutos).
- Si el cliente no actúa en 30 minutos, el sistema notifica al segundo en la lista, y así sucesivamente.
- El admin puede ver y gestionar la waitlist manualmente: ver posiciones, eliminar entradas, reordenar prioridades.
- Clientes marcados como VIP en el CRM tienen prioridad automática en la waitlist.
- Si el cliente en waitlist ya tiene otra reserva del mismo servicio ese día, el sistema lo notifica igualmente (el cliente decide).

---

#### RF-BIZ-013: Integración de Pagos del Negocio

**Descripción:** Configuración de la pasarela de pago del negocio para cobrar depósitos a sus clientes.

**Prioridad:** 🔴 Crítica

**Modelo de integración:** Stripe Connect (Plataforma → Negocio).
- Reasy es la plataforma Stripe Connect.
- Cada tenant conecta su propia cuenta Stripe (Standard o Express).
- Los cobros van directamente a la cuenta del tenant.
- Reasy cobra su fee de transacción mediante `application_fee_amount`.

**Criterios de Aceptación:**
- El tenant puede conectar su cuenta Stripe en <5 minutos mediante OAuth de Stripe Connect.
- El admin puede desconectar la cuenta Stripe; esto desactiva el cobro de depósitos pero no cancela las reservas existentes.
- El dashboard de pagos muestra: cobros recibidos, reembolsos emitidos, fees de Reasy retenidos.
- El tenant puede ver la reconciliación de cada reserva con su pago correspondiente.
- La desconexión de Stripe queda registrada en audit trail.

---

### 7.3 Módulo: Portal de Staff

#### RF-STF-001: Calendario Personal del Empleado

**Descripción:** Vista personalizada de la agenda del empleado con sus citas asignadas.

**Prioridad:** 🟡 Alta

**Criterios de Aceptación:**
- El empleado solo ve las citas asignadas a su recurso (no las de otros).
- Vista disponible: día, semana.
- Cada cita muestra: hora, servicio, nombre del cliente, duración, estado.
- El calendario se actualiza automáticamente sin refrescar la página cuando se modifica una cita (WebSocket o SSE).
- El empleado puede ver su agenda en dispositivos móviles con buena UX (responsive).

---

#### RF-STF-002: Gestión de Estado de Citas

**Descripción:** El empleado puede actualizar el estado de sus citas y agregar notas de servicio.

**Prioridad:** 🟡 Alta

**Acciones disponibles para el empleado:**
- Marcar cliente como "Llegó" (check-in).
- Marcar cita como "Completada".
- Marcar como "No se presentó" (no-show).
- Añadir nota interna sobre el servicio realizado.
- Ver historial del cliente (reservas anteriores con este negocio, notas de citas pasadas).

**Criterios de Aceptación:**
- El empleado puede marcar una cita como completada en <10 segundos desde su celular.
- Al marcar como "No-show", el sistema dispara el proceso de retención del depósito automáticamente.
- Las notas del empleado son visibles para el admin pero NO para el cliente.
- El empleado NO puede cancelar citas por defecto; necesita permiso explícito del admin (configurable por rol).

---

#### RF-STF-003: Gestión de Disponibilidad Personal

**Descripción:** El empleado puede solicitar bloqueos de tiempo o días libres.

**Prioridad:** 🟢 Media

**Criterios de Aceptación:**
- El empleado puede solicitar bloqueo de tiempo con una justificación.
- El bloqueo queda en estado "Pendiente de aprobación" hasta que el admin lo apruebe o rechace.
- Una vez aprobado, el slot no aparece disponible en el widget.
- El admin puede crear bloqueos directamente sin aprobación.
- El sistema previene que un bloqueo se apruebe si ya hay reservas en ese período; requiere resolución manual.

---

### 7.4 Módulo: Flujo de Reserva del Cliente

#### RF-CLT-001: Flujo de Reserva Guest (Sin Cuenta)

**Descripción:** Un cliente puede completar una reserva sin crear una cuenta ni una contraseña, solo verificando su email o teléfono mediante OTP.

**Prioridad:** 🔴 Crítica

**Pasos del flujo:**

**Paso 1 — Selección de Servicio:**
- El widget muestra los servicios activos del negocio, agrupados por categoría.
- Cada servicio muestra: nombre, descripción, duración, precio, si requiere depósito.
- El cliente puede filtrar por categoría.

**Paso 2 — Selección de Recurso (opcional):**
- Si el servicio tiene múltiples recursos disponibles, el cliente puede elegir uno específico o "cualquier disponible".
- Se muestra foto y nombre del profesional si es tipo staff.

**Paso 3 — Selección de Fecha y Hora:**
- Calendario interactivo que muestra los próximos 60 días.
- Días sin disponibilidad se muestran deshabilitados.
- Al seleccionar un día, los slots disponibles aparecen como botones en formato de tiempo.
- La zona horaria del cliente se detecta automáticamente (puede ajustarse manualmente).

**Paso 4 — Datos del Cliente:**
- Formulario mínimo: nombre (requerido), email ó teléfono (al menos uno, requerido).
- Apellido: opcional.
- Notas para el negocio: opcional, max 500 caracteres.
- Validación en tiempo real de formato de email y teléfono.

**Paso 5 — Verificación OTP:**
- Se envía código de 6 dígitos al email o teléfono ingresado.
- El código expira en 10 minutos.
- Máximo 3 intentos antes de bloquear por 15 minutos (anti-spam).
- El cliente puede solicitar reenvío después de 60 segundos.

**Paso 6 — Pago de Depósito (si aplica):**
- Si el servicio tiene depósito configurado, aparece el formulario de Stripe (Stripe Elements embebido).
- Se muestra claramente: "Se cobrará $X como depósito. El restante ($Y) se paga en el local."
- El booking se crea en estado `pending_payment`; tiene 15 minutos para completar el pago.
- Soporte para: tarjeta de crédito/débito. (Futuro: OXXO, PSE, otros métodos locales).

**Paso 7 — Confirmación:**
- Pantalla de éxito con resumen completo.
- Email de confirmación enviado en <2 minutos.
- Enlace único de gestión enviado al email/SMS del cliente.

**Criterios de Aceptación:**
- El flujo completo (sin depósito) se completa en <3 minutos en condiciones normales.
- El widget es completamente funcional en móviles (responsive, sin zoom, sin scroll horizontal).
- El widget carga en <1 segundo (LCP) cuando se embebe en sitios externos.
- Si el slot se toma mientras el cliente está en el paso de OTP, el sistema informa claramente y ofrece los próximos slots disponibles sin perder los datos del formulario.
- El sistema previene reservas duplicadas del mismo cliente para el mismo slot (detectado por email/teléfono + resource_id + start_time).

---

#### RF-CLT-002: Flujo de Reserva Cliente Registrado

**Descripción:** Para clientes con cuenta, el flujo de reserva es más rápido con datos precompletados.

**Prioridad:** 🔴 Crítica

**Diferencias vs. flujo Guest:**
- El cliente inicia sesión antes de reservar (o durante el proceso).
- Los datos personales están precompletados.
- No requiere OTP (ya está autenticado).
- Si tiene métodos de pago guardados, puede seleccionar uno para el depósito (1-click payment).
- El sistema recuerda y sugiere el recurso favorito del cliente.
- El historial de reservas previas es visible durante el proceso.

**Criterios de Aceptación:**
- Un cliente registrado puede completar una nueva reserva (sin depósito) en <90 segundos.
- El cliente puede guardar un método de pago para futuras reservas con consentimiento explícito.
- El sistema detecta si el cliente está intentando reservar un slot que ya tiene reservado.

---

#### RF-CLT-003: Auto-gestión de Reservas (Cliente)

**Descripción:** Los clientes pueden gestionar sus reservas existentes sin necesidad de contactar al negocio.

**Prioridad:** 🔴 Crítica

**Acceso:** Mediante enlace único en el email de confirmación (clientes guest) o desde su cuenta (clientes registrados).

**Acciones disponibles:**

**Cancelar reserva:**
- El sistema muestra la política de cancelación aplicable.
- Calcula y muestra exactamente cuánto se reembolsará.
- El cliente confirma conociendo el impacto financiero.
- El reembolso se procesa automáticamente en <5 minutos.
- Restricción: No se puede cancelar si falta <X horas (configurable por el negocio; default: no restricción de tiempo para cancelar, pero el reembolso varía).

**Reprogramar reserva:**
- El cliente selecciona una nueva fecha/hora del mismo calendario de disponibilidad.
- El slot original queda liberado.
- El nuevo slot queda confirmado.
- El cliente recibe confirmación del cambio.
- Restricción: El negocio puede deshabilitar la reprogramación dentro de X horas antes de la cita.

**Criterios de Aceptación:**
- El enlace de gestión en el email es válido por 7 días desde la confirmación.
- La cancelación con reembolso dispara el proceso de devolución en Stripe y el cliente recibe el dinero en 5–10 días hábiles (según Stripe).
- El cliente puede reprogramar máximo N veces (configurable por negocio; default: sin límite).
- Las acciones disponibles se adaptan según el estado actual del booking (no se puede cancelar un booking ya cancelado).

---

#### RF-CLT-004: Registro y Perfil de Cliente

**Descripción:** Los clientes pueden crear una cuenta para tener historial persistente, métodos de pago guardados y experiencia mejorada.

**Prioridad:** 🟡 Alta

**Criterios de Aceptación:**
- El registro puede iniciarse durante el flujo de reserva guest (paso post-confirmación) o desde el portal del cliente.
- Al registrarse después de una reserva guest, el historial de reservas guest se asocia automáticamente a la nueva cuenta (matching por email/teléfono).
- El cliente puede gestionar sus métodos de pago (agregar, eliminar).
- El cliente puede ver su historial completo de reservas en todos los negocios donde está registrado dentro de Reasy.
- El cliente puede solicitar la eliminación de su cuenta (GDPR right to erasure). El sistema anonimiza los datos en 30 días (conserva registros de pago por obligación fiscal).
- El cliente puede descargar todos sus datos en formato JSON (GDPR right to portability).

---

### 7.5 Módulo: API Pública (v1.5)

#### RF-API-001: REST API Pública

**Descripción:** API REST documentada y versionada para integraciones de terceros.

**Prioridad:** 🟢 Media (post-MVP)

**Endpoints principales:**

```
GET    /api/v1/availability
POST   /api/v1/bookings
GET    /api/v1/bookings/{id}
PATCH  /api/v1/bookings/{id}
DELETE /api/v1/bookings/{id}
GET    /api/v1/services
GET    /api/v1/resources
GET    /api/v1/customers
POST   /api/v1/customers
```

**Criterios de Aceptación:**
- Toda la API requiere autenticación por API Key (header `X-Reasy-Key`).
- Rate limiting: 1000 requests/hora por API Key (ajustable por plan).
- Todos los endpoints siguen el mismo formato de respuesta: `{success, data, error, metadata}`.
- Documentación OpenAPI 3.0 generada automáticamente, disponible en `/api/v1/docs`.
- El entorno sandbox está disponible en `sandbox.reasy.app` con datos de prueba reseteables.
- Los errores devuelven códigos HTTP semánticamente correctos y mensajes descriptivos en español.

---

#### RF-API-002: Sistema de Webhooks

**Descripción:** El negocio puede configurar endpoints que recibirán notificaciones cuando ocurran eventos en Reasy.

**Prioridad:** 🟢 Media (post-MVP)

**Eventos disponibles:**
- `booking.created`, `booking.confirmed`, `booking.cancelled`, `booking.completed`, `booking.no_show`, `booking.rescheduled`
- `payment.succeeded`, `payment.failed`, `payment.refunded`
- `customer.created`, `customer.updated`
- `waitlist.notified`

**Criterios de Aceptación:**
- El admin puede configurar hasta 5 URLs de webhook por negocio.
- Cada webhook se firma con HMAC-SHA256 usando un secret único por endpoint.
- El receptor tiene 5 segundos para responder 2xx, de lo contrario se considera fallido.
- El sistema reintenta webhooks fallidos con backoff exponencial: 5min, 30min, 2h, 12h, 24h.
- El admin puede ver el log de entregas de cada webhook (éxitos, fallos, payload).

---

### 7.6 Módulo: Seguridad y Compliance

#### RF-SEC-001: Autenticación y Gestión de Sesiones

**Descripción:** Sistema de autenticación seguro para todos los tipos de usuario del sistema.

**Prioridad:** 🔴 Crítica

**Criterios de Aceptación:**
- Autenticación por email + password con hash bcrypt (cost factor ≥12).
- Política de contraseña: mínimo 8 caracteres, al menos 1 mayúscula, 1 número.
- JWT de acceso: expira en 30 minutos. Refresh token: expira en 30 días.
- MFA opcional (TOTP, ej. Google Authenticator) disponible para roles Admin y Gerente.
- Bloqueo de cuenta después de 5 intentos fallidos consecutivos durante 15 minutos.
- Las sesiones se invalidan globalmente al cambiar la contraseña.
- Un usuario puede ver sus sesiones activas (dispositivo, IP, fecha) y cerrarlas remotamente.
- Los tokens JWT incluyen: `tenant_id`, `user_id`, `role`, `permissions[]`, `session_id`.

---

#### RF-SEC-002: Control de Acceso Basado en Roles (RBAC)

**Descripción:** Sistema granular de permisos que define qué puede hacer cada tipo de usuario.

**Prioridad:** 🔴 Crítica

**Roles del sistema:**

| Rol | Alcance | Descripción |
|---|---|---|
| `platform_admin` | Global | Acceso total a la plataforma |
| `support_agent` | Cross-tenant (limitado) | Soporte técnico con acceso auditado |
| `business_owner` | Tenant | Acceso total dentro de su tenant |
| `manager` | Tenant / Sede | Acceso operativo con algunas restricciones |
| `receptionist` | Tenant / Sede | Gestión de reservas, sin configuración |
| `professional` | Propio recurso | Solo su agenda y sus citas |
| `customer` | Propio perfil | Solo sus reservas y datos |

**Criterios de Aceptación:**
- El `business_owner` puede crear roles personalizados para su negocio.
- Los permisos se verifican en el backend en cada petición (no solo en el frontend).
- Un `professional` NO puede ver datos de clientes más allá del nombre y servicio de sus citas.
- Un `receptionist` puede crear y cancelar reservas pero no puede ver datos financieros.
- El cambio de rol de un usuario aplica en la próxima petición (sin esperar expiración del token; se verifica contra DB).

---

#### RF-SEC-003: Aislamiento Multi-Tenant (Row-Level Security)

**Descripción:** Garantía técnica de que los datos de un tenant nunca son visibles ni accesibles desde otro tenant.

**Prioridad:** 🔴 Crítica — Launch Blocker

**Implementación:**
- Todas las tablas con datos de tenant tienen columna `tenant_id`.
- PostgreSQL Row-Level Security (RLS) habilitado en todas estas tablas.
- Las políticas RLS verifican que `tenant_id = current_setting('app.current_tenant_id')`.
- El `current_tenant_id` se establece al inicio de cada transacción desde el middleware de Laravel.

**Criterios de Aceptación:**
- Un test de seguridad automatizado verifica que un request autenticado como Tenant A no puede acceder a datos del Tenant B bajo ninguna circunstancia.
- Las políticas RLS están habilitadas en producción para las tablas: `bookings`, `customers`, `payments`, `notifications`, `resources`, `services`, `locations`, `users`, `audit_logs`.
- Si `tenant_id` no está establecido en la sesión de DB, cualquier query a tablas protegidas retorna 0 resultados (fail-safe, no error 500).

---

#### RF-AUD-001: Audit Trail

**Descripción:** Registro inmutable de todas las acciones significativas sobre datos críticos.

**Prioridad:** 🟡 Alta — Launch Blocker para segmentos regulados

**Eventos auditados:**
- Creación, modificación y cancelación de reservas.
- Todos los pagos y reembolsos.
- Cambios de configuración del negocio.
- Creación, modificación y eliminación de usuarios.
- Login, logout, intentos fallidos de autenticación.
- Cambios de rol.
- Accesos de support agents a datos de tenant.
- Exportaciones de datos.
- Eliminación de datos (GDPR requests).

**Criterios de Aceptación:**
- Cada registro de audit incluye: timestamp (con timezone), actor (user_id, role), acción, entidad afectada (tipo + ID), valores anteriores (JSON), valores nuevos (JSON), IP address, user agent, session_id.
- Los registros de audit NO son editables ni eliminables por ningún usuario del sistema (incluyendo platform_admin). Solo archivables después de 7 años.
- El auditor puede buscar y filtrar registros por: actor, entidad, tipo de acción, rango de fechas.
- Los registros pueden exportarse en CSV y JSON.

---

## 8. Requisitos No Funcionales

### 8.1 Performance

| Métrica | Condición | Target MVP | Target Año 1 |
|---|---|---|---|
| Latencia API (p95) | Carga normal | <1000ms | <500ms |
| Latencia API (p99) | Carga normal | <2000ms | <1000ms |
| Latencia consulta disponibilidad (p95) | Cache hit | <100ms | <50ms |
| Latencia consulta disponibilidad (p95) | Cache miss | <800ms | <500ms |
| Tiempo creación de booking (p95) | Normal | <2000ms | <1500ms |
| LCP del widget de reserva | Red móvil 4G | <2500ms | <1500ms |
| Carga del dashboard de negocio | Normal | <3000ms | <2000ms |
| Throughput | Pico | 200 reservas/min | 1000 reservas/min |

### 8.2 Disponibilidad y Confiabilidad

| Métrica | Target MVP | Target Año 1 |
|---|---|---|
| Uptime mensual | 99.5% | 99.9% |
| Downtime planificado/mes | <8 horas | <4 horas |
| Failover automático en caso de falla de nodo | <10 minutos | <5 minutos |
| RPO (Recovery Point Objective) | 1 hora | 15 minutos |
| RTO (Recovery Time Objective) | 8 horas | 4 horas |
| Retención de backups | 30 días | 90 días |
| Frecuencia de backups | Cada 6 horas | Continuo (WAL shipping) |

### 8.3 Escalabilidad

- La arquitectura debe soportar escalamiento horizontal de la capa de aplicación sin cambios de código.
- El sistema debe auto-escalar basado en: CPU >70% por 5 minutos, latencia p95 >800ms, queue length >500.
- La adición de nuevos tenants no debe requerir particionamiento manual ni downtime.
- La base de datos debe soportar hasta 10 millones de bookings por tabla sin degradación de performance (indexado correcto).

### 8.4 Seguridad

- Todas las comunicaciones usan TLS 1.2+ (forzado; sin fallback a HTTP).
- Sin almacenamiento de datos de tarjetas de crédito (PANs). Solo tokens de Stripe.
- Penetration testing externo antes del go-live en producción.
- Dependencias de software escaneadas automáticamente por CVEs (GitHub Dependabot o equivalente).
- Secrets manejados por servicio externo (AWS Secrets Manager / DigitalOcean Secrets); sin secrets en código.
- Política de retención de logs: audit logs 7 años, application logs 90 días, access logs 1 año.

### 8.5 Usabilidad

- El flujo de reserva guest debe completarse con un máximo de 7 interacciones/clics.
- El tiempo de aprendizaje para un nuevo admin de negocio (sin entrenamiento) debe ser <30 minutos para las funciones core.
- Todos los formularios incluyen mensajes de error descriptivos en español, con sugerencias de corrección.
- El sistema soporta keyboard navigation completa (accesibilidad WCAG 2.1 AA).
- Las acciones destructivas (cancelar, eliminar) siempre requieren confirmación explícita.

### 8.6 Mantenibilidad

- Cobertura de tests unitarios: ≥80% en módulos Booking y Payment.
- Cobertura de tests de integración: ≥70% en flujos críticos (booking, pago, reembolso).
- Toda la API debe estar documentada con ejemplos de request/response.
- Las migraciones de base de datos deben ser reversibles (down migrations).
- El tiempo de despliegue de una nueva versión debe ser <15 minutos sin downtime.
- Cambios en un módulo no deben requerir modificaciones en otros módulos (boundaries DDD).

---

## 9. Modelo de Datos Conceptual

### 9.1 Entidades Principales y Relaciones

```
TENANT (1) ──────────────────────────── (N) BUSINESS
                                              │
                              ┌───────────────┤
                              │               │
                             (N)             (N)
                          LOCATION         SERVICE
                              │               │
                             (N)         (N) ServicePricing
                          RESOURCE        (N) CancellationPolicy
                              │
                         (N) SCHEDULE
                         (N) ScheduleException
                         (N) ScheduleBlock

TENANT (1) ────────────────────────── (N) CUSTOMER
                                            │
                                           (N) BOOKING ──── (1) SERVICE
                                            │            └── (1) RESOURCE
                                            │            └── (1) LOCATION
                                            │
                                           (N) PAYMENT ──── (N) REFUND

TENANT (1) ────────────────────── (N) USER (empleado)
                                        │
                                       (1) RESOURCE (opcional)

BOOKING (1) ──────────────────── (N) NOTIFICATION
BOOKING (1) ──────────────────── (N) AuditLog

TENANT (1) ──────────────────── (N) NotificationTemplate
TENANT (1) ──────────────────── (N) WaitlistEntry
```

### 9.2 Tabla de Entidades Críticas

| Entidad | Propósito | Claves de aislamiento |
|---|---|---|
| `tenants` | Registro del negocio cliente | `id` — raíz del árbol de tenant |
| `businesses` | Perfil del negocio | `tenant_id` |
| `locations` | Sedes físicas | `business_id` → `tenant_id` |
| `resources` | Personal / salas / equipos | `location_id` → `tenant_id` |
| `services` | Catálogo de servicios | `business_id` → `tenant_id` |
| `schedules` | Horarios base de recursos | `resource_id` → `tenant_id` |
| `customers` | Clientes del negocio | `tenant_id` — NO mezclar con `users` |
| `users` | Empleados con acceso al sistema | `tenant_id` — NO mezclar con `customers` |
| `bookings` | Reservas (núcleo del sistema) | `tenant_id`, `business_id`, `location_id` |
| `payments` | Cobros y depósitos | `tenant_id`, `booking_id` |
| `refunds` | Reembolsos | `payment_id` → `tenant_id` |
| `notification_templates` | Plantillas personalizadas | `tenant_id` |
| `notifications` | Log de envíos | `tenant_id`, `booking_id`, `customer_id` |
| `audit_logs` | Registro inmutable de acciones | `tenant_id` (nullable para acciones de plataforma) |
| `waitlist_entries` | Lista de espera | `tenant_id`, `service_id` |

---

## 10. Integraciones Externas

### 10.1 Stripe (Pagos)

**Propósito:** Procesamiento de depósitos, pagos y reembolsos.

**Modelo:** Stripe Connect Platform.
- Reasy es la cuenta de plataforma (Platform Account).
- Cada tenant conecta su cuenta (Connected Account: Standard o Express).
- Los cobros fluyen a la cuenta del tenant; Reasy deduce su fee vía `application_fee_amount`.

**APIs utilizadas:**
- `PaymentIntents`: Crear intención de pago con soporte 3DS.
- `Customers`: Guardar clientes para cobros futuros.
- `PaymentMethods`: Guardar tarjetas con consentimiento del cliente.
- `Refunds`: Procesar devoluciones automáticas.
- `Webhooks`: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`.

**Consideración crítica:** Los webhooks de Stripe deben procesarse con **idempotencia** completa. El sistema verifica en DB si el `stripe_event_id` ya fue procesado antes de actuar. Esto previene cobros o reembolsos duplicados en caso de reintentos de Stripe.

### 10.2 SendGrid (Email)

**Propósito:** Envío de emails transaccionales (confirmaciones, recordatorios, reembolsos).

**Integración:** API v3 de SendGrid.

**Consideraciones:**
- Usar dominios de envío verificados por tenant (sender authentication).
- Tracking de aperturas y clicks para métricas de engagement.
- Bounce handling: marcar emails rebotados en el perfil del cliente.
- Fallback: si SendGrid no está disponible, los eventos se encolan en Redis y se procesan cuando el servicio vuelve.

### 10.3 Twilio (SMS — v1.5)

**Propósito:** Envío de OTP por SMS, recordatorios de cita por SMS.

**Consideraciones:**
- Los números de teléfono se almacenan en formato E.164 (+50712345678).
- Los SMS de OTP expiran en 10 minutos.
- Costo por SMS varía por país; monitorear con alertas de gasto.

### 10.4 Dependencias de Infraestructura

| Servicio | Propósito | Alternativa de fallback |
|---|---|---|
| PostgreSQL 15+ | Base de datos principal | Read replica para lecturas analíticas |
| Redis 7+ | Caché, sesiones, queues | Degradación graceful (sin caché) |
| AWS S3 / DO Spaces | Almacenamiento de imágenes (logos, avatares) | CDN propio |
| CloudFlare | CDN, WAF, protección DDoS | — |

---

## 11. Modelo de Negocio y Monetización

### 11.1 Fuentes de Ingresos

| Fuente | Modelo | Cálculo estimado |
|---|---|---|
| Suscripciones | Mensual/anual por tenant | Ver tabla de planes |
| Fee de transacción | % del depósito cobrado por Stripe Connect | Varía por plan |
| Add-ons (futuro) | Módulos extra (SMS, integraciones premium) | $10–50/mes por add-on |
| Servicios profesionales | Implementación, customización enterprise | $500–5,000 por proyecto |

### 11.2 Estructura de Planes

| Plan | Precio mensual | Precio anual (20% desc.) | Reservas/mes | Usuarios | Sedes | Fee transacción |
|---|---|---|---|---|---|---|
| Starter | $49 | $470 | 200 | 3 | 1 | 3.5% + $0.30 |
| Professional | $149 | $1,430 | 1,000 | 10 | 3 | 2.9% + $0.30 |
| Business | $399 | $3,830 | 5,000 | 25 | Sin límite | 2.4% + $0.30 |
| Enterprise | Custom | Custom | Sin límite | Sin límite | Sin límite | 1.9% + $0.30 |

### 11.3 Proyección de MRR (Año 1)

Asumiendo distribución de tenants: 80% Starter, 14% Professional, 5% Business, 1% Enterprise.

| Plan | Tenants | MRR/tenant | MRR total |
|---|---|---|---|
| Starter | 400 | $49 | $19,600 |
| Professional | 70 | $149 | $10,430 |
| Business | 25 | $399 | $9,975 |
| Enterprise | 5 | $800 (prom) | $4,000 |
| **Total suscripciones** | **500** | — | **$44,005** |
| Fees de transacción (est.) | — | — | ~$18,000 |
| **MRR Total Estimado** | — | — | **~$62,000** |

### 11.4 Modelo de Free Trial

- 14 días de prueba gratuita en plan Professional.
- Sin necesidad de tarjeta de crédito para iniciar trial.
- Al día 10 del trial: recordatorio de conversión con oferta del 20% descuento primer mes.
- Al final del trial: conversión a plan Starter o upgrade manual.

---

## 12. Métricas de Éxito (KPIs)

### 12.1 Métricas de Producto

| KPI | Definición | Target MVP (3 meses) | Target Año 1 |
|---|---|---|---|
| Time-to-first-booking | Tiempo desde registro de tenant hasta primera reserva recibida | <60 minutos (mediana) | <30 minutos |
| Booking completion rate (guest) | % de flujos de reserva iniciados que se completan | >55% | >65% |
| No-show rate de tenants | % de bookings marcados como no-show / total confirmados | Reducción ≥40% vs. baseline | Reducción ≥60% |
| Setup completion rate | % de tenants que completan el setup wizard | >80% | >90% |
| Tenant activation rate | % de tenants que reciben ≥1 reserva en los primeros 7 días | >60% | >75% |
| Monthly Active Tenants | Tenants con ≥1 booking en el mes | 80% de tenants registrados | 85% |

### 12.2 Métricas de Negocio

| KPI | Definición | Target |
|---|---|---|
| MRR | Monthly Recurring Revenue | Ver proyección §11.3 |
| Churn mensual | Tenants que cancelaron / tenants activos inicio del mes | <3% mensual |
| LTV:CAC ratio | Lifetime Value / Customer Acquisition Cost | >3:1 |
| Net Revenue Retention | MRR de tenants del mes anterior en el mes actual (incluyendo upgrades) | >105% |
| Conversión trial → pago | % de trials que se convierten a plan de pago | >25% |

### 12.3 Métricas de Calidad y Operaciones

| KPI | Target |
|---|---|
| Uptime mensual | >99.5% (MVP), >99.9% (Año 1) |
| Latencia p95 (API) | <1000ms (MVP), <500ms (Año 1) |
| Tasa de doble-bookings | 0 (cero tolerancia) |
| Tiempo de resolución de soporte (P1) | <2 horas |
| Tiempo de resolución de soporte (P2) | <24 horas |
| Emails de confirmación entregados | >98% dentro de 2 minutos |

---

## 13. Priorización y Roadmap

### 13.1 Framework de Priorización

Se usa una combinación de: **Impacto en el usuario** × **Impacto en el negocio** / **Esfuerzo estimado** (RICE simplificado).

### 13.2 MVP (Meses 1–3)

**Objetivo:** Primera reserva real pagada en producción. Tenants piloto operando.

| Feature | Prioridad | Justificación |
|---|---|---|
| Multi-tenancy con RLS | 🔴 P0 | Fundamento de seguridad. Sin esto no hay producto. |
| Auth + RBAC básico | 🔴 P0 | Sin esto nadie puede usar el sistema. |
| Setup wizard de negocio | 🔴 P0 | Sin esto los tenants no pueden configurarse. |
| Motor de disponibilidad | 🔴 P0 | Corazón del producto. |
| Flujo de reserva guest | 🔴 P0 | El producto no existe sin esto. |
| Cobro de depósito (Stripe) | 🔴 P0 | Principal propuesta de valor vs. no-shows. |
| Máquina de estados del booking | 🔴 P0 | Consistencia del ciclo de vida. |
| Notificaciones por email (básicas) | 🔴 P0 | Confirmación y recordatorio son mínimo viable. |
| Cancelación y reembolso automático | 🔴 P0 | Sin esto no se puede cobrar depósito eticamente. |
| Panel de gestión de reservas (admin) | 🔴 P0 | El negocio necesita ver y gestionar sus reservas. |
| Dashboard analítico básico | 🟡 P1 | Importante para retención pero no bloqueante. |
| Creación manual de reservas | 🟡 P1 | Necesario para recepcionistas. |
| CRM de clientes básico | 🟡 P1 | Ver historial mínimo. |
| Audit trail | 🟡 P1 | Requerido para compliance antes de producción. |

### 13.3 Post-MVP v1.1 (Meses 4–5)

| Feature | Prioridad |
|---|---|
| Flujo de cliente registrado (cuenta) | 🟡 P1 |
| Programa de fidelización básico | 🟢 P2 |
| Gestión de waitlist | 🟡 P1 |
| Notificaciones por SMS (Twilio) | 🟢 P2 |
| Calendario personal del staff | 🟡 P1 |
| Gestión de disponibilidad personal (staff) | 🟢 P2 |
| Análisis predictivo de churn (plataforma) | 🟢 P2 |

### 13.4 v1.5 (Meses 6–9)

| Feature | Prioridad |
|---|---|
| API pública REST | 🟡 P1 |
| Sistema de webhooks | 🟡 P1 |
| Integración Google Calendar | 🟢 P2 |
| Add-ons / marketplace básico | 🟢 P2 |
| Dashboard analítico avanzado | 🟢 P2 |
| Soporte multi-idioma (EN) | 🟢 P2 |

### 13.5 v2.0 (Meses 10–18)

- App móvil nativa (iOS / Android).
- Análisis predictivo de negocio (demanda, pricing dinámico).
- Integración con sistemas POS.
- Facturación multi-jurisdiccional avanzada.
- Marketplace de integraciones de terceros.

---

## 14. Riesgos y Mitigaciones

### 14.1 Riesgos Técnicos

| ID | Riesgo | Probabilidad | Impacto | Severidad | Mitigación |
|---|---|---|---|---|---|
| RT-01 | Race condition en disponibilidad genera doble-bookings | Media | Crítico | 🔴 Crítico | `SELECT FOR UPDATE SKIP LOCKED` + tests de concurrencia automatizados |
| RT-02 | Webhook de Stripe procesado más de una vez → cobro duplicado | Media | Crítico | 🔴 Crítico | Tabla de idempotencia con `stripe_event_id` + `UNIQUE` constraint |
| RT-03 | Fuga de datos cross-tenant por error en RLS | Baja | Crítico | 🔴 Crítico | RLS en DB + tests de aislamiento + penetration testing |
| RT-04 | Caída de Stripe afecta flujo de reserva con depósito | Media | Alto | 🟡 Alto | Circuit breaker + fallback a "reserva sin depósito, cobro posterior" |
| RT-05 | Crecimiento de DB sin particionamiento degrada performance | Media | Medio | 🟡 Medio | Particionamiento por tenant_id en bookings desde el inicio |
| RT-06 | Invalidación de caché de disponibilidad incompleta genera slots incorrectos | Media | Alto | 🟡 Alto | Invalidación explícita por event + TTL corto (5 min) como fallback |

### 14.2 Riesgos de Producto

| ID | Riesgo | Mitigación |
|---|---|---|
| RP-01 | El flujo de reserva guest tiene mucha fricción → tasa de conversión baja | Tests de usabilidad con usuarios reales antes del lanzamiento. A/B testing de pasos. |
| RP-02 | Setup wizard demasiado complejo → tenants no activan el producto | Simplificar al mínimo viable. Onboarding asistido para primeros 50 tenants. |
| RP-03 | Precio no competitivo para el mercado objetivo | Validar precio con entrevistas de descubrimiento antes del lanzamiento. |
| RP-04 | Features de compliance (GDPR) subestimados en esfuerzo | Incluir en el scope del MVP. No lanzar sin right-to-erasure funcional. |

### 14.3 Riesgos de Mercado

| ID | Riesgo | Mitigación |
|---|---|---|
| RM-01 | Competidor grande (Square, Stripe) lanza producto similar para LATAM | Profundizar en verticales específicos (beauty). Competir en precio y localización. |
| RM-02 | Baja adopción digital en el segmento objetivo | Onboarding presencial para primeros clientes. Material de capacitación en video. |
| RM-03 | Churn alto por dificultad de integración del widget en sitios existentes | Simplificar integración (1 línea de código). Ofrecer instalación asistida. |

---

## 15. Supuestos y Restricciones

### 15.1 Supuestos

| ID | Supuesto | Consecuencia si es falso |
|---|---|---|
| AS-01 | Stripe Connect está disponible y operativo en Panamá, Colombia y México | Necesidad de integrar pasarela alternativa regional (ej. Kushki, Conekta) |
| AS-02 | Los negocios objetivo tienen acceso a internet estable | Considerar modo offline parcial para futuras versiones |
| AS-03 | Los clientes finales tienen email válido o número de teléfono | Si no, el flujo de OTP falla y no hay manera de verificar identidad |
| AS-04 | El equipo de desarrollo tiene experiencia en Laravel y PostgreSQL | Si no, la curva de aprendizaje retrasa el MVP |
| AS-05 | Los tenants usan dispositivos modernos con navegadores actualizados | Limita la necesidad de soporte de IE y browsers legacy |

### 15.2 Restricciones

| ID | Restricción | Razón |
|---|---|---|
| RST-01 | Backend en Laravel 11 + Livewire 3 | Decisión de stack tomada; equipo existente |
| RST-02 | PHP 8.3+ | Compatibilidad con Laravel 11 |
| RST-03 | PostgreSQL 15+ como única base de datos | RLS, JSONB nativo, particionamiento |
| RST-04 | Sin infraestructura compartida (shared hosting) | Incompatible con multi-tenancy seguro y RLS |
| RST-05 | Sin app móvil nativa en v1 | Costo de desarrollo vs. tiempo al mercado |
| RST-06 | Stripe como único procesador de pago en v1 | Tiempo de integración y certificación PCI |
| RST-07 | Solo español en la UI para v1 | Mercado objetivo LATAM; inglés en v1.5 |
| RST-08 | Presupuesto de infraestructura <$5,000 USD/mes en MVP | Restricción financiera de la fase inicial |

---

## 16. Criterios de Aceptación por Feature

Esta sección define las condiciones que debe cumplir cada feature antes de considerarse "Done" para propósitos de QA y release.

### 16.1 Definición Global de "Done"

Un feature está **Done** cuando:

- [ ] El código pasa todos los tests unitarios existentes.
- [ ] Se escribieron tests unitarios para la nueva funcionalidad (cobertura ≥80% en módulos críticos).
- [ ] Los tests de integración para el flujo end-to-end pasan.
- [ ] El feature funciona correctamente en los navegadores target (Chrome, Firefox, Safari, Edge — últimas 2 versiones).
- [ ] El feature es responsive y funcional en dispositivos móviles (viewport 375px mínimo).
- [ ] No hay errores en la consola del navegador.
- [ ] El feature cumple con los criterios de aceptación específicos documentados en §7.
- [ ] El código fue revisado por al menos 1 peer (code review).
- [ ] La documentación relevante fue actualizada.
- [ ] El feature está detrás de un feature flag si tiene riesgo alto.
- [ ] El audit trail registra correctamente las acciones relevantes.
- [ ] El aislamiento multi-tenant fue verificado (datos del feature solo accesibles por el tenant correcto).

### 16.2 Criterios Específicos del Booking Flow (CRÍTICO)

Un flujo de reserva está **Done** cuando pasa estos escenarios de test:

| Escenario | Resultado esperado |
|---|---|
| 2 usuarios reservan el mismo slot simultáneamente | Solo 1 booking se confirma; el otro recibe error claro |
| Cliente paga depósito y Stripe confirma vía webhook | Booking pasa a `confirmed`; cliente recibe email |
| Cliente no completa el pago en 15 minutos | Booking pasa a `expired`; slot queda disponible |
| Cliente cancela con >48h de anticipación | Reembolso del 100% procesado automáticamente en Stripe |
| Cliente cancela con <24h de anticipación | Sin reembolso; depósito retenido; booking → `cancelled` |
| Admin marca booking como no-show | Booking → `no_show`; depósito retenido; email enviado al cliente |
| Webhook de Stripe recibido dos veces (retry) | Solo se procesa una vez; segundo intento es idempotente |
| Slot seleccionado entre paso OTP y confirmación (slot se ocupa) | Error claro con alternativas; datos del cliente conservados |

---

## 17. Glosario

| Término | Definición |
|---|---|
| **Tenant** | Negocio cliente de Reasy. Cada tenant es completamente aislado de los demás. |
| **Booking** | Reserva o cita. Ciclo de vida gestionado por máquina de estados. |
| **Slot** | Franja horaria específica en la que un recurso puede atender un servicio. |
| **Resource** | Entidad reservable: personal (staff), sala (room) o equipamiento (equipment). |
| **Depósito** | Pago parcial anticipado requerido para confirmar una reserva. Puede ser porcentual o fijo. |
| **No-show** | Cliente que no se presentó a su cita confirmada. El depósito es retenido. |
| **Buffer** | Tiempo de preparación o limpieza antes/después de un servicio. Bloquea el slot del recurso. |
| **Widget** | Componente de booking embebible en el sitio web del negocio. |
| **RLS** | Row-Level Security: mecanismo de PostgreSQL que filtra filas según el contexto de sesión. |
| **TOCTOU** | Time-Of-Check-Time-Of-Use: race condition donde el estado cambia entre que se verifica y se actúa. |
| **Idempotencia** | Propiedad de una operación que produce el mismo resultado si se ejecuta múltiples veces. |
| **OTP** | One-Time Password: código de verificación de un solo uso enviado por email o SMS. |
| **MRR** | Monthly Recurring Revenue: ingresos recurrentes mensuales. |
| **Churn** | Tasa de cancelación de tenants. Un tenant churned es uno que canceló su suscripción. |
| **CAC** | Customer Acquisition Cost: costo promedio de adquirir un nuevo tenant. |
| **LTV** | Lifetime Value: ingresos totales esperados de un tenant durante su ciclo de vida. |
| **GDPR** | General Data Protection Regulation: regulación europea de protección de datos. |
| **PCI-DSS** | Payment Card Industry Data Security Standard: norma de seguridad para datos de tarjetas. |
| **Stripe Connect** | Producto de Stripe que permite a plataformas (Reasy) facilitar pagos entre sus usuarios (tenants) y sus clientes. |
| **RBAC** | Role-Based Access Control: sistema de permisos basado en roles. |
| **DDD** | Domain-Driven Design: metodología de diseño de software centrada en el dominio de negocio. |
| **JWT** | JSON Web Token: estándar de tokens de autenticación compactos y auto-contenidos. |
| **RRULE** | Recurrence Rule (RFC 5545): formato estándar para definir reglas de recurrencia en calendarios. |
| **WAL** | Write-Ahead Logging: mecanismo de PostgreSQL para replicación continua. |
| **CDN** | Content Delivery Network: red de distribución de contenido estático geográficamente distribuida. |
| **Circuit Breaker** | Patrón de diseño que previene que fallas en servicios externos se propaguen al sistema. |
| **Feature Flag** | Mecanismo para activar/desactivar features sin despliegue de código. |
| **p95 / p99** | Percentil 95 / 99: la latencia que el 95%/99% de las requests no supera. |

---

*Fin del Product Requirements Document — Reasy v1.0*

*Documento mantenido por: Equipo de Producto*  
*Próxima revisión programada: Octubre 2025 (post-sprint 1)*
