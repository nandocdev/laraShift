# Reasy - Documento de Requisitos

## Sistema SaaS de Gestión de Reservas Multi-Negocio

> **Versión:** 2.3 (alineada a openSaaS + estado verificado en repo)
> **Fecha:** Septiembre 2025 (rev. estado: 2026-09-11, rama `main`)
> **Tipo:** Especificación de Requisitos de Software (SRS)
> **Nombres anteriores deprecados:** ReserveHub. El nombre oficial del producto es **Reasy** sobre el framework **openSaaS**.
>
> **Estado de implementación (§7):** verificado contra el código de `main` (commits `f2d904a/6379d01/fbdbce8`). Leyenda: `✅ existe` · `🟡 parcial` · `⬜ no existe` · `🚫 diferido MVP`. El avance de `01_PRD.md §14` (8/12) corresponde al prototipo legado, no a este repo: `app/Modules/Product/` no existe y ningún UC `BO/CL/BM` tiene código aquí.

> **Alineación normativa openSaaS (lectura obligatoria):**
>
> - Reasy es **producto vertical**, no core. Toda la lógica de este SRS (negocios, sedes, servicios, recursos, horarios, reservas, clientes) vive en la **capa de extensión del producto**, nunca en `Tenant/Access|Compliance|Experience|Integrations|Workspace` del core. Ver `docs/PROJECT_DECISIONS.md §11`, `docs/ARCHITECTURE_RULES.md`.
> - [ ] **Multitenancy:** Single Database + `tenant_id` + PostgreSQL RLS (`SET LOCAL` transaccional). `business_id` = `tenant_id` lógico. Sin sharding, sin particionamiento por tenant hasta ADR con demanda real.
> - [ ] **Stack:** Laravel 13+, Livewire 4 + FluxUI 2 + TailwindCSS, PostgreSQL + RLS, Redis (cache `tenant:{id}:*`, colas Horizon, locks, sesiones Redis obligatorias en staging/prod). Prohibidos: microservicios, React/Vue/Inertia en paneles, Repository genérico, CQRS/Event Sourcing, JWT unificado.
> - [ ] **Backend:** Actions (`final readonly class` + `execute(DTO)` con `spatie/laravel-data`) + Contracts/Events. Livewire solo estado de UI. Jobs `TenantAware` + `RehydrateTenantContext`. Todo módulo tenant-aware exige `CrossTenantLeakTest`.
> - [ ] **Billing:** Clave redirect primero, dLocal Smart Fields + MIT tarjeta segundo, Stripe diferido (`docs/BILLING.md`). RF21/RF41/RF50 se implementan vía Billing Core + capabilities, nunca con SDK directo en Actions. Dinero en `amount_cents int`.
> - En contradicción con `docs/PROJECT_DECISIONS.md`, prevalece `PROJECT_DECISIONS.md`.

---

## Índice

