Tengo el panorama completo. Analicemos la distribución de cada módulo como Bounded Context desde DDD.
Análisis de Bounded Contexts — LaraShift
Context Map General
┌─────────────────────────────────────────────────────────┐
│ PLATFORM │
│ (Infraestructura Transversal — sin lógica de negocio) │
│ Contracts, Data, Events, Tenancy, Security, UI, etc. │
└─────────────────────────────────────────────────────────┘
▲ ▲
│ depende │ depende
┌─────────┴──────────┐ ┌──────────┴──────────────┐
│ CENTRAL │ │ TENANT │
│ (Operación SaaS) │ │ (Producto del cliente) │
│ Auth, Billing, │ │ Access, Workspace, │
│ Catalog, Marketing │ │ Experience, Integrations │
│ Provisioning, Op's │ │ Audit (Compliance) │
│ Support │ │ │
└────────────────────┘ └─────────────────────────┘
🔴 Hallazgos críticos (boundary leaks)

1. Plan existe en DOS Bounded Contexts distintos
   Ubicación Propósito
   Central/Catalog/Domain/Models/Plan.php Define features, quotas, precio — source of truth
   Central/Billing/Domain/Models/Plan.php Representa el plan para suscripciones Cashier
   Problema: Un mismo concepto de negocio ("Plan") vive en dos contextos con modelos diferentes. Catalog's Plan define qué incluye el plan, Billing's Plan maneja la suscripción. La propuesta asigna la propiedad de Plan a Catalog, y Billing debe referenciarlo como concepto externo, no duplicarlo.
   Riesgo: Inconsistencia si un plan se actualiza en Catalog pero no en Billing.
2. DataExport en Tenant/Access en vez de Tenant/Audit (Compliance)
   ExportTenantData action + ExportTenantDataJob + DataExport Livewire + IdentityExportService están en Tenant/Access.
   Problema: Exportación de datos es una responsabilidad de Compliance/Audit, no de Access (identidad). Access debería ocuparse solo de login, roles, permisos, API keys.
3. TenantSetting como "god object" entre módulos Tenant
   Tenant\Experience\TenantSetting es usado por:
   Módulo Uso
   Tenant/Integrations Leer/guardar SMTP config
   Tenant/Access/EnforceTenantMfa Leer mfa_required
   Tenant/Access/ExportTenantDataJob Exportar settings
   Tenant/Experience Branding, localization
   Problema: TenantSetting es un modelo genérico (key-value) que acopla todos los módulos Tenant a Experience. Cada módulo debería ser dueño de su propia configuración, o usar un mecanismo de settings transversal en Platform.
   🟡 Hallazgos medios (inconsistencias de frontera)
4. Platform/UI, Platform/Security, Platform/Observability están incompletos
   Submódulo Estado Debería tener (según propuesta)
   Platform/UI Solo DesignSystem.php Layouts, componentes Blade, navegación, errores, Livewire base
   Platform/Security Solo HmacSigner.php MFA primitives, API key hashing, rate limiting, encryption, tokens
   Platform/Observability Activity.php + HealthChecker.php Logging, metrics, alerts, correlation IDs, queue tracing
   Riesgo: Las primitives de seguridad y UI quedan distribuidas en Central y Tenant en vez de centralizadas en Platform. Por ejemplo, EnrollTenantMfa (Tenant/Access) y EnrollCentral2FAAction (Central/Auth) duplican lógica MFA que debería vivir en Platform/Security.
5. Central/Marketing debería ser Central/Growth
   Sigue existiendo como "Marketing" en vez del nombre propuesto "Growth". Nominalmente menor pero refleja el alcance: ¿solo marketing o también adquisición, conversión, trials?
6. Central/Operations mezcla responsabilidades dispares
   Responsabilidad ¿Pertenece aquí?
   Railway infrastructure provisioning ✅ Operaciones
   Horizon queue resolution ✅ Operaciones
   Health check ✅ Operaciones
   CentralBranding (branding de plataforma) ❌ Debería estar en un módulo de Settings/Config
   CentralBranding (logo, colores, nombre de plataforma) en Operations/Infrastructure/Support es un leak semántico — branding no es operación.
7. Tenant/Access es sobrecargado — demasiadas responsabilidades
   Actualmente contiene:

- Autenticación (login, 2FA) ✅
- Roles y permisos ✅
- API keys 🤔 (¿access o integraciones?)
- Invitaciones ✅
- Team management ❌ (debe estar en Workspace, ya está movido ✅)
- Data export ❌ (debe estar en Compliance)

8. Tenant/Audit vs Tenant/Compliance
   Solo maneja audit logs. Según la propuesta, Compliance debería incluir:

- ✅ Audit logging (actual)
- ❌ Data export (en Access)
- ❌ Security events reporting
- ❌ Retention policies
  ✅ Aciertos de diseño (límites correctos)
  Módulo Evaluación
  Central/Provisioning Bounded context limpio — creación de tenants, dominios, setup
  Central/Catalog Cohesivo — features, quotas, planes, overrides
  Central/Billing Bien delimitado — suscripciones, pagos, webhooks, invoices
  Central/Support Cohesivo — impersonation, broadcasts, notas de soporte
  Tenant/Experience Cohesivo — branding, localización, landing builder
  Tenant/Integrations Cohesivo — SMTP y futuras integraciones externas
  Tenant/Workspace Bien enfocado — dashboard, notificaciones, team, usage
  Platform/Contracts Correcto — interfaces para desacoplar contexts
  Platform/Events Correcto — integración entre contexts vía eventos
  Platform/Tenancy Bien delimitado — multi-tenancy, RLS, scopes, middleware
  📋 Plan de acción recomendado

# Acción Contexto afectado Prioridad

1 Unificar Plan en Central/Catalog como único source of truth; Central/Billing debe referenciarlo vía ID o contract Central Alta
2 Mover ExportTenantData y DataExport de Tenant/Access a Tenant/Audit (Compliance) Tenant Alta
3 Completar Platform/Security con MFA primitives, API key hashing, rate limiting base Platform Media
4 Expandir Platform/UI con layouts, componentes, navigation base Platform Media
5 Mover CentralBranding desde Operations a un submódulo Settings o Config en Central Central Baja
6 Renombrar Central/Marketing → Central/Growth y Tenant/Audit → Tenant/Compliance Ambos Baja
¿Quieres que abordemos alguno de estos ajustes de límite de contexto?