1. [Introducción](#1-introducción)
2. [Stakeholders y Casos de Uso](#2-stakeholders-y-casos-de-uso)
3. [Requisitos Funcionales](#3-requisitos-funcionales)
4. [Requisitos No Funcionales](#4-requisitos-no-funcionales)
5. [Reglas de Negocio](#5-reglas-de-negocio)
6. [Matriz de Trazabilidad](#6-matriz-de-trazabilidad)
7. [Estado de implementación (verificado en código)](#7-estado-de-implementación-verificado-en-código)

---

## 1. Introducción

### 1.1 Propósito

Este documento especifica los requisitos funcionales y no funcionales para Reasy, una plataforma SaaS de gestión de reservas y citas que permite a múltiples negocios administrar sus operaciones de booking de manera eficiente y escalable.

### 1.2 Alcance

Reasy será un sistema multi-tenant sobre openSaaS que soportará:

- Gestión de múltiples negocios (tenants openSaaS; `business_id` = `tenant_id`)
- Múltiples ubicaciones por negocio
- Diferentes tipos de servicios y recursos
- Clientes registrados y no registrados
- Sistema de pagos integrado con depósitos opcionales
- Políticas configurables de cancelación y reembolso

### 1.3 Audiencia

- Equipo de desarrollo
- Arquitectos de software
- Product managers
- Stakeholders del negocio
- Equipos de QA y testing

---

## 2. Stakeholders y Casos de Uso

### 2.1 Stakeholders Identificados

1. **Administrador de Plataforma** (SaaS Owner)
2. **Administrador de Negocio** (Tenant Owner)
3. **Usuarios de Negocio** (Staff/Empleados)
4. **Clientes Registrados del Negocio**
5. **Clientes No Registrados del Negocio** (Guest)
6. **Super Admin/Soporte Técnico**
7. **Desarrollador/Integrador de Terceros**
8. **Auditor/Compliance Officer**

### 2.2 Matriz de Stakeholders

| Stakeholder              | Nivel de Acceso | Responsabilidad Principal             |
| ------------------------ | --------------- | ------------------------------------- |
| Administrador Plataforma | Global          | Gestión de tenants y plataforma       |
| Administrador Negocio    | Tenant          | Configuración y operación del negocio |
| Staff/Empleados          | Limitado        | Operaciones diarias y agenda personal |
| Clientes Registrados     | Cliente         | Autogestión de reservas               |
| Clientes Guest           | Mínimo          | Reservas puntuales                    |
| Super Admin              | Soporte         | Troubleshooting y soporte             |
| Desarrollador            | API             | Integraciones de terceros             |
| Auditor                  | Solo lectura    | Compliance y auditorías               |

---

## 3. Requisitos Funcionales

### 3.1 Administrador de Plataforma (SaaS Owner)

#### RF1 - Gestión de Tenants

- [x] **RF1.1** - El administrador debe poder crear, visualizar, suspender y eliminar cuentas de negocio (tenants)
- [x] **RF1.2** - El sistema debe permitir la búsqueda y filtrado de tenants por múltiples criterios
- [x] **RF1.3** - El administrador debe poder acceder a información detallada de cada tenant sin acceder a datos de clientes

#### RF2 - Gestión de Planes de Suscripción

- [x] **RF2.1** - El sistema debe permitir crear y editar planes de suscripción con precios, límites y características (`Catalog/Livewire/ManagePlans`: crear/editar/desactivar/duplicar + precios en centavos, quotas, `display_features`, `gateway_ids`)
- [x] **RF2.2** - El administrador debe poder asignar o cambiar manualmente el plan de cualquier tenant (`Provisioning/Actions/ChangeTenantPlanAction` + `ManageTenant::changePlan` + `Billing/Livewire/SubscriptionDetail::changePlan`; sincroniza `tenants.plan_id` ↔ `subscriptions.plan_id`, rechaza planes inactivos, `activity('provisioning'/'billing')`)
- [~] **RF2.3** - El sistema debe aplicar automáticamente los límites definidos en cada plan (`CatalogPlanQuotaResolver` + `ResolveTenantFeatures` con caché existen; `Tenant::getQuotaLimit()` aún retorna `-1` fijo)
- [x] **RF2.4** - El sistema debe permitir planes customizados para clientes enterprise (`plans.is_custom`: ocultos del catálogo público `PlanManager::active()`/`SelectPlan`, asignables por staff vía `ManageTenant`/`SubscriptionDetail`, resolubles por `find()` para features y billing)

#### RF3 - Dashboard de Plataforma

- [x] **RF3.1** - El sistema debe mostrar métricas de negocio clave (`Auth/Livewire/Dashboard`: MRR por moneda desde suscripciones no canceladas, churn 30d, breakdown Active/Suspended/Quarantined)
- [~] **RF3.2** - El dashboard debe mostrar el número total de transacciones y reservas (transacciones ✅: volumen aprobado 30d + pendientes vía `payments`; reservas ⬜: sin módulo de producto en el repo)
- [x] **RF3.3** - El sistema debe proporcionar gráficos de tendencias temporales (tenants + suscripciones por día, 7 días, doble serie)
- [x] **RF3.4** - El dashboard debe actualizarse en tiempo casi real (`wire:poll.60s` sobre computeds sin caché)

#### RF4 - Facturación de Plataforma

- [~] **RF4.1** - El sistema debe generar automáticamente facturas mensuales por tenant (Migraciones listas, falta automatización)
- [x] **RF4.2** - El administrador debe poder visualizar el historial de facturación completo
- [ ] **RF4.3** - El sistema debe gestionar automáticamente los cargos por transacción
- [ ] **RF4.4** - El sistema debe manejar prorrateos por cambios de plan

#### RF5 - Configuración Global

- [ ] **RF5.1** - El administrador debe poder configurar integraciones de pago globales
- [ ] **RF5.2** - El sistema debe permitir configurar plantillas de email transaccionales por defecto
- [~] **RF5.3** - El administrador debe poder establecer políticas globales de la plataforma

#### RF6 - Comunicación con Tenants

- [~] **RF6.1** - El sistema debe permitir enviar notificaciones a todos los administradores de negocio (Módulo Support/Notificaciones base creado, falta flujos completos)
- [~] **RF6.2** - El administrador debe poder crear anuncios de mantenimiento o nuevas funcionalidades (Módulo Support/Notificaciones base creado, falta flujos completos)
- [~] **RF6.3** - El sistema debe mantener un historial de comunicaciones enviadas (Módulo Support/Notificaciones base creado, falta flujos completos)

#### RF7 - Compliance y Auditoría

- [ ] **RF7.1** - El sistema debe generar reportes de compliance (GDPR, PCI-DSS) para todos los tenants
- [x] **RF7.2** - El administrador debe tener acceso a logs de auditoría centralizados con búsqueda y filtrado
- [ ] **RF7.3** - El sistema debe proporcionar herramientas para gestionar solicitudes de eliminación de datos

#### RF8 - Monitoreo de Salud del Sistema

- [~] **RF8.1** - El dashboard debe mostrar métricas de infraestructura y performance en tiempo real (Módulo Observability existe, falta dashboard UI)
- [~] **RF8.2** - El sistema debe generar alertas automáticas para incidentes críticos (Módulo Observability existe, falta dashboard UI)
- [~] **RF8.3** - El administrador debe poder ejecutar maintenance mode por tenant o globalmente (Módulo Observability existe, falta dashboard UI)

#### RF9 - Gestión de Integraciones

- [~] **RF9.1** - El sistema debe permitir gestionar integraciones de terceros centralizadamente (Tablas de webhooks y api keys creadas)
- [~] **RF9.2** - El administrador debe poder configurar webhooks para partners y desarrolladores (Tablas de webhooks y api keys creadas)
- [~] **RF9.3** - El sistema debe gestionar API keys y rate limits por tenant (Tablas de webhooks y api keys creadas)

#### RF10 - Análisis Predictivo

- [ ] **RF10.1** - El sistema debe identificar tenants en riesgo de churn usando algoritmos predictivos
- [ ] **RF10.2** - El dashboard debe mostrar análisis de patrones de uso para optimización
- [ ] **RF10.3** - El sistema debe generar recomendaciones automáticas de upgrade de plan

### 3.2 Administrador de Negocio (Tenant Owner)

#### RF11 - Configuración del Negocio

- [~] **RF11.1** - El sistema debe permitir configurar perfil completo: nombre, logo, descripción, dirección (Módulo Workspace existe, falta UI detallada)
- [~] **RF11.2** - El administrador debe poder establecer zona horaria y moneda del negocio (Módulo Workspace existe, falta UI detallada)
- [~] **RF11.3** - El sistema debe permitir personalización de branding para clientes (Módulo Workspace existe, falta UI detallada)

#### RF12 - Gestión de Sedes/Ubicaciones

- [ ] **RF12.1** - El sistema debe permitir gestionar múltiples ubicaciones de forma independiente
- [ ] **RF12.2** - Cada sede debe poder tener su propia configuración de horarios y servicios
- [ ] **RF12.3** - El sistema debe permitir transferir recursos entre sedes

#### RF13 - Gestión de Servicios

- [ ] **RF13.1** - El administrador debe poder crear, editar y categorizar servicios
- [ ] **RF13.2** - El sistema debe permitir definir duración, capacidad y precio por servicio
- [ ] **RF13.3** - El administrador debe poder configurar políticas de depósito (ninguno, porcentaje, cantidad fija)
- [ ] **RF13.4** - El sistema debe permitir configurar políticas de cancelación personalizadas por servicio
- [ ] **RF13.5** - El administrador debe poder establecer buffers antes y después de cada servicio
- [ ] **RF13.6** - El sistema debe soportar servicios grupales con capacidad múltiple

#### RF14 - Gestión de Recursos

- [ ] **RF14.1** - El sistema debe permitir gestionar personal, salas y equipamiento como recursos
- [ ] **RF14.2** - El administrador debe poder asignar servicios específicos a cada recurso
- [ ] **RF14.3** - El sistema debe permitir definir habilidades y especialidades por recurso
- [ ] **RF14.4** - El administrador debe poder establecer tarifas diferentes por recurso

#### RF15 - Gestión de Horarios

- [ ] **RF15.1** - El sistema debe proporcionar interfaz visual (calendario) para definir horarios de trabajo
- [ ] **RF15.2** - El administrador debe poder configurar días libres y vacaciones por recurso
- [ ] **RF15.3** - El sistema debe permitir horarios recurrentes y excepciones
- [ ] **RF15.4** - El sistema debe manejar automáticamente feriados y días no laborables

#### RF16 - Gestión de Usuarios (Empleados)

- [~] **RF16.1** - El administrador debe poder invitar empleados por email (Módulo Access y 2FA base, falta roles granulares)
- [~] **RF16.2** - El sistema debe permitir asignar roles específicos (recepcionista, profesional, gerente) (Módulo Access y 2FA base, falta roles granulares)
- [~] **RF16.3** - El administrador debe poder configurar permisos granulares por empleado (Módulo Access y 2FA base, falta roles granulares)
- [~] **RF16.4** - El sistema debe mantener historial de accesos y acciones de empleados (Módulo Access y 2FA base, falta roles granulares)

#### RF17 - Panel de Control de Reservas

- [ ] **RF17.1** - El sistema debe mostrar todas las reservas en vista calendario y lista
- [ ] **RF17.2** - El administrador debe poder filtrar reservas por múltiples criterios
- [ ] **RF17.3** - El sistema debe permitir búsqueda avanzada de reservas
- [ ] **RF17.4** - El administrador debe poder modificar reservas con permisos apropiados

#### RF18 - Creación Manual de Reservas

- [ ] **RF18.1** - El sistema debe permitir crear reservas manualmente desde el panel administrativo
- [ ] **RF18.2** - El administrador debe poder asignar reservas a clientes existentes o crear nuevos
- [ ] **RF18.3** - El sistema debe aplicar las mismas validaciones que las reservas online
- [ ] **RF18.4** - El sistema debe permitir overrides administrativos cuando sea necesario

#### RF19 - Gestión de Clientes

- [ ] **RF19.1** - El sistema debe mantener base de datos completa de clientes del negocio
- [ ] **RF19.2** - El administrador debe poder ver historial completo de reservas por cliente
- [ ] **RF19.3** - El sistema debe permitir añadir notas y tags a los perfiles de cliente
- [ ] **RF19.4** - El administrador debe poder segmentar clientes para marketing

#### RF20 - Dashboard de Negocio

- [ ] **RF20.1** - El sistema debe mostrar KPIs relevantes: ingresos, ocupación, reservas por servicio
- [ ] **RF20.2** - El dashboard debe incluir métricas de performance de empleados
- [ ] **RF20.3** - El sistema debe mostrar tendencias y comparaciones temporales
- [ ] **RF20.4** - El dashboard debe permitir exportar reportes en múltiples formatos

#### RF21 - Integración de Pagos (vía Billing Core openSaaS, no SDK directo)

- [~] **RF21.1** - El administrador debe poder operar con los proveedores soportados por el Billing Core: Clave (redirect) y dLocal (Smart Fields + MIT tarjeta). Stripe queda diferido hasta demanda real (`docs/BILLING.md §16`). Prohibido conectar SDKs de pago directamente desde Actions. (Estructura de pagos Billing Core en progreso)
- [~] **RF21.2** - El sistema debe permitir configurar múltiples métodos de pago solo entre los que el proveedor declara vía `supports(capability, forMethod)` (p. ej. dLocal tarjeta sí admite recurrencia; efectivo/voucher no) (Estructura de pagos Billing Core en progreso)
- [ ] **RF21.3** - El administrador debe poder ver reconciliación de pagos y comisiones
- [ ] **RF21.4** - El sistema debe generar reportes fiscales y de impuestos

#### RF22 - Personalización de Notificaciones

- [ ] **RF22.1** - El administrador debe poder personalizar plantillas de email y SMS
- [ ] **RF22.2** - El sistema debe permitir configurar timing de recordatorios
- [ ] **RF22.3** - El administrador debe poder incluir branding personalizado en notificaciones
- [ ] **RF22.4** - El sistema debe soportar múltiples idiomas en notificaciones

#### RF23 - Gestión Financiera Avanzada

- [ ] **RF23.1** - El sistema debe proporcionar panel de facturación detallado por período
- [ ] **RF23.2** - El administrador debe poder generar reportes de impuestos multi-jurisdiccionales
- [ ] **RF23.3** - El sistema debe permitir gestión de descuentos, promociones y códigos de cupón
- [ ] **RF23.4** - El sistema debe mostrar análisis de rentabilidad por servicio y recurso

#### RF24 - Gestión de Inventario

- [ ] **RF24.1** - El sistema debe permitir control de stock para productos relacionados
- [ ] **RF24.2** - El administrador debe poder gestionar equipamiento con calendarios de mantenimiento
- [ ] **RF24.3** - El sistema debe manejar reserva de salas y recursos compartidos

#### RF25 - Automatización de Marketing

- [ ] **RF25.1** - El sistema debe permitir crear campañas automáticas de email marketing
- [ ] **RF25.2** - El administrador debe poder configurar seguimiento de clientes inactivos
- [ ] **RF25.3** - El sistema debe soportar programas básicos de referidos y loyalty
- [ ] **RF25.4** - El sistema debe permitir integración con redes sociales para promociones

#### RF26 - Gestión de Waitlists

- [ ] **RF26.1** - El sistema debe crear automáticamente listas de espera para horarios ocupados
- [ ] **RF26.2** - El sistema debe notificar automáticamente cuando se libere un slot
- [ ] **RF26.3** - El administrador debe poder priorizar waitlist (clientes VIP, orden de llegada)
- [ ] **RF26.4** - El sistema debe permitir gestión manual de waitlists

### 3.3 Usuarios de Negocio (Staff/Empleados)

#### RF27 - Vista de Calendario Personal

- [ ] **RF27.1** - El empleado debe poder visualizar su calendario personal por día, semana y mes
- [ ] **RF27.2** - El sistema debe mostrar detalles relevantes de cada cita sin información sensible
- [ ] **RF27.3** - La vista debe actualizarse automáticamente cuando hay cambios

#### RF28 - Gestión de Citas Propias

- [ ] **RF28.1** - El empleado debe poder ver detalles completos de sus citas asignadas
- [ ] **RF28.2** - El sistema debe permitir añadir notas internas a las citas
- [ ] **RF28.3** - El empleado debe poder reprogramar citas si tiene permisos apropiados
- [ ] **RF28.4** - El sistema debe mantener historial de cambios realizados

#### RF29 - Gestión del Estado de Cita

- [ ] **RF29.1** - El empleado debe poder marcar citas como "Completada" o "No Presentado"
- [ ] **RF29.2** - El sistema debe permitir añadir notas sobre el servicio realizado
- [ ] **RF29.3** - El empleado debe poder iniciar proceso de cobro si es necesario
- [ ] **RF29.4** - El sistema debe actualizar automáticamente métricas de performance

#### RF30 - Gestión de Disponibilidad

- [ ] **RF30.1** - El empleado debe poder bloquear franjas horarias con aprobación apropiada
- [ ] **RF30.2** - El sistema debe permitir solicitar tiempo libre o vacaciones
- [ ] **RF30.3** - El empleado debe poder ver su disponibilidad futura planificada
- [ ] **RF30.4** - El sistema debe prevenir conflictos de horarios automáticamente

#### RF31 - Notificaciones en Tiempo Real

- [ ] **RF31.1** - El empleado debe recibir notificaciones cuando se le asigne nueva reserva
- [ ] **RF31.2** - El sistema debe notificar cancelaciones o cambios de sus citas
- [ ] **RF31.3** - El empleado debe recibir recordatorios de citas próximas
- [ ] **RF31.4** - El sistema debe permitir configurar preferencias de notificación

#### RF32 - Gestión de Comisiones

- [ ] **RF32.1** - El empleado debe poder ver seguimiento de ingresos generados
- [ ] **RF32.2** - El sistema debe calcular automáticamente comisiones según reglas configurables
- [ ] **RF32.3** - El empleado debe poder ver reportes de performance individual
- [ ] **RF32.4** - El sistema debe mostrar comparativas con períodos anteriores

#### RF33 - Comunicación Interna

- [ ] **RF33.1** - El sistema debe proporcionar chat interno entre empleados del negocio
- [ ] **RF33.2** - Los empleados deben poder compartir notas sobre clientes apropiadamente
- [ ] **RF33.3** - El sistema debe facilitar handoffs entre turnos
- [ ] **RF33.4** - El sistema debe mantener historial de comunicaciones internas

#### RF34 - Gestión de Tareas

- [ ] **RF34.1** - El empleado debe poder mantener lista de tareas pendientes
- [ ] **RF34.2** - El sistema debe permitir recordatorios personalizados
- [ ] **RF34.3** - El empleado debe poder integrar tareas con calendario
- [ ] **RF34.4** - El sistema debe permitir asignación de tareas por supervisores

#### RF35 - Check-in/Check-out de Clientes

- [ ] **RF35.1** - El empleado debe poder marcar llegada de clientes desde dispositivo móvil
- [ ] **RF35.2** - El sistema debe gestionar sala de espera virtual
- [ ] **RF35.3** - El empleado debe poder registrar tiempo real vs. tiempo planificado
- [ ] **RF35.4** - El sistema debe actualizar disponibilidad en tiempo real

### 3.4 Clientes Registrados del Negocio

#### RF36 - Autenticación

- [ ] **RF36.1** - El cliente debe poder registrarse con email y password
- [ ] **RF36.2** - El sistema debe permitir login con credenciales o SSO
- [ ] **RF36.3** - El cliente debe poder recuperar password via email
- [ ] **RF36.4** - El sistema debe soportar autenticación de dos factores opcional

#### RF37 - Perfil de Usuario

- [ ] **RF37.1** - El cliente debe poder ver y editar información personal
- [ ] **RF37.2** - El sistema debe permitir actualizar preferencias de contacto
- [ ] **RF37.3** - El cliente debe poder gestionar métodos de pago guardados
- [ ] **RF37.4** - El sistema debe permitir configurar preferencias de notificación

#### RF38 - Flujo de Reserva Simplificado

- [ ] **RF38.1** - El sistema debe autocompletar datos del cliente al reservar
- [ ] **RF38.2** - El cliente debe poder usar métodos de pago guardados
- [ ] **RF38.3** - El sistema debe recordar preferencias de servicios y profesionales
- [ ] **RF38.4** - El cliente debe poder hacer reservas recurrentes

#### RF39 - Historial de Reservas

- [ ] **RF39.1** - El cliente debe poder ver lista completa de reservas pasadas y futuras
- [ ] **RF39.2** - El sistema debe mostrar detalles de cada reserva claramente
- [ ] **RF39.3** - El cliente debe poder descargar comprobantes e historial
- [ ] **RF39.4** - El sistema debe permitir búsqueda en historial personal

#### RF40 - Autogestión de Reservas

- [ ] **RF40.1** - El cliente debe poder cancelar reservas según política del negocio
- [ ] **RF40.2** - El sistema debe permitir reprogramar reservas dentro de políticas
- [ ] **RF40.3** - El cliente debe poder ver claramente restricciones y costos
- [ ] **RF40.4** - El sistema debe procesar automáticamente reembolsos cuando aplique

#### RF41 - Gestión de Pagos

- [ ] **RF41.1** - El cliente debe poder guardar métodos de pago de forma segura
- [ ] **RF41.2** - El sistema debe soportar checkout en 1-clic
- [ ] **RF41.3** - El cliente debe poder ver historial de transacciones
- [ ] **RF41.4** - El sistema debe manejar automáticamente facturación recurrente

#### RF42 - Programa de Fidelidad

- [ ] **RF42.1** - El sistema debe acumular puntos por reservas completadas
- [ ] **RF42.2** - El cliente debe poder ver nivel de membresía y beneficios
- [ ] **RF42.3** - El sistema debe aplicar automáticamente descuentos por nivel
- [ ] **RF42.4** - El cliente debe poder canjear puntos por servicios

#### RF43 - Preferencias Avanzadas

- [ ] **RF43.1** - El cliente debe poder marcar profesionales favoritos
- [ ] **RF43.2** - El sistema debe recordar horarios preferidos del cliente
- [ ] **RF43.3** - El cliente debe poder configurar servicios frecuentes para booking rápido
- [ ] **RF43.4** - El sistema debe permitir recordatorios personalizados

#### RF44 - Social Features

- [ ] **RF44.1** - El cliente debe poder compartir experiencias en redes sociales
- [ ] **RF44.2** - El sistema debe facilitar referir amigos con incentivos
- [ ] **RF44.3** - El cliente debe poder dejar reviews y ratings de servicios
- [ ] **RF44.4** - El sistema debe mostrar reviews de otros clientes apropiadamente

#### RF45 - Gestión de Suscripciones

- [ ] **RF45.1** - El cliente debe poder adquirir membresías mensuales o anuales
- [ ] **RF45.2** - El sistema debe manejar paquetes de servicios (ej. 10 sesiones)
- [ ] **RF45.3** - El cliente debe poder gestionar auto-renovación
- [ ] **RF45.4** - El sistema debe mostrar claramente uso y balance de paquetes

### 3.5 Clientes No Registrados (Guest)

#### RF46 - Flujo de Reserva sin Cuenta

- [ ] **RF46.1** - El cliente debe poder completar reserva sin crear password
- [ ] **RF46.2** - El sistema debe requerir solo información esencial (nombre, contacto)
- [ ] **RF46.3** - El proceso debe ser optimizado para mínima fricción
- [ ] **RF46.4** - El sistema debe ofrecer opción de crear cuenta después de reservar

#### RF47 - Selección de Servicio y Horario

- [ ] **RF47.1** - El cliente debe poder ver servicios disponibles con descripciones claras
- [ ] **RF47.2** - El sistema debe mostrar calendario interactivo con slots libres
- [ ] **RF47.3** - El cliente debe poder filtrar por tipo de servicio, profesional o ubicación
- [ ] **RF47.4** - El sistema debe mostrar precios y duración claramente

#### RF48 - Entrada de Datos Mínima

- [ ] **RF48.1** - El sistema debe solicitar solo nombre, email y/o teléfono
- [ ] **RF48.2** - La validación de campos debe ser en tiempo real
- [ ] **RF48.3** - El sistema debe detectar automáticamente zona horaria del cliente
- [ ] **RF48.4** - El formulario debe ser totalmente responsive

#### RF49 - Verificación de Contacto

- [ ] **RF49.1** - El sistema debe verificar email o teléfono via OTP
- [ ] **RF49.2** - La verificación debe completarse en menos de 2 minutos
- [ ] **RF49.3** - El sistema debe prevenir spam y bookings falsos
- [ ] **RF49.4** - Debe haber método alternativo de verificación

#### RF50 - Proceso de Pago

- [ ] **RF50.1** - Si requiere depósito, debe integrar pasarela segura (PCI compliant)
- [ ] **RF50.2** - El cliente debe ver claramente qué se está cobrando
- [ ] **RF50.3** - El sistema debe soportar múltiples métodos de pago
- [ ] **RF50.4** - El proceso debe completarse en menos de 30 segundos

#### RF51 - Confirmación y Gestión por Enlace

- [ ] **RF51.1** - El cliente debe recibir confirmación inmediata vía email/SMS
- [ ] **RF51.2** - El sistema debe proporcionar enlace único para gestionar reserva
- [ ] **RF51.3** - El cliente debe poder ver, cancelar o reprogramar via enlace
- [ ] **RF51.4** - El enlace debe tener security token y expiración apropiada

#### RF52 - Conversión a Cliente Registrado

- [ ] **RF52.1** - El sistema debe ofrecer incentivos post-servicio para crear cuenta
- [ ] **RF52.2** - Al registrarse debe importar automáticamente historial de reservas guest
- [ ] **RF52.3** - El cliente debe recibir ofertas especiales por primera reserva como miembro
- [ ] **RF52.4** - El proceso de conversión debe ser simple y sin fricción

#### RF53 - Booking Múltiple

- [ ] **RF53.1** - El cliente debe poder reservar múltiples servicios en una sesión
- [ ] **RF53.2** - El sistema debe permitir reservas para múltiples personas
- [ ] **RF53.3** - El sistema debe coordinar automáticamente horarios para servicios consecutivos
- [ ] **RF53.4** - El proceso de pago debe consolidar múltiples reservas

### 3.6 Super Admin/Soporte Técnico

#### RF54 - Soporte Multitenancy

- [ ] **RF54.1** - El soporte debe poder acceder datos de tenant específico con autorización
- [ ] **RF54.2** - El sistema debe permitir impersonar usuarios para debugging
- [ ] **RF54.3** - Todas las acciones de soporte deben quedar registradas en logs
- [ ] **RF54.4** - El acceso debe estar limitado por tiempo y alcance

#### RF55 - Troubleshooting Tools

- [ ] **RF55.1** - El sistema debe proporcionar panel de diagnóstico de problemas comunes
- [ ] **RF55.2** - El soporte debe poder limpiar datos inconsistentes con aprobación
- [ ] **RF55.3** - El sistema debe permitir reenvío de notificaciones fallidas
- [ ] **RF55.4** - Debe haber herramientas para verificar integridad de datos

### 3.7 Desarrollador/Integrador de Terceros

#### RF56 - Developer Portal

- [ ] **RF56.1** - El sistema debe proporcionar documentación interactiva de APIs
- [ ] **RF56.2** - Los desarrolladores deben tener acceso a sandbox environment
- [ ] **RF56.3** - El sistema debe permitir gestión de API keys y webhooks
- [ ] **RF56.4** - Debe haber ejemplos de código y SDKs disponibles

#### RF57 - Marketplace de Apps

- [ ] **RF57.1** - El sistema debe mantener directorio de integraciones disponibles
- [ ] **RF57.2** - Debe haber proceso simplificado de instalación de apps terceros
- [ ] **RF57.3** - El sistema debe manejar revenue sharing para partners
- [ ] **RF57.4** - Debe haber proceso de certificación y revisión de apps

### 3.8 Auditor/Compliance Officer

#### RF58 - Audit Trail

- [ ] **RF58.1** - El auditor debe tener acceso completo a logs inmutables
- [ ] **RF58.2** - El sistema debe generar reportes de cumplimiento automatizados
- [ ] **RF58.3** - Debe permitir exportación de datos en formatos estándar
- [ ] **RF58.4** - Los logs deben incluir todos los cambios de datos críticos

#### RF59 - Privacy Management

- [ ] **RF59.1** - El sistema debe proporcionar dashboard de solicitudes GDPR
- [ ] **RF59.2** - Debe haber herramientas de anonimización de datos
- [ ] **RF59.3** - El sistema debe gestionar automáticamente retención de datos
- [ ] **RF59.4** - Debe haber reportes de compliance por jurisdicción

---

## 4. Requisitos No Funcionales

### 4.1 Performance

#### RNF1 - Tiempo de Respuesta

- [ ] **RNF1.1** - Las consultas de disponibilidad deben responder en menos de 500ms (p95)
- [ ] **RNF1.2** - La creación de reservas debe completarse en menos de 2 segundos
- [ ] **RNF1.3** - Los dashboards deben cargar en menos de 3 segundos
- [ ] **RNF1.4** - El widget de reserva debe cargar en menos de 1 segundo

#### RNF2 - Throughput

- [ ] **RNF2.1** - El sistema debe soportar al menos 1000 reservas concurrentes
- [ ] **RNF2.2** - Debe manejar 10,000 consultas de disponibilidad por minuto
- [ ] **RNF2.3** - El sistema debe procesar 500 pagos simultáneos
- [ ] **RNF2.4** - Debe soportar 100,000 usuarios activos concurrentes

#### RNF3 - Escalabilidad

- [ ] **RNF3.1** - La arquitectura debe soportar escalamiento horizontal
- [ ] **RNF3.2** - El sistema debe auto-escalar basado en carga de CPU y memoria
- [ ] **RNF3.3** - Debe poder agregar nuevos tenants sin afectar performance
- [ ] **RNF3.4** - La base de datos escala con Single DB + RLS sin cambiar arquitectura. Prohibido particionamiento/sharding por tenant hasta ADR con demanda real.

### 4.2 Availability y Reliability

#### RNF4 - Disponibilidad

- [ ] **RNF4.1** - El sistema debe tener 99.9% de uptime mensual (SLA)
- [ ] **RNF4.2** - El downtime planificado no debe exceder 4 horas mensuales
- [ ] **RNF4.3** - El sistema debe soportar maintenance mode sin afectar reservas existentes
- [ ] **RNF4.4** - Debe haber failover automático en menos de 5 minutos

#### RNF5 - Recuperación ante Desastres

- [ ] **RNF5.1** - RPO (Recovery Point Objective) debe ser máximo 15 minutos
- [ ] **RNF5.2** - RTO (Recovery Time Objective) debe ser máximo 4 horas
- [ ] **RNF5.3** - Los backups deben probarse automáticamente cada semana
- [ ] **RNF5.4** - Debe haber replicación geográfica de datos críticos

#### RNF6 - Tolerancia a Fallos

- [ ] **RNF6.1** - El sistema debe continuar operando con falla de un componente
- [ ] **RNF6.2** - Los pagos deben tener mecanismo de retry automático
- [ ] **RNF6.3** - Las notificaciones fallidas deben reintentarse hasta 3 veces
- [ ] **RNF6.4** - Debe haber circuit breakers para servicios externos

### 4.3 Security

#### RNF7 - Autenticación y Autorización (openSaaS: identidad dual)

- [ ] **RNF7.1** - El sistema debe soportar autenticación de múltiples factores
- [ ] **RNF7.2** - Los passwords deben cumplir políticas de complejidad configurables
- [ ] **RNF7.3** - Las sesiones deben expirar automáticamente después de inactividad
- [ ] **RNF7.4** - Debe haber control de acceso basado en roles (RBAC)
- [ ] **RNF7.5** - Identidades separadas obligatorias: guard `central` (`CentralUser`) para staff de plataforma, guard `tenant` (`User`) para usuarios del negocio. Prohibido JWT unificado multi-rol y tabla única `users` con `type`.
- [ ] **RNF7.6** - Sesiones en Redis en staging/prod (`SESSION_DRIVER=redis`). Prohibido `file/database` con Octane/Horizon multi-worker.
- [ ] **RNF7.7** - Aislamiento garantizado por PostgreSQL RLS (`tenant_id = app.current_tenant_id()`, `FORCE`), propagado vía `SET LOCAL` transaccional + `TenantContext` `scoped()`. Global Scopes solo como defensa secundaria.
- [ ] **RNF7.8** - Todo Job tenant-aware implementa `TenantAware` + `RehydrateTenantContext`; sin contexto falla explícito, nunca silencioso.
- [ ] **RNF7.9** - Cache aislada por tenant (`tenant:{id}:{key}`); rate-limit incluye `tenant_id` donde aplique.
- [ ] **RNF7.10** - Todo módulo tenant-aware del producto incluye `CrossTenantLeakTest` (lectura/escritura cruzada + reuso de conexión). Sin este test la tarea no está terminada.

### 4.4 Reglas transversales openSaaS (adición normativa)

- [ ] **RNF8.1** - Lógica de negocio solo en Actions (`final readonly class` + `execute(DTO)`); Livewire solo estado de UI; Controllers solo coordinan; Models sin lógica.
- [ ] **RNF8.2** - Comunicación entre módulos solo vía Actions públicas, Contracts (`Platform/Contracts`) o Events (`Platform/Events`). Prohibido `Model::find()` cruzado o SQL cruzado, incluso entre módulos Central.
- [ ] **RNF8.3** - Dinero en `amount_cents int` (no `DECIMAL`/`float`); formato en `Platform/Data`.
- [ ] **RNF8.4** - Notificaciones y emails vía Jobs en cola Redis/Horizon, idempotentes y reintentables; recordatorios con scheduling explícito, no cron disperso.
- [ ] **RNF8.5** - Observabilidad mínima: `tenant_id` + `request_id` en logs, `activity()` en acciones administrativas (impersonation, suspensión, cambios de plan), health checks separados central/tenant.

---

## 5. Reglas de Negocio (producto Reasy — viven en la capa de producto, no en el core)

> Fuente: `02_Documents.md §4.1.2–4.1.3`. Se resumen aquí como reglas testeables; el detalle algorítmico queda en `02`.
>
> **Estado_repo: ⬜ todas (RN1–RN8).** Sin `app/Modules/Product/` no hay máquina de estados, motor de disponibilidad ni políticas de depósito/reembolso implementados. Ver §7.

- [ ] **RN1 - Estados de reserva:** `draft → pending_payment → confirmed → rescheduled | cancelled | no_show | completed | expired`. Transiciones validadas en Action (p. ej. no confirmar una ya cancelada). `pending` del happy-path de `01_PRD.md` equivale a `pending_payment` cuando hay depósito, o a estado pendiente de confirmación del dueño cuando no lo hay.
- [ ] **RN2 - Disponibilidad:** slots = horario semanal del recurso − excepciones (feriados/vacaciones con prioridad) − reservas existentes − buffers (`buffer_before/after`). Cálculo en Action/Query, mostrado en zona horaria del negocio, almacenado en UTC. Cache `tenant:{id}:availability:*` TTL corto (5 min) con invalidación por crear/cancelar.
- [ ] **RN3 - Capacidad:** servicios grupales admiten `capacity > 1`; doble reserva prohibida por lock (`lockForUpdate` / constraint de solape) + validación de solape `resource_id + [start,end)`.
- [ ] **RN4 - Depósitos:** por servicio `none | percentage | fixed` (`service_pricing`). Sin depósito → confirmación directa del negocio; con depósito → `pending_payment` hasta webhook/aprobación vía Billing Core.
- [ ] **RN5 - Cancelación/reembolso:** ventana por servicio; `no_show` no reembolsa; reembolso proporcional por reglas (`min_hours → %`). Todo reembolso pasa por Billing Core (idempotencia por `gateway_event_id`), nunca crédito manual en Action de reservas.
- [ ] **RN6 - Guest:** datos mínimos (nombre + email o teléfono), verificación OTP < 2 min, enlace firmado con expiración para autogestión (ver/cancelar/reprogramar). Conversión a registrado importa historial.
- [ ] **RN7 - Staff:** solo ve y muta citas de sus recursos asignados (Policy + RLS); check-in/out y `no_show/completed` auditan `actor + timestamp`.
- [ ] **RN8 - Zonas horarias:** input del cliente → normalizar a UTC; display siempre en `business.timezone` (campo obligatorio por `BO-02`).

---

## 6. Matriz de Trazabilidad

`business_id` = `tenant_id` lógico. `M` = MVP (`01_PRD.md`), `E` = post-MVP/enterprise (`02`/`03`).

| RF                                                                                                                           | Alcance          | Módulo openSaaS / Producto                                                      | UCs `01`                                                     | Estado                                                                                                  |
| ---------------------------------------------------------------------------------------------------------------------------- | ---------------- | ------------------------------------------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- |
| RF1–RF10 (plataforma: tenants, planes, dashboard, facturación, config, comms, compliance, health, integraciones, predictivo) | Framework        | `Central/Provisioning, Catalog, Billing, Settings, Support, Operations, Growth` | PA-01/02                                                     | ✅/🟡 framework (detalle en §7: RF3/RF4/RF7/RF8 parciales, RF10 inexistente); Reasy no los reimplementa |
| RF11–RF16 (negocio, sedes, servicios, recursos, horarios, empleados)                                                         | Producto         | Extensión Reasy sobre `Tenant/Access + Experience` (scaffolding)                | BO-01/02/05/06/07 hechos en legado; en repo ⬜ salvo RF16 🟡 | M: BO-03/04                                                                                             |
| RF13.3–RF13.4, RF21, RF41, RF50 (depósitos, pagos)                                                                           | Producto+Billing | Billing Core + Actions de reserva; Clave/dLocal, Stripe diferido                | Explícitamente fuera de MVP en `01 §7.2`                     | E (Q1 post-MVP salvo que el MVP active Clave)                                                           |
| RF17–RF18, RF27–RF31, RF35 (panel reservas, calendario staff, check-in)                                                      | Producto         | Extensión Reasy (Booking) + `Tenant/Workspace` (notificaciones)                 | BM-01/02 pendientes                                          | M: BM-01/02                                                                                             |
| RF19, RF36–RF40, RF46–RF53 (clientes, guest OTP, booking múltiple)                                                           | Producto         | Extensión Reasy (Customers/Bookings)                                            | CL-01..04 pendientes                                         | M: CL-01..04                                                                                            |
| RF20, RF23 (dashboard negocio, finanzas)                                                                                     | Producto         | Extensión Reasy Queries/Read Models                                             | —                                                            | E parcial (KPIs básicos en M, fiscal multi-jurisdicción en E)                                           |
| RF22, RF31 (notificaciones, plantillas, timing)                                                                              | Transversal      | `Tenant/Integrations` (SMTP) + Jobs `TenantAware` + colas                       | CL-04                                                        | M: email confirmación/cambio estado; SMS/WhatsApp = E                                                   |
| RF24–RF26, RF32–RF34, RF42–RF45 (inventario, marketing, waitlist, comisiones, tareas, fidelidad, suscripciones)              | Producto         | Extensión Reasy futura                                                          | Fuera de MVP `01 §7.2`                                       | E                                                                                                       |
| RF54–RF59 (soporte, dev portal, auditoría)                                                                                   | Framework        | `Central/Support, Tenant/Compliance, Access` (API keys)                         | —                                                            | Heredado; marketplace/RM = E                                                                            |

**Criterio de corte MVP:** `BO-03/04 + CL-01..04 + BM-01/02` de `01_PRD.md` + `CL-04` por Jobs. Todo lo marcado `E` requiere RF dedicado + ADR antes de entrar al core o a la extensión.

---

## 7. Estado de implementación (verificado en código)

> Rev. 2026-09-11, rama `main`. Método: `grep` de clases/Actions/Livewire/comandos en `app/` + `ls` de módulos y migraciones. `✅` = clase + ruta/test o uso verificado · `🟡` = existe pero incompleto o con deuda auditada · `⬜` = sin código en el repo · `🚫` = diferido del MVP (Billing Core del framework sí existe).
> `app/Modules/Product/` no existe: todo lo marcado Producto es ⬜ salvo scaffolding heredado del core.

### 7.1 Framework — Central/Tenant (RF1–RF10, RF54–RF59)

| RF                     | Estado_repo            | Evidencia en código                                                                                                                                                                                                                                                                                                                                             |
| ---------------------- | ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RF1 tenants            | ✅                     | `Provisioning/Actions/{CreateTenantAction, ProvisionTenantPipeline, PurgeTenantDataAction}`, `TenantList` (filtros search/status/plan/health) + `ManageTenant` (tabs + suspender/purgar), `provisioning:reconcile`; gates `tenants:view` (staff) / `tenants:manage` (global admin) + tests en `TenantManagementTest` (incl. no-exposición de datos de clientes) |
| RF2 planes             | ✅                     | `Catalog/Livewire/ManagePlans`, `Catalog/Application/Services/PlanManager`, `Provisioning/Actions/ChangeTenantPlanAction` (sync tenant↔subscriptions, rechazo inactivos), migración `plans`                                                                                                                                                                     |
| RF3 dashboard          | ✅                     | `Auth/Livewire/Dashboard` (MRR, churn 30d, transacciones, chart dual, `wire:poll.60s`, alertas/health con datos reales); reservas N/A sin producto                                                                                                                                                                                                              |
| RF4 facturación        | 🟡                     | `Billing/Actions/IssueInvoiceAction`, `GenerateInvoicePdf`, `SubscriptionList`, `TenantInvoiceList`; prorrateo manual                                                                                                                                                                                                                                           |
| RF5 config global      | ✅                     | `Settings/Infrastructure/Services/CentralBranding`, `CentralSetting`, `PlatformBranding`                                                                                                                                                                                                                                                                        |
| RF6 broadcasts         | ✅/🟡                  | `Support/Livewire/BroadcastCenter`, `Support/Actions/SendBroadcastAction`, historial ✅; programado/audiencia avanzada 🟡                                                                                                                                                                                                                                       |
| RF7 compliance central | 🟡                     | `activity()` disperso; `Compliance/AuditLogViewer` es tenant; sin UI central de Audit Log                                                                                                                                                                                                                                                                       |
| RF8 health             | 🟡                     | `Operations/Controllers/HealthCheckController` (JSON) ✅; UI monitor ❌; alertas ❌; `maintenance_mode`/`read_only` por tenant ✅                                                                                                                                                                                                                               |
| RF9 integraciones      | 🟡                     | `Access/Livewire/ManageApiKeys`, `GenerateApiKey`, rate limits ✅; portal/webhooks partners ❌                                                                                                                                                                                                                                                                  |
| RF10 predictivo        | ⬜ (E, YAGNI)          | No existe                                                                                                                                                                                                                                                                                                                                                       |
| RF54 soporte           | ✅*                    | `Support/Actions/ImpersonateTenantAction`, `TenantSupportBitacora`, audit (*deuda P0: token en claro)                                                                                                                                                                                                                                                           |
| RF55 troubleshooting   | 🟡                     | Bitácora + health ✅; reenvío notificaciones/limpieza 🟡                                                                                                                                                                                                                                                                                                        |
| RF56 dev portal        | 🟡                     | API keys ✅; docs interactivas/sandbox ❌                                                                                                                                                                                                                                                                                                                       |
| RF57 marketplace       | ⬜                     | No existe                                                                                                                                                                                                                                                                                                                                                       |
| RF58 audit trail       | ✅ tenant / 🟡 central | `AuditLogViewer` + `DataExport` (*deuda OOM en jobs); central sin UI                                                                                                                                                                                                                                                                                            |
| RF59 privacidad        | 🟡                     | Export + `PurgeTenantDataAction` ✅; anonimización/retención auto ❌                                                                                                                                                                                                                                                                                            |

### 7.2 Producto Reasy (RF11–RF53) — ⬜ salvo scaffolding

| RF                     | Estado_repo | Evidencia / nota                                                                                                                                                                                                            |
| ---------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| RF11 negocio/branding  | 🟡          | Scaffolding `Experience/BrandingSettings` ✅; entidad negocio (`timezone/moneda`) ❌                                                                                                                                        |
| RF12 sedes             | ⬜          | No existe                                                                                                                                                                                                                   |
| RF13 servicios         | ⬜          | BO-03 pendiente (= `01`)                                                                                                                                                                                                    |
| RF14 recursos          | ⬜          | BO-04 pendiente (= `01`)                                                                                                                                                                                                    |
| RF15 horarios          | ⬜ en repo  | BO-05/06 "hechos" solo en prototipo legado                                                                                                                                                                                  |
| RF16 empleados         | 🟡          | `Access/Actions/{SendInvitation, UpdateTenantUserRole}`, `Workspace/TeamManagement` (scaffolding)                                                                                                                           |
| RF17–RF18 panel/manual | ⬜          | No existe                                                                                                                                                                                                                   |
| RF19 clientes          | ⬜          | No existe                                                                                                                                                                                                                   |
| RF20 dashboard negocio | 🟡          | `Workspace/UsageOverview` (cuotas) ✅; KPIs ingresos/ocupación ❌                                                                                                                                                           |
| RF21 pagos negocio     | 🚫 diferido | Framework ✅ (`CreateCheckoutSessionAction`, `SubscribeTenantAction`, `ChargeDirectAction`, `GenerateRenewalCheckoutAction`, `BillingScheduler`, `billing:process-recurring`, `billing:reconcile`); depósitos en reserva ❌ |
| RF22 notificaciones    | 🟡          | `Workspace/NotificationCenter` + `Integrations/SmtpSettings` ✅; plantillas/SMS ❌                                                                                                                                          |
| RF23–RF26              | ⬜          | Finanzas/inventario/marketing/waitlist sin código                                                                                                                                                                           |
| RF27–RF30 staff/agenda | ⬜          | Sin agenda de staff                                                                                                                                                                                                         |
| RF31 tiempo real       | 🟡          | `NotificationCenter` ✅; recordatorios programados ❌                                                                                                                                                                       |
| RF32–RF35              | ⬜          | Comisiones/chat/tareas/check-in sin código                                                                                                                                                                                  |
| RF36–RF37 auth/perfil  | 🟡          | Login/MFA/invitaciones ✅ (`EnrollTenantMfa`, `SendInvitation`); SSO corporativo ❌                                                                                                                                         |
| RF38–RF40              | ⬜          | Sin reserva de cliente registrado                                                                                                                                                                                           |
| RF41 pagos cliente     | 🚫 diferido | Framework ✅; producto ❌                                                                                                                                                                                                   |
| RF42–RF45              | ⬜          | Fidelidad/preferencias/social/suscripciones sin código                                                                                                                                                                      |
| RF46–RF49 guest        | ⬜          | `Growth/RegisterTenant` es alta de tenants, no guest del negocio; OTP ❌                                                                                                                                                    |
| RF50 pago guest        | 🚫 diferido | Framework ✅; producto ❌                                                                                                                                                                                                   |
| RF51–RF53              | ⬜          | Enlace firmado/conversión/múltiple sin código                                                                                                                                                                               |

### 7.3 RNF y RN

- [ ] **RNF1–RNF2:** ⬜ objetivos sin benchmark en el repo.
- [ ] **RNF3:** ✅ (monolito + Single DB + RLS, sin sharding).
- [ ] **RNF4:** 🟡 (health ✅, `maintenance_mode` ✅; failover = infra, fuera del repo).
- [ ] **RNF5:** ⬜/🟡 (backups/replicación = infra, fuera del repo).
- [ ] **RNF6:** 🟡 (retry en colas + `failed_attempts`/dunning ✅; circuit breakers ❌).
- [ ] **RNF7.1–7.4:** ✅ (Fortify/MFA/RBAC en `Access` + `Auth`).
- [ ] **RNF7.5–7.10, RNF8:** ✅ normativa (guards separados, Redis sessions, RLS + `TenantAware`, caché `tenant:{id}:*`, `CrossTenantLeakTest` con grupo `RLSEnforce` en `BillingRlsEnforceTest`).
- [ ] **RN1–RN8:** ⬜ todas (sin `app/Modules/Product/`).
