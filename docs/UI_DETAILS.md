# UI DETAILS — LaraShift

> Documentación de vistas, componentes Livewire, rutas y funcionalidades expuestas al usuario vía UI.

---

## Central\Auth — Portal de Administración Central

### Login Central (`central-auth::pages.login`)

- **Ruta:** `GET /central/login` → name: `central.login`
- **Contexto → Módulo:** `Central\Auth`
- **Propósito:** Autenticación de administradores centrales (email + password). Soporta redirección a 2FA si el usuario lo tiene habilitado.
- **Actions invocadas:**
  - `Central\Auth\Actions\LoginCentralUserAction::execute(LoginData)` — valida credenciales, retorna `'success'`, `'requires_2fa'` o `'failed'`
  - `Central\Auth\Actions\LoginCentralUserAction::completeLogin(CentralUser, remember)` — completa login post-2FA, regenera sesión, registra `CentralSession`
  - `Central\Auth\Actions\LogoutCentralUserAction::execute()` — (desde la ruta POST `/central/logout`)
- **Layout:** `layouts.auth` → `layouts::auth.split` (2 columnas: cita inspiradora + formulario)
- **Componentes Flux:** `flux:input`, `flux:link`, `flux:label`, `flux:checkbox`, `flux:button`, `flux:card`, `flux:text`
- **Componentes Blade compartidos:** `x-auth-header`, `x-auth-session-status`
- **Notas UI_RULES:**
  - Formulario usa `wire:submit="authenticate"` con validación nativa (`#[Validate]`)
  - El enlace "¿Olvidaste tu contraseña?" usa `wire:navigate` para SPA
  - Muestra error de sesión mediante `x-auth-session-status` y errores puntuales con `flux:card`
  - Si el usuario tiene 2FA, redirige a `central.login.challenge` sin navegación SPA (`redirect()` en lugar de `redirectIntended`)

---

### 2FA Challenge (`central-auth::pages.challenge`)

- **Ruta:** `GET /central/login/challenge` → name: `central.login.challenge`
- **Contexto → Módulo:** `Central\Auth`
- **Propósito:** Verificación de código 2FA (TOTP de 6 dígitos) después de login exitoso con credenciales. Solo accesible si existe `session('login.id')`.
- **Actions invocadas:**
  - `Central\Auth\Actions\LoginCentralUserAction` (resuelto con `app()`) — completa login + `recordSession(CentralUser)`
  - `PragmaRX\Google2FA\Google2FA::verifyKey(secret, code)` — verifica el código TOTP
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:card`, `flux:input`, `flux:button`, `flux:link`
- **Componentes Blade compartidos:** `x-auth-header`
- **Notas UI_RULES:**
  - `mount()` redirige a `central.login` si no hay `login.id` en sesión
  - La verificación usa `DB::transaction` para atomicidad entre login + registro de sesión
  - Tras éxito, redirige a `central.dashboard` con `redirectIntended`

---

### Forgot Password (`central-auth::pages.forgot-password`)

- **Ruta:** `GET /central/forgot-password` → name: `central.password.request`
- **Contexto → Módulo:** `Central\Auth`
- **Propósito:** Enviar enlace de restablecimiento de contraseña al email del admin central.
- **Actions invocadas:**
  - `Password::broker('central_users')->sendResetLink(['email' => $email])` — usa el password broker específico para `CentralUser`
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:input`, `flux:button`, `flux:link`
- **Componentes Blade compartidos:** `x-auth-header`, `x-auth-session-status`
- **Notas UI_RULES:**
  - Usa `Password::broker('central_users')` — broker dedicado, no el default
  - Tras envío exitoso, resetea el campo email y muestra status vía `session()->flash()`
  - Enlace "Volver al login" usa `wire:navigate`

---

### Reset Password (`central-auth::pages.reset-password`)

- **Ruta:** `GET /central/reset-password/{token}` → name: `central.password.reset`
- **Contexto → Módulo:** `Central\Auth`
- **Propósito:** Restablecer la contraseña del admin central usando el token recibido por email.
- **Actions invocadas:**
  - `Password::broker('central_users')->reset([...], closure)` — broker dedicado `central_users`, el closure hace `forceFill` de password + `remember_token`
  - `Central\Auth\Models\CentralUser::save()` — guarda el nuevo password hasheado
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:input`, `flux:button`
- **Componentes Blade compartidos:** `x-auth-header`
- **Notas UI_RULES:**
  - `mount(string $token)` recibe el token como parámetro de ruta; el email se obtiene de `request()->query('email')`
  - Validación: password mínimo 12 caracteres, requiere confirmación (`required|min:12|confirmed`)
  - Tras éxito, redirige a `central.login` con `redirect()` (no SPA)

---

### Dashboard Central (`central-auth::pages.dashboard`)

- **Ruta:** `GET /central/dashboard` → name: `central.dashboard`
- **Contexto → Módulo:** `Central\Auth`
- **Middleware:** `auth:central`, `ValidateCentralSession`
- **Propósito:** Overview del ecosistema SaaS: número de tenants, suscripciones activas, ingresos últimos 30 días, tenants recientes, actividad de plataforma, acciones rápidas.
- **Actions invocadas:** Ninguna directa. Consulta modelos de otros módulos vía Eloquent:
  - `Central\Provisioning\Models\Tenant::count()`
  - `Central\Billing\Domain\Models\Subscription::where('status', 'active')->count()`
  - `Central\Billing\Domain\Models\Invoice::where('status', 'paid')->...sum('amount')`
  - `Platform\Observability\Audit\Activity::latest()->take(10)`
- **Layout:** `layouts.central` → `layouts::central.sidebar` (sidebar con navegación completa)
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:icon`, `flux:badge`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`
- **Componentes Blade compartidos:** `x-central-user-menu` (en sidebar)
- **Notas UI_RULES:**
  - **⚠️ Acceso directo a Models de otros módulos** — `Dashboard.php` importa `Tenant`, `Subscription`, `Invoice` y `Activity` directamente. Esto acopla `Central\Auth` con `Billing`, `Provisioning` y `Platform\Observability`. Debería migrarse a Queries/ReadModels.
  - Las cards de métricas usan grid responsive `grid-cols-1 md:grid-cols-2 lg:grid-cols-4`
  - Los botones de acciones rápidas usan `wire:navigate` para navegación SPA

---

### 2FA Enrollment (`central-auth::livewire.two-factor-enrollment`)

- **Ruta:** `GET /central/settings/2fa` → name: `central.auth.2fa`
- **Contexto → Módulo:** `Central\Auth`
- **Middleware:** `auth:central`, `ValidateCentralSession`
- **Propósito:** Habilitar/deshabilitar autenticación de dos factores (TOTP) para el admin central. Flujo: estado inicial (disabled) → botón "Setup 2FA" → muestra QR → confirmar código → muestra recovery codes.
- **Actions invocadas:**
  - `Central\Auth\Actions\EnrollCentral2FAAction::initiate(CentralUser)` — genera secreto + QR URL
  - `Central\Auth\Actions\EnrollCentral2FAAction::confirm(CentralUser, secret, code)` — verifica código y persiste `Central2FA` con recovery codes
  - `BaconQrCode\Writer::writeString($qrCodeUrl)` — renderiza QR como SVG inline
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:badge`, `flux:input`, `flux:button`
- **Notas UI_RULES:**
  - Tres estados UI manejados con condicionales blade: `$enabled && empty($recoveryCodes)` → muestra "2FA is Enabled"; `$showingQrCode` → muestra QR + input de confirmación; estado default → muestra "2FA is Disabled" + botón Setup
  - El QR se renderiza como SVG inline (`{!! $qrCodeUrl !!}`) — seguro porque BaconQrCode solo produce SVG
  - Recovery codes (8 códigos) se muestran en grid 2-columnas con fuente monoespaciada
  - El precio que se paga: el QR se genera en el servidor (Livewire), no en cliente — podría ser lento con payloads grandes. Considerar generar QR en cliente con librería JS.

---

### Logout (ruta POST)

- **Ruta:** `POST /central/logout` → name: `central.logout`
- **Contexto → Módulo:** `Central\Auth`
- **Middleware:** `auth:central`, `ValidateCentralSession`
- **Propósito:** Cerrar sesión del admin central. Invalida sesión Laravel, revoca `CentralSession` asociada, regenera token CSRF.
- **Actions invocadas:**
  - `Central\Auth\Actions\LogoutCentralUserAction::execute()` — hace `Auth::guard('central')->logout()`, marca `CentralSession` como revocada, invalida sesión
- **Layout:** No aplica (POST redirect)
- **Notas UI_RULES:**
  - Implementado como closure en ruta, no como Livewire component
  - Se accede desde el menú de usuario en la sidebar (`flux:menu.item` con formulario POST)
  - Tras logout redirige a `/` (landing page pública)

---

### Estructura de Layouts usados

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts::auth.split` | `resources/views/layouts/auth/split.blade.php` | Auth | 2 columnas: cita + formulario. Usado por login, challenge, forgot-password, reset-password. |
| `layouts::central.sidebar` | `resources/views/layouts/central/sidebar.blade.php` | Central | Sidebar con nav completa + user menu. Usado por dashboard y 2FA enrollment. |

### Componentes Blade compartidos usados

| Componente | Archivo | Uso |
|-----------|---------|-----|
| `x-auth-header` | `resources/views/components/auth-header.blade.php` | Título + descripción en páginas de auth |
| `x-auth-session-status` | `resources/views/components/auth-session-status.blade.php` | Mensaje flash de status |
| `x-app-logo` | `resources/views/components/app-logo.blade.php` | Logo completo en sidebar |
| `x-app-logo-icon` | `resources/views/components/app-logo-icon.blade.php` | Logo icon en layout auth |
| `x-central-user-menu` | `resources/views/components/central-user-menu.blade.php` | Menú desplegable de usuario en sidebar |

### Flux Components utilizados en Central\Auth

`flux:input`, `flux:button`, `flux:link`, `flux:label`, `flux:checkbox`, `flux:card`, `flux:text`, `flux:heading`, `flux:subheading`, `flux:icon`, `flux:badge`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:sidebar`, `flux:sidebar.header`, `flux:sidebar.nav`, `flux:sidebar.group`, `flux:sidebar.item`, `flux:sidebar.collapse`, `flux:header`, `flux:dropdown`, `flux:menu`, `flux:menu.radio.group`, `flux:menu.item`, `flux:menu.separator`, `flux:profile`, `flux:avatar`, `flux:spacer`, `flux:toast.group`, `flux:toast`, `flux:main`

---

## Central\Billing — Facturación y Planes

### Subscription List (`billing::pages.subscription-list`)

- **Ruta:** `GET /central/billing/subscriptions` → name: `central.billing.subscriptions`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Lista paginada de todos los tenants con suscripciones activas. Muestra plan, estado (ACTIVE / GRACE PERIOD / INACTIVE), próxima facturación. Acciones: ver facturas, sync status, cancelar suscripción.
- **Actions invocadas:** Ninguna directa. Consulta vía Eloquent:
  - `Central\Provisioning\Models\Tenant::has('subscriptions')->with('subscriptions')->...paginate()`
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:dropdown`, `flux:button`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`
- **Notas UI_RULES:**
  - **⚠️ Acceso directo a `Tenant` desde `Central\Billing`** — el `SubscriptionList` importa `Central\Provisioning\Models\Tenant`. Debería usar un Query/ReadModel o un contrato en `Platform`.
  - El método `$tenant->subscription('default')` es de Cashier/Laravel — llamado en la vista, no en el componente.
  - El menú de acciones por fila incluye items no implementados ("Sync Status", "Cancel Subscription") — son solo UI placeholder.
  - Usa Livewire `WithPagination` para paginado.

---

### Plan List (`billing::pages.plan-list`)

- **Ruta:** `GET /central/billing/plans` → name: `central.billing.plans`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Lista completa de planes de suscripción (con soft-deletes). Muestra nombre, slug, precio mensual/anual, estado (ACTIVE / INACTIVE / RETIRED). Modal para ver features del catálogo asignadas y quotas técnicas. Acción de retirar plan (soft delete con confirmación modal).
- **Actions invocadas:**
  - `Central\Billing\Actions\DeletePlan::execute(Plan)` — soft delete del plan, logea actividad
  - `Plan::load('catalogFeatures')` — eager loading de features del catálogo
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:text`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ `Plan` model pertenece a `Central\Catalog`, no a `Central\Billing`** — se importa `Central\Catalog\Domain\Models\Plan`. Esto acopla Billing con Catalog. Los precios se formatean con `Platform\Data\Services\PriceFormatter`.
  - Modal de features usa `wire:click="showFeatures('{{ $plan->id }}')"` para cargar datos bajo demanda.
  - Modal de confirmación de borrado es único por plan (dinámico: `delete-plan-{{ $plan->id }}`).
  - El botón "New Plan" usa `wire:navigate` para navegación SPA.

---

### Manage Plan — Create / Edit (`billing::pages.manage-plan`)

- **Ruta:** `GET /central/billing/plans/create` → name: `central.billing.plans.create` | `GET /central/billing/plans/{plan}/edit` → name: `central.billing.plans.edit`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Formulario de creación/edición de planes de suscripción. Incluye: nombre, slug, precios mensual/anual, estado activo, quotas técnicas (branches, staff, bookings), features de marketing (texto), selección de features del catálogo global (checkboxes agrupados por módulo), y Stripe Price ID opcional. Acción de borrar plan con modal de confirmación.
- **Actions invocadas:**
  - `Central\Billing\Actions\UpsertPlan::execute(PlanData, ?Plan)` — crea o actualiza el plan en transacción. Logea actividad (`plan_created` / `plan_updated`)
  - `Plan::catalogFeatures()->sync($selectedFeatures)` — sincroniza features del catálogo asignadas al plan
  - `Central\Billing\Actions\DeletePlan::execute(Plan)` — si se elimina desde el formulario de edición
- **DTOs:** `Central\Billing\DTOs\PlanData` — name, slug, price_monthly (cents), price_yearly (cents), is_active, features
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:card`, `flux:input`, `flux:checkbox`, `flux:textarea`, `flux:spacer`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`
- **Notas UI_RULES:**
  - **⚠️ `Plan` pertenece a `Central\Catalog`** — misma dependencia cruzada que PlanList.
  - `mount()` recibe un `?Plan $plan = null` con route-model-binding implícito para edición.
  - Los precios se convierten de/a cents en el componente (multiplicación/división por 100).
  - La lista de features del catálogo se filtra por `is_active = true` y se agrupa por `module` en la vista para organizar checkboxes.
  - Tras guardar exitosamente, redirige a `central.billing.plans` con `navigate: true` (SPA).

---

### Global Invoice List (`billing::pages.global-invoice-list`)

- **Ruta:** `GET /central/billing/invoices/global` → name: `central.billing.invoices.global`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Lista paginada global de todas las facturas del sistema. Muestra tenant, número de factura, monto, estado (paid/pending), fecha. Botón de descarga de PDF por factura.
- **Actions invocadas:** Ninguna directa. Consulta vía Eloquent:
  - `Central\Billing\Domain\Models\Invoice::with('tenant')->latest()->paginate(20)`
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:button`
- **Notas UI_RULES:**
  - Usa `$invoice->tenant->name` en la vista — accede a relación desde `Invoice`, que es del mismo módulo. Correcto.
  - Los montos se formatean con `Platform\Data\Services\PriceFormatter`.
  - La descarga de PDF se hace mediante un botón que abre `central.billing.invoices.pdf` en nueva pestaña (`target="_blank"`).

---

### Tenant Invoice List (`billing::pages.tenant-invoice-list`)

- **Ruta:** `GET /central/billing/tenants/{tenant}/invoices` → name: `central.billing.tenant.invoices`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Facturas de un tenant específico desde el panel de admin. Muestra número, período, monto, estado, fecha. Botón de descarga PDF. Navegación con botón "back" a la lista de suscripciones.
- **Actions invocadas:** Ninguna directa. Consulta vía Eloquent:
  - `Central\Billing\Domain\Models\Invoice::where('tenant_id', $tenant->id)->latest()->paginate(10)`
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`
- **Notas UI_RULES:**
  - `mount(Tenant $tenant)` usa route-model-binding para recibir el tenant.
  - El botón "back" regresa a `central.billing.subscriptions` con `wire:navigate`.

---

### Invoice PDF Download (ruta closure)

- **Ruta:** `GET /central/billing/invoices/{invoice}/pdf` → name: `central.billing.invoices.pdf`
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Descargar PDF de factura (pro-forma). Usa DomPDF para renderizar la vista `billing::pdf.invoice-proforma` con datos de factura + branding de plataforma.
- **Actions invocadas:**
  - `Central\Billing\Actions\GenerateInvoicePdf::execute(Invoice)` — genera el PDF con DomPDF, carga branding desde `Central\Operations\Infrastructure\Support\CentralBranding`
  - `Central\Billing\Actions\GenerateInvoicePdf::download(Invoice)` — retorna Response con Content-Disposition attachment
- **Layout:** No aplica (descarga de archivo)
- **Notas UI_RULES:**
  - Implementado como closure en ruta — no es Livewire component
  - Usa `CentralBranding` de `Central\Operations` para personalizar el PDF con nombre, color y logo de plataforma

---

### Manage Billing — Tenant-Facing (`billing::pages.manage-billing`)

- **Ruta:** `GET /billing` → name: `tenant.billing.manage`
- **Contexto → Módulo:** `Central\Billing` (scope tenant)
- **Middleware:** web, tenant, auth
- **Propósito:** Página de gestión de suscripción para el tenant. Muestra plan actual con estado, próximo cobro, método de pago registrado, y lista de facturas recientes. Botones para cambiar plan, editar método de pago, completar pagos pendientes.
- **Actions invocadas:**
  - `Central\Billing\Jobs\SyncTenantInvoicesJob::dispatch($tenant)` — dispara job en background para sincronizar facturas
  - `$tenant->subscription('default')` — método de Cashier/Laravel para obtener suscripción
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:text`, `flux:card`, `flux:badge`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:icon`
- **Notas UI_RULES:**
  - **⚠️ Acceso directo a `Invoice::where('tenant_id', ...)`** desde el render. Usa `Central\Billing\Domain\Models\Invoice` — mismo módulo, correcto, pero el dispatch del job de sync debería idealmente estar en un action separado.
  - No es un action invocable directo — la lógica está inline en el `render()`.
  - El dispatch de `SyncTenantInvoicesJob` ocurre en cada render — podría ser excesivo. Considerar cache o trigger manual.
  - Flujo: tenant con plan free vs plan pago → UI diferente (botón "Pay for Plan" vs información de suscripción).

---

### Select Plan — Tenant-Facing (`billing::pages.select-plan`)

- **Ruta:** `GET /billing/plans` → name: `tenant.billing.plans`
- **Contexto → Módulo:** `Central\Billing` (scope tenant)
- **Middleware:** web, tenant, auth
- **Propósito:** Selección de plan de suscripción para el tenant. Muestra cards comparativas con precio, features display. Detecta si el plan actual está activo o pendiente de pago. Redirige a checkout externo al seleccionar.
- **Actions invocadas:**
  - `Central\Billing\Actions\CreateCheckoutSession::execute(Tenant, planId)` — crea sesión de checkout vía `BillingManager` (delega al driver configurado: Stripe, Clave, dLocal)
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:text`, `flux:card`, `flux:button`, `flux:icon`
- **Notas UI_RULES:**
  - El método `selectPlan()` usa `app(CreateCheckoutSession::class)` — resuelve del contenedor, no inyección en método.
  - Redirige al checkout URL externo con `redirect($checkoutUrl, navigate: false)` — sale del SPA.
  - Valida si el tenant ya tiene el mismo plan activo antes de crear checkout (evita duplicados).
  - El botón muestra texto dinámico según estado: "Selected" / "Proceed to Payment" / "Upgrade Now" / "Select Plan".
  - Loading state con `wire:loading` para el texto del botón ("Redirecting...").

---

### Hosted Checkout — Tenant-Facing (`billing::pages.hosted-checkout`)

- **Ruta:** `GET /billing/checkout/hosted/{tenant_uuid}/{plan_uuid}` → name: `tenant.billing.checkout.hosted`
- **Contexto → Módulo:** `Central\Billing` (scope tenant)
- **Middleware:** web, tenant, auth
- **Propósito:** Página que embeda el componente `payments.checkout` para completar el pago de suscripción. Escucha el evento `payment-approved` para redirigir a la página de éxito y mostrar toast de confirmación.
- **Actions invocadas:**
  - `payments.checkout` — componente Livewire embebido que ejecuta `InitiateCheckout` action
  - Evento `payment-approved` → `handleSuccess()` redirige a `tenant.billing.success`
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:button`
- **Componentes Livewire embebidos:** `<livewire:payments.checkout>`
- **Notas UI_RULES:**
  - `mount(string $tenant_uuid, string $plan_uuid)` resuelve por ruta parámetros (no route-model-binding implícito, explícito con `findOrFail`).
  - El `payment-approved` se escucha con `#[On('payment-approved')]` atributo de Livewire.
  - Tras éxito, redirige a `tenant.billing.success` con `navigate: true` (SPA).

---

### Update Payment Method — Tenant-Facing (`billing::pages.update-payment-method`)

- **Ruta:** `GET /billing/update-payment` → name: `tenant.billing.update-payment`
- **Contexto → Módulo:** `Central\Billing` (scope tenant)
- **Middleware:** web, tenant, auth
- **Propósito:** Actualizar método de pago del tenant. Para Stripe: integra Stripe Elements (card) con SetupIntent para recolectar nueva tarjeta de forma segura. Para otros gateways (Clave, dLocal): muestra mensaje informativo de que la actualización no está disponible online.
- **Actions invocadas:**
  - `$tenant->createSetupIntent()` — crea SetupIntent de Stripe para recolectar método de pago
  - `$tenant->updateDefaultPaymentMethod($paymentMethod)` — actualiza el método de pago por defecto en Stripe
  - Evento `paymentMethodUpdated` → `updatePaymentMethod(string $paymentMethod)` — callback desde JS
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:label`, `flux:input`, `flux:button`, `flux:icon`
- **Notas UI_RULES:**
  - **⚠️ Stripe JS integrado directamente en la vista** — el script de Stripe Elements está inline en el blade (no hay componente JS externo). El `client_secret` se pasa como data attribute.
  - La comunicación JS → Livewire es mediante `Livewire.dispatch('paymentMethodUpdated', { paymentMethod: ... })`.
  - Tras éxito, redirige a `tenant.billing.manage` con `navigate: true`.
  - Para gateways no-Stripe, muestra un estado "Online Update Unavailable" con instrucciones de contacto.

---

### Billing Success & Cancel — Tenant-Facing (`billing::pages.success`, `billing::pages.cancel`)

- **Rutas:**
  - `GET /billing/success` → name: `tenant.billing.success`
  - `GET /billing/cancel` → name: `tenant.billing.cancel`
- **Contexto → Módulo:** `Central\Billing` (scope tenant)
- **Middleware:** web, tenant, auth
- **Propósito:** Páginas estáticas de resultado del proceso de checkout. Success: icono check, mensaje de agradecimiento, botones "Go to Dashboard" y "View Billing Details". Cancel: icono X, mensaje de cancelación, botones "Try Again" y "Back to Billing".
- **Actions invocadas:** Ninguna.
- **Layout:** `layouts.app` → `layouts::app.sidebar` (usando `x-layouts::app` directamente en el blade)
- **Componentes Flux:** `flux:icon`, `flux:heading`, `flux:text`, `flux:button`
- **Notas UI_RULES:**
  - Implementadas como closures en `tenant.php` — no son Livewire components, solo vistas.
  - Usan `x-layouts::app` directamente en lugar de `#[Layout]` atributo.
  - Ambas usan `wire:navigate` para los botones de navegación.

---

### CheckoutComponent — Widget Reutilizable (`payments::livewire.checkout-component`)

- **Ruta:** No tiene ruta directa. Se embebe como `<livewire:payments.checkout>` con props.
- **Contexto → Módulo:** `Central\Billing`
- **Propósito:** Widget de pago reutilizable multi-gateway (Clave, dLocal). Props: amount, taxAmount, discount, displayId, email, lang, customFieldValues. States: inicial → botón "Pay Now", loading → spinner + "Redirecting", checkout URL → auto-redirect + fallback button, completed → icono check. Maneja errores de gateway con toast.
- **Actions invocadas:**
  - `Central\Billing\Actions\InitiateCheckout::execute(PaymentData, tenantId, apiKey)` — inicia sesión de checkout en el gateway configurado
- **Layout:** No aplica (componente embebido sin layout propio)
- **Componentes Flux:** `flux:icon`, `flux:heading`, `flux:text`, `flux:button`, `flux:icon.lock-closed`
- **Notas UI_RULES:**
  - Usa `x-data` de Alpine.js para manejo de estado reactivo: loading, checkoutUrl, completed, error.
  - Props marcadas con `#[Locked]` para que no puedan ser modificadas desde el cliente.
  - Dispara eventos Livewire: `checkout-ready` (cuando URL está lista), `payment-approved`, `payment-declined`, `toast`.
  - Auto-redirect con JS: escucha `checkout-ready`, espera 1s, hace `window.location.href = event.url`.
  - El gateway se resuelve desde `tenant('billing_gateway')` con fallback a config.
  - Múltiples formatos de pago manejados: Clave, dLocal, y extensible.

---

### Billing Admin API — Endpoints JSON (`BillingApiController`)

- **Rutas:**
  - `GET /central/plans` → `listPlans()` — lista todos los planes vía `PlanManager::all()`
  - `POST /central/billing/checkout` → `checkout()` — crea checkout session para un tenant+plan
  - `GET /central/billing/subscriptions/{tenant_id}` → `subscriptionStatus()` — estado de suscripción del tenant
  - `POST /central/billing/subscriptions/{id}/cancel` → `cancelSubscription()` — cancela suscripción
  - `GET /central/billing/invoices` → `listInvoices()` — lista facturas (filtrable por tenant_id)
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** `web`, `auth:central` (autenticación de admin central)
- **Propósito:** API JSON para integración con herramientas externas o peticiones AJAX del admin. No expone UI directa.
- **Actions invocadas:**
  - `Central\Billing\Actions\CreateCheckoutSession::execute(Tenant, planId)` — delega en `BillingManager`
  - `Central\Billing\Actions\CancelSubscription::execute(Tenant, subscriptionId, immediately)` — cancela y dispatches `SubscriptionCancelled` event
  - `BillingManager::all()` — obtiene todos los planes
- **Notas UI_RULES:**
  - **⚠️ Acceso directo a `Tenant` (Provisioning) e `Invoice` (Billing Domain)** — la creación de `Tenant::findOrFail()` está en el controller.
  - Controlador extiende `Platform\Foundation\Http\Controllers\Controller`.
  - No usa Livewire, son controllers HTTP tradicionales con respuesta JSON.

---

### Webhooks & Callbacks Públicos

- **Rutas:**
  - `POST /central/webhooks/stripe` → `StripeWebhookController` — webhook de Stripe
  - `GET /central/billing/paguelofacil/callback` → `PaguelofacilCallbackController::handleReturn()` — callback browser redirect de PagueloFacil
  - `POST /webhooks/clave` → `WebhookController::handle()` — webhook de Clave (sin auth, sin tenant middleware)
  - `POST /webhooks/dlocal` → `WebhookController::handle()` — webhook de dLocal (sin auth, sin tenant middleware)
  - `POST /payments/checkout/initiate` → `CheckoutController::initiate()` — inicio de checkout (tenant-scoped)
- **Contexto → Módulo:** `Central\Billing`
- **Middleware:** Los webhooks usan `throttle:webhooks` y están fuera del stack de tenant/auth. El callback de PagueloFacil es público (browser redirect). El checkout initiate usa middleware estándar `web, tenant, auth, verified`.
- **Propósito:** Puntos de entrada para integración con pasarelas de pago externas. Procesan notificaciones de pago, redirecciones post-pago, e inician sesiones de checkout.
- **Actions invocadas:** Delegan en los gateways específicos (Stripe, Clave, dLocal, PagueloFacil).
- **Notas UI_RULES:**
  - Los webhooks de Clave y dLocal usan `withoutMiddleware(['web', 'auth', 'tenant'])` para evitar problemas de sesión.
  - Rate limiting específico: 60 requests por minuto por IP para webhooks.
  - El callback de PagueloFacil es un browser redirect GET — maneja el retorno del usuario después del pago.

---

### Estructura de Layouts usados en Central\Billing

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.central` | `resources/views/layouts/central/sidebar.blade.php` | Central Admin | Sidebar con nav de administración. Usado por SubscriptionList, PlanList, ManagePlan, GlobalInvoiceList, TenantInvoiceList. |
| `layouts.app` | `resources/views/layouts/app/sidebar.blade.php` | Tenant | Sidebar de tenant. Usado por ManageBilling, SelectPlan, HostedCheckout, UpdatePaymentMethod, Success, Cancel. |

### Flux Components utilizados en Central\Billing

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:button`, `flux:input`, `flux:checkbox`, `flux:textarea`, `flux:label`, `flux:icon`, `flux:badge`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`, `flux:icon.lock-closed`, `flux:toast.group`, `flux:toast`

### Incidencias de Arquitectura detectadas en Central\Billing

| Vista | Problema | Impacto |
|-------|----------|---------|
| `SubscriptionList` | Importa `Central\Provisioning\Models\Tenant` directamente | Acopla Billing → Provisioning. Usar `TenantContract` de `Platform\Contracts`. |
| `PlanList` / `ManagePlan` | `Plan` model pertenece a `Central\Catalog\Domain\Models\Plan` | Acopla Billing → Catalog. El modelo Plan debería estar en Billing o moverse a Platform. |
| `ManageBilling` (render) | `Invoice::where('tenant_id', ...)` inline + dispatch de Job en render | Lógica de negocio en la capa de presentación. Extraer a un Query object. |

---

## Central\Catalog — Catálogo de Features (Feature Flags)

### Feature List (`features::pages.feature-list`)

- **Ruta:** `GET /central/features` → name: `central.features.index`
- **Contexto → Módulo:** `Central\Catalog`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Lista paginada de todas las feature flags del catálogo global. Muestra key (formato `module.action`), nombre, módulo, estado (ACTIVE / INACTIVE). Acciones: editar feature. El borrado desde la UI está deshabilitado (solo soft-delete desde backend).
- **Actions invocadas:** Ninguna. Consulta directa vía Eloquent:
  - `Central\Catalog\Domain\Models\Feature::orderBy('module')->orderBy('key')->paginate(20)`
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:text`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`
- **Notas UI_RULES:**
  - La ruta NO está en un archivo Routes separado — se define inline en `CatalogServiceProvider::boot()` dentro de un `Route::middleware(...)->group()`.
  - El key se muestra en fuente monoespaciada (`font-mono text-xs`) para distinguir el identificador técnico.
  - El módulo se muestra como badge outline con texto uppercase.
  - El botón "Delete" en el menú de acciones está deshabilitado (`disabled`) — no hay acción de borrado desde UI, solo soft-delete programático.
  - Usa Livewire `WithPagination`.

---

### Manage Feature — Create / Edit (`features::pages.manage-feature`)

- **Ruta:** `GET /central/features/create` → name: `central.features.create` | `GET /central/features/{feature}/edit` → name: `central.features.edit`
- **Contexto → Módulo:** `Central\Catalog`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Formulario de creación/edición de feature flags del catálogo. Campos: key técnico (formato `module.action`, con auto-slugging), nombre visible, módulo/categoría, descripción, estado activo. Acción de borrado con modal de confirmación (soft-delete).
- **Actions invocadas:** Ninguna. Operaciones CRUD directas sobre el modelo:
  - `Feature::create($attributes)` / `$feature->update($attributes)` — create/update inline
  - `$feature->delete()` — soft-delete inline
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:card`, `flux:input`, `flux:textarea`, `flux:checkbox`, `flux:spacer`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`
- **Notas UI_RULES:**
  - **CRUD directo sin Action class** — el `save()` y `delete()` operan directamente sobre el Modelo Eloquent. No hay una capa Application/Actions intermedia. Esto es aceptable para CRUD simple pero rompe el patrón de acciones invocables.
  - `updatedKey($value)` hace auto-transformación a slug con puntos: `Str::lower(Str::slug($value, '.'))` — fuerza el formato `module.action`.
  - Validación de key con regex: `regex:/^[a-z0-9]+\.[a-z0-9_]+$/`.
  - Tras guardar, redirige a `central.features.index` con `navigate: true` (SPA).

---

### Tenant Overrides (`features::pages.tenant-overrides`)

- **Ruta:** `GET /central/tenants/{tenant}/features/overrides` → name: `central.tenants.features.overrides`
- **Contexto → Módulo:** `Central\Catalog`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Gestión de overrides de features por tenant. Panel dividido en dos columnas:
  - **Izquierda:** formulario para aplicar nuevo override (seleccionar feature, tipo allow/deny, expiración opcional, razón). Muestra el set efectivo de features resultante.
  - **Derecha:** tabla de overrides activos con feature, tipo (allow/deny), expiración, razón, y botón de eliminar.
- **Actions invocadas:**
  - `Central\Catalog\Actions\ApplyTenantFeatureOverride::execute(Tenant, featureKey, type, reason, expiresAt)` — aplica override en transacción, invalida cache de features, logea actividad. Requiere gate `features:manage`.
  - `Central\Catalog\Actions\ResolveTenantFeatures::execute(Tenant, forceRefresh)` — resuelve y cachea el set efectivo de features del tenant (jerarquía: Plan Base + Overrides Allow - Overrides Deny)
- **DTOs:** `Central\Catalog\DTOs\TenantSummaryData` — id, name, slug, email, plan_id, status
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:text`, `flux:card`, `flux:select`, `flux:radio.group`, `flux:radio`, `flux:input`, `flux:textarea`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`
- **Notas UI_RULES:**
  - **⚠️ `TenantOverrides` importa `Central\Provisioning\Models\Tenant` directamente** — en `mount()`, `applyOverride()` y `removeOverride()` se resuelve `Tenant::findOrFail()`. Esto acopla Catalog → Provisioning. Debería usar `TenantContract`.
  - Usa `TenantSummaryData` DTO para pasar solo los datos necesarios del tenant a la vista (evita pasar el modelo completo).
  - El override se aplica con validación: feature debe existir (`exists:features,key`), tipo `allow|deny`, expiración futura si se especifica.
  - `removeOverride()` hace soft-delete del override + refresca caché vía `ResolveTenantFeatures::execute($tenant, true)`.
  - El `render()` vuelve a buscar el tenant y overrides — esto podría ser ineficiente y debería cachearse.
  - El set efectivo de features se muestra como badges en un card informativo debajo del formulario.

---

### Estructura de Layouts usados en Central\Catalog

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.central` | `resources/views/layouts/central/sidebar.blade.php` | Central Admin | Sidebar con nav de administración. Usado por FeatureList, ManageFeature, TenantOverrides. |

### Flux Components utilizados en Central\Catalog

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:button`, `flux:input`, `flux:checkbox`, `flux:textarea`, `flux:select`, `flux:radio.group`, `flux:radio`, `flux:badge`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`

### Incidencias de Arquitectura detectadas en Central\Catalog

| Vista | Problema | Impacto |
|-------|----------|---------|
| `FeatureList` | CRUD directo sin Action class (solo consulta) | Aceptable para solo-lectura. Correcto. |
| `ManageFeature` | `save()` y `delete()` operan directo sobre `Feature` model | Sin capa Application/Actions. Para CRUD simple es aceptable, pero rompe el estándar de Actions invocables. |
| `TenantOverrides` | Importa `Central\Provisioning\Models\Tenant` directamente | Acopla Catalog → Provisioning. Usar `TenantContract` de `Platform\Contracts`. |
| `TenantOverrides` (render) | `Tenant::findOrFail()` + queries cada render | Ineficiente. Cachear la consulta de overrides o usar computed properties de Livewire. |

### Solapamiento de modelos `Plan`

Existen **dos modelos `Plan`** en la base de código:
- `Central\Catalog\Domain\Models\Plan` — en el módulo Catalog (usado para relaciones con Features)
- `Central\Catalog\Domain\Models\Plan` — en el módulo Catalog (único source of truth para planes)

Esto es confuso y propenso a errores de importación. **Cada vista de Billing usa `Central\Catalog\Domain\Models\Plan`** (PlanList, ManagePlan, SelectPlan, HostedCheckout), no el de Billing. Unificar en un solo modelo dentro de `Platform` o `Billing` eliminaría la ambigüedad.

---

## Central\Marketing — Landing Pública y Registro de Tenants

### Landing Page (`marketing::pages.landing-page`)

- **Ruta:** `GET /` → name: `home` (catch-all) y `home.{domain}` (por cada `tenancy.central_domains`)
- **Contexto → Módulo:** `Central\Marketing` (scope público)
- **Middleware:** Ninguno (público)
- **Propósito:** Página de marketing pública del SaaS. Muestra hero con CTA, sección de features del producto, y pricing cards con todos los planes activos. Es la puerta de entrada al registro de nuevos tenants.
- **Actions invocadas:**
  - `Central\Billing\Gateways\PlanManager::all()` — obtiene todos los planes activos (no soft-deleted)
  - `Central\Operations\Support\CentralBranding::platformName()`, `::primaryColor()`, `::logoUrl()` — branding configurable de plataforma
- **Layout:** `layouts.marketing` (layout mínimo: solo head + slot + toast)
- **Componentes Flux:** `flux:button`, `flux:heading`, `flux:subheading`, `flux:text`, `flux:card`, `flux:icon`, `flux:toast.group`, `flux:toast`
- **Notas UI_RULES:**
  - **⚠️ Acceso a `PlanManager` (Billing) y `CentralBranding` (Operations)** — `LandingPage` importa de otros módulos. `PlanManager` es un service de infraestructura de Billing; `CentralBranding` es de Operations. Esto acopla Marketing → Billing + Operations. Podría beneficiarse de un contrato `Platform\Contracts`.
  - Layout `layouts.marketing` es un wrapper HTML sin sidebar ni navegación estructurada — es full page marketing.
  - El pricing section usa `$plan->features['display_features']` y `$plan->features['quotas']` — estructura de datos legacy del modelo `Plan`.
  - Los colores del branding se aplican inline con `style="color: {{ $primaryColor }}"` en múltiples elementos (hero, pricing badges, icon backgrounds).
  - El botón CTA "Get Started" linkea a `/register` con query param `?plan={{ $plan->slug }}` para pre-seleccionar plan.
  - Se registra la ruta tanto para cada `central_domains` individual como catch-all. Doble registro intencional.

---

### Register Tenant — Wizard Multi-Step (`marketing::pages.register-tenant`)

- **Ruta:** `GET /register` → name: `central.register` | `register.{domain}`
- **Contexto → Módulo:** `Central\Marketing` (scope público, con rate limiting)
- **Middleware:** `throttle:5,1` (5 requests por minuto)
- **Propósito:** Wizard de auto-registro de tenants en 3 pasos:
  1. **Datos de organización** — nombre, email, compañía, workspace URL (slug), password
  2. **Selección de plan** — cards visuales con features, quotas y precios
  3. **Confirmación** — resumen del pedido, redirección a pago o login

  Para planes gratuitos: redirige directamente al login del nuevo workspace.
  Para planes pagos: crea checkout session y redirige al gateway.
- **Actions invocadas:**
  - `Central\Provisioning\Actions\CreateTenantAction::execute(CreateTenantData)` — crea el tenant con provisioning completo (dominio, DB, infraestructura, admin user, billing setup)
  - `Central\Billing\Gateways\PlanManager::all()` — lista planes activos para step 2
  - `Central\Billing\Gateways\BillingManager::createCheckoutSession(Tenant, planId)` — crea checkout URL para redirección a gateway de pago
  - `Central\Catalog\Domain\Models\Plan::where('slug', ...)` — validación de existencia y precio del plan seleccionado
- **DTOs:** `Central\Provisioning\DTOs\CreateTenantData` — name, slug, email, plan_id, password, payment_token
- **Layout:** `layouts.marketing` (layout mínimo)
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:input`, `flux:button`, `flux:card`, `flux:icon`, `flux:toast.group`, `flux:toast`
- **Notas UI_RULES:**
  - **⚠️ Dependencias multi-módulo pesadas** — el componente importa de:
    - `Central\Catalog\Domain\Models\Plan` (acopla Marketing → Catalog)
    - `Central\Billing\Infrastructure\Gateways\PlanManager` y `BillingManager` (acopla Marketing → Billing)
    - `Central\Provisioning\Actions\CreateTenantAction` y `DTOs\CreateTenantData` (acopla Marketing → Provisioning)
    - `Central\Provisioning\Support\ReservedSlugs` (acopla Marketing → Provisioning)
  - Rate limiting: 5 requests por minuto — protege contra fuerza bruta en registro.
  - **Race condition en slug**: documentada en el código. Validación `unique:tenants,slug` con constraint DB unique como mitigación.
  - Auto-generación de slug desde company name con `updatedCompany()` + flag `autoGenerateSlug`. Si el usuario edita manualmente el slug, se desactiva el auto-generado.
  - Step indicator visual con círculos numerados + líneas de progreso entre pasos. El ancho del wizard cambia dinámicamente: `sm:max-w-5xl` para step 2 (cards de plan), `sm:max-w-xl` para steps 1 y 3.
  - `mount()` lee query param `plan` para pre-seleccionar plan desde la landing page.
  - Plan gratuito vs pago: flujo bifurcado en `register()`. Si es pago, redirige a checkout URL externo (`navigate: false`). Si es gratis, redirige al login del tenant.
  - `isPlanFree()` usa `$plan->price_monthly->isPositive()` — verifica que el monto sea positivo.

---

### Estructura de Layouts usados en Central\Marketing

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.marketing` | `resources/views/layouts/marketing.blade.php` | Público | Layout mínimo (sin sidebar). Solo HTML head + slot + toast. Usado por LandingPage y RegisterTenant. |

### Flux Components utilizados en Central\Marketing

`flux:button`, `flux:heading`, `flux:subheading`, `flux:text`, `flux:card`, `flux:icon`, `flux:input`, `flux:toast.group`, `flux:toast`

### Incidencias de Arquitectura detectadas en Central\Marketing

| Vista | Problema | Impacto |
|-------|----------|---------|
| `LandingPage` | Importa `PlanManager` (Billing) y `CentralBranding` (Operations) | Acopla Marketing → Billing + Operations. Crear contratos en `Platform\Contracts`. |
| `RegisterTenant` | Importa de 4 módulos distintos (Catalog, Billing, Provisioning, Provisioning/Support) | Alto acoplamiento. El wizard de registro debería orquestarse vía un solo Application Service o Job que Central\Marketing exponga. |
| `RegisterTenant` | `PlanManager::all()` llamado tanto en `register()` como en `render()` | Duplicación de consulta. Podría cachearse o usarse una computed property. |
| `RegisterTenant` | Llamada directa a `BillingManager::createCheckoutSession()` inline en `register()` | Lógica de negocio de pago en la capa de presentación. Delegar a un Action. |
| `RegisterTenant` | `Plan::where('slug', ...)->first()` repetido en `mount()`, `isPlanFree()`, `getSelectedPlanProperty()` | Sin repositorio ni cache. Tres queries potenciales por render. |

---

## Central\Operations — Operaciones de Plataforma (Branding, Health)

### Platform Branding (`settings::pages.platform-branding`)

- **Ruta:** `GET /central/settings/branding` → name: `central.settings.branding`
- **Contexto → Módulo:** `Central\Operations`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Configuración de branding global de la plataforma SaaS. Permite personalizar: nombre de la plataforma, color primario (selector de color), y URL del logo. Incluye un preview en vivo del PDF de factura pro-forma para visualizar cómo se verán los cambios en documentos generados. Los valores se cachean con `rememberForever` y se invalidan al guardar.
- **Actions invocadas:**
  - `Central\Operations\Support\CentralBranding::set('platform_name', $value)` — persiste y cachea el nombre
  - `Central\Operations\Support\CentralBranding::set('primary_color', $value)` — persiste y cachea el color
  - `Central\Operations\Support\CentralBranding::set('logo_url', $value)` — persiste y cachea la URL del logo
- **DTOs / Models internos:** `Central\Operations\Domain\Models\CentralSetting` — modelo key-value con `key` como primary key y cast `type` (string, bool, int, json)
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:input`, `flux:button`, `flux:text`
- **Notas UI_RULES:**
  - El componente usa `CentralBranding` directamente (service del mismo módulo). Correcto — no hay dependencias externas.
  - `mount()` carga valores actuales desde `CentralBranding::` (cacheados). Usa `?? ''` para `logoUrl` porque puede ser null.
  - Validación de `primaryColor` usa regla `hex_color` de Laravel (validación nativa desde v9).
  - La vista incluye un **preview de PDF inline** con estilos visuales (bordes, fondo, tipografía) que replican la plantilla `billing::pdf.invoice-proforma`. El preview se actualiza en tiempo real con `wire:model`.
  - `CentralBranding::set()` invalida la cache específica de cada key (`Cache::forget("central_setting_{$key}")`).
  - La ruta NO está en el archivo Routes separado — se define inline en `OperationsServiceProvider::boot()` dentro de un `booted()` callback.

---

### Health Check (`endpoint JSON, sin UI`)

- **Ruta:** `GET /central/health` → name: `central.health`
- **Contexto → Módulo:** `Central\Operations`
- **Middleware:** `web`, `auth:central`. Opcional: IP restriction vía `config('infrastructure.health.allowed_ips')`.
- **Propósito:** Endpoint JSON de monitoreo de salud del sistema. Verifica: conexión a base de datos (PDO), conexión a Redis (ping), tamaño de cola de jobs (warn si > 1000). Retorna status general (`healthy` / `degraded`) con timestamp ISO 8601. HTTP 200 si healthy, 503 si degraded.
- **Actions invocadas:** Ninguna. Llamadas directas a fachadas de Laravel:
  - `DB::connection()->getPdo()` — verifica conexión a DB
  - `Redis::connection()->ping()` — verifica conexión a Redis
  - `Queue::size()` — verifica profundidad de cola
- **Layout:** No aplica (JSON response)
- **Notas UI_RULES:**
  - **⚠️ NO es un Livewire component** — es un Controller invocable tradicional que devuelve `JsonResponse`.
  - Implementado como `__invoke()` en `HealthCheckController`.
  - El endpoint soporta IP restriction opcional: si `allowed_ips` está configurado y la IP del request no está en la lista, retorna 403.
  - Verifica que la extensión `phpredis` exista antes de intentar el ping para evitar fatales.
  - Se accede desde la sidebar de Central como link externo (`target="_blank"` en `flux:sidebar.item`).
  - Status code: 200 si healthy, 503 si degraded.

---

### Estructura de Layouts usados en Central\Operations

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.central` | `resources/views/layouts/central/sidebar.blade.php` | Central Admin | Sidebar con nav de administración. Usado por PlatformBranding. |

### Flux Components utilizados en Central\Operations

`flux:heading`, `flux:subheading`, `flux:card`, `flux:input`, `flux:button`, `flux:text`

### Incidencias de Arquitectura detectadas en Central\Operations

| Vista | Problema | Impacto |
|-------|----------|---------|
| `PlatformBranding` | Sin incidencias. Usa solo modelos y services de su propio módulo (`CentralSetting`, `CentralBranding`). | Correcto. Ejemplo de buena separación. |
| `HealthCheckController` | Usa fachadas de Laravel directamente (`DB`, `Redis`, `Queue`). | Aceptable para un health check. Podría beneficiarse de contracts `Platform\Contracts\HealthCheckerInterface` para testabilidad. |

---

## Central\Provisioning — Gestión de Tenants (Provisioning)

### Tenant List (`provisioning::pages.tenant-list`)

- **Ruta:** `GET /central/tenants` → name: `central.provisioning.index`
- **Contexto → Módulo:** `Central\Provisioning`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Lista paginada de todos los tenants de la plataforma. Muestra nombre, email, dominio, estado (con badge de read-only), plan, fecha de creación. Acciones por fila: gestionar features, editar, impersonar (con modal + razón auditada), y hard-delete (con confirmación de slug + purge en background job).
- **Actions invocadas:**
  - `Central\Provisioning\Actions\DeleteTenantAction::execute(Tenant, hardDelete: true)` — dispatches `PurgeTenantJob` en background para purga completa
  - `Central\Support\Actions\ImpersonateTenantAction::execute(Tenant, reason)` — crea `SupportSession` con token one-time y retorna URL de redirección al tenant domain
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:text`, `flux:card`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:link`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:textarea`, `flux:input`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ `ImpersonateTenantAction` pertenece a `Central\Support`** — el TenantList invoca una acción de otro módulo. Esto es aceptable porque Support expone la acción como servicio, pero idealmente debería haber un contrato en `Platform\Contracts`.
  - Dos modales: **Impersonate** (con textarea para razón, mínimo 20 caracteres, acción auditada) y **Delete** (con input de confirmación de slug para evitar borrados accidentales).
  - El modal de impersonación requiere razón obligatoria de ≥20 caracteres — validación tanto en frontend como en backend (`ImpersonateTenantAction`).
  - Hard-delete con purge en background job (`PurgeTenantJob`) — no bloquea el request.
  - `selectTenant()` carga el tenant en `$selectedTenantId` para que los modales tengan contexto.
  - Usa Livewire `WithPagination`.

---

### Create Tenant (`provisioning::pages.create-tenant`)

- **Ruta:** `GET /central/tenants/create` → name: `central.provisioning.create`
- **Contexto → Módulo:** `Central\Provisioning`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Formulario de creación manual de un nuevo tenant desde el panel de admin. Campos: company name, subdomain/slug, owner email, plan (select estático: free/pro/enterprise). Ejecuta provisioning completo atómico al guardar.
- **Actions invocadas:**
  - `Central\Provisioning\Actions\CreateTenantAction::execute(CreateTenantData)` — provisioning atómico multi-step: dominio, DB schema, infraestructura, admin user, billing
- **DTOs:** `Central\Provisioning\DTOs\CreateTenantData` — name, slug, email, plan_id, password (opcional), payment_token (opcional)
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:input`, `flux:select`, `flux:button`
- **Notas UI_RULES:**
  - **⚠️ `CreateTenantAction` invoca `ProvisionInfrastructureAction` (Operations) y `RegisterPaymentMethod` (Billing) internamente** — el action orquesta cross-module, lo cual es correcto (está en Application layer), pero la dependencia debería estar detrás de contracts.
  - Validación de slug: `alpha_dash`, `unique:domains,domain`, y exclusión de `ReservedSlugs::$list`.
  - No se usa `PlanManager::all()` para el select de planes — es un `<select>` estático con opciones hardcodeadas (`free`, `pro`, `enterprise`). Esto podría desincronizarse del catálogo real de planes.
  - Tras éxito, redirige a `central.provisioning.index` con `navigate: true` (SPA).
  - El slug tiene un suffix visual que muestra el dominio central (`.{{ config('tenancy.central_domain') }}`).

---

### Manage Tenant — Edit (`provisioning::pages.manage-tenant`)

- **Ruta:** `GET /central/tenants/{tenant}/edit` → name: `central.provisioning.edit`
- **Contexto → Módulo:** `Central\Provisioning`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Edición de un tenant existente. Campos: account name, owner email, subscription plan (dinámico desde BD), operational status, maintenance mode, read-only mode. Si se cambia el plan, invalida la cache de features del tenant. Incluye el componente embebido `tenant-support-bitacora` al final.
- **Actions invocadas:**
  - `Central\Catalog\Actions\ResolveTenantFeatures::execute(Tenant, forceRefresh: true)` — invalida y refresca la cache de features si cambió el plan
  - CRUD directo: `$this->tenant->update([...])` — actualización inline del modelo
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:button`, `flux:card`, `flux:input`, `flux:select`, `flux:checkbox`
- **Componentes Livewire embebidos:** `<livewire:tenant-support-bitacora :tenant="$tenant" />` — bitácora de soporte del módulo `Central\Support`
- **Notas UI_RULES:**
  - **⚠️ Dependencia cruzada a `Central\Catalog`** — invoca `ResolveTenantFeatures` directamente. Aunque es para invalidar cache y técnicamente es transversal, la acción pertenece a Catalog. Usar un evento `PlanChanged` sería más desacoplado.
  - **⚠️ CRUD directo sin Action class** — el `save()` actualiza el modelo directamente con `$this->tenant->update()`. Solo invalida cache y logea actividad. Debería extraerse a un `UpdateTenantAction`.
  - **⚠️ Acceso a `Central\Catalog\Domain\Models\Plan`** — el `render()` consulta `Plan::where('is_active', true)->get()` para el select de planes. Acopla Provisioning → Catalog.
  - El dropdown de `plan_id` usa los IDs de los modelos `Plan` (UUIDs), no slugs. Esto es correcto para relación FK pero inconsistente con `CreateTenant` que usa slugs hardcodeados.
  - `mount(Tenant $tenant)` usa route-model-binding implícito.
  - Tras cambios, redirige a `central.provisioning.index` con `navigate: true`.

---

### Estructura de Layouts usados en Central\Provisioning

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.central` | `resources/views/layouts/central/sidebar.blade.php` | Central Admin | Sidebar con nav de administración. Usado por TenantList, CreateTenant, ManageTenant. |

### Flux Components utilizados en Central\Provisioning

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:button`, `flux:input`, `flux:select`, `flux:checkbox`, `flux:textarea`, `flux:badge`, `flux:link`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`

### Incidencias de Arquitectura detectadas en Central\Provisioning

| Vista | Problema | Impacto |
|-------|----------|---------|
| `TenantList` | Invoca `ImpersonateTenantAction` de `Central\Support` | Dependencia entre módulos Central. Aceptable si Support expone la acción como servicio público. |
| `ManageTenant` | Invoca `ResolveTenantFeatures` de `Central\Catalog` directamente | Acopla Provisioning → Catalog. Usar evento `PlanChanged` para desacoplar. |
| `ManageTenant` | `save()` hace CRUD directo sobre `Tenant` model sin Action class | Sin capa Application. Extraer a `UpdateTenantAction`. |
| `ManageTenant` | `render()` consulta `Plan` de `Central\Catalog\Domain\Models\Plan` | Acopla Provisioning → Catalog. Usar `PlanManager` o contrato en Platform. |
| `CreateTenant` | Select de planes hardcodeado (`free`, `pro`, `enterprise`) vs `ManageTenant` que usa valores dinámicos de BD | Inconsistencia: CreateTenant podría desincronizarse del catálogo real de planes. |

---

## Central\Support — Soporte y Comunicaciones (Broadcast, Bitácora, Anuncios)

### Broadcast Center (`support::pages.broadcast-center`)

- **Ruta:** `GET /central/support/broadcasts` → name: `central.support.broadcasts`
- **Contexto → Módulo:** `Central\Support`
- **Middleware:** `web`, `auth:central`
- **Propósito:** Panel de comunicaciones masivas con tenants. Dividido en dos columnas:
  - **Izquierda (Composer):** formulario para crear broadcast con título, cuerpo, filtro de audiencia (All Tenants / By Plan / By Status), y canales de entrega (Email, In-App Banner). Confirmación antes de enviar.
  - **Derecha (History):** tabla paginada de broadcasts enviados con título, target, contador de destinatarios, fecha de envío, creador.
- **Actions invocadas:**
  - `Central\Support\Actions\SendBroadcastAction::execute(BroadcastData)` — crea el broadcast, cuenta destinatarios, dispara `SendBulkBroadcastJob` para emails o marca como enviado para banners
- **DTOs:** `Central\Support\DTOs\BroadcastData` — title, body, filterType, filterValue, channels
- **Layout:** `layouts.central` → `layouts::central.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:textarea`, `flux:select`, `flux:checkbox.group`, `flux:checkbox`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`
- **Notas UI_RULES:**
  - **⚠️ `BroadcastCenter` importa `Plan` de `Central\Catalog\Domain\Models\Plan`** para poblar el select de filtro por plan. Acopla Support → Catalog. Usar un Query object o contrato en Platform.
  - **⚠️ `SendBroadcastAction` importa `Tenant` de `Central\Provisioning\Models\Tenant`** para contar y filtrar destinatarios. Acopla Support → Provisioning. Usar `TenantContract`.
  - El filtro de audiencia es dinámico: al seleccionar "By Plan" se despliega un select de planes; al seleccionar "By Status" se muestran opciones de estado (active, suspended, archived); "All Tenants" oculta el segundo select.
  - Los canales son checkboxes: email (dispara `SendBulkBroadcastJob` en background) y banner (visible en tenant UI vía `GlobalAnnouncements`).
  - `Broadcast` model tiene `filter_type` y `filter_value` que se evalúan en el momento de envío para contar destinatarios.
  - El botón "Dispatch Message" incluye `wire:confirm` para evitar envíos accidentales.

---

### Tenant Support Bitácora (`support::livewire.tenant-support-bitacora`)

- **Ruta:** No tiene ruta directa. Se embebe como `<livewire:tenant-support-bitacora :tenant="$tenant" />` dentro de `ManageTenant` (Provisioning).
- **Contexto → Módulo:** `Central\Support`
- **Middleware:** `web`, `auth:central` (hereda del padre)
- **Propósito:** Bitácora interna de soporte para un tenant específico. Dividida en dos columnas:
  - **Izquierda:** timeline de notas internas de soporte con autor, contenido (en itálicas entre comillas), y fecha relativa.
  - **Derecha:** historial de sesiones de impersonación con operador, razón y fecha.
  - Incluye formulario para agregar nuevas notas.
- **Actions invocadas:**
  - `Central\Support\Actions\CreateSupportNoteAction::execute(TenantContract, content)` — crea nota de soporte usando `TenantContract` (recibe interfaz, no modelo concreto). Logea actividad.
- **Layout:** No aplica (componente embebido sin layout propio)
- **Componentes Flux:** `flux:heading`, `flux:card`, `flux:textarea`, `flux:button`, `flux:text`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`
- **Notas UI_RULES:**
  - **✅ `CreateSupportNoteAction` usa `TenantContract` en lugar de `Tenant` model directamente** — correcto, sigue el principio de depender de contratos de Platform.
  - **⚠️ `TenantSupportBitacora` importa `Tenant` de `Provisioning`** en `mount()` para route-model-binding. Debería aceptar solo `tenantId` string y usar `TenantContract`.
  - **⚠️ Las queries en `render()` (`SupportNote::where(...)`, `SupportSession::where(...)`) son directas** — deberían usar queries/repositories.
  - El formulario requiere mínimo 5 caracteres para la nota.
  - Se embebe en la página de edición de tenant de Provisioning, no tiene ruta independiente.

---

### Global Announcements (`support::livewire.global-announcements`)

- **Ruta:** No tiene ruta directa. Se embebe como `<livewire:global-announcements />` en el layout `layouts.app` del tenant.
- **Contexto → Módulo:** `Central\Support` (scope tenant)
- **Middleware:** No aplica (embebido en layout)
- **Propósito:** Banner de anuncios globales visible para usuarios tenant. Muestra broadcasts cuyo canal incluye `banner`, que han sido enviados (`sent_at` not null), que coinciden con el filtro del tenant (all, plan, status), y que no han sido dismissados por el usuario actual. Cada banner tiene botón de dismiss que persiste en la BD central.
- **Actions invocadas:** Ninguna. Operaciones directas:
  - `Broadcast::whereNotNull('sent_at')->whereJsonContains('channels', 'banner')->...` — consulta de broadcasts activos
  - `DB::connection('central')->table('broadcast_dismissals')->updateOrInsert(...)` — persiste dismiss del usuario
- **Layout:** No aplica (componente embebido en `layouts::app.sidebar`)
- **Componentes Flux:** `flux:icon`
- **Notas UI_RULES:**
  - **⚠️ `GlobalAnnouncements` usa `DB::connection('central')` directamente** para acceder a la tabla `broadcast_dismissals` en la BD central desde el contexto tenant. Esto es técnicamente correcto (los dismissals se almacenan centralizadamente), pero frágil.
  - **⚠️ `Broadcast::whereJsonContains('channels', 'banner')`** — El query de broadcasts activos se ejecuta desde el contexto tenant pero consulta la BD central. Esto funciona porque el modelo `Broadcast` está configurado sin `BelongsToTenant` trait, pero debe verificarse que la conexión sea la correcta.
  - El dismiss se persiste con `updateOrInsert` combinando `broadcast_id + user_id + tenant_id` como clave única.
  - Se renderiza como un banner horizontal color indigo con icono de megáfono, título, cuerpo, y botón X para dismiss.
  - Si no hay tenant activo, retorna colección vacía (sin broadcasts).

---

### Impersonation Auth (controlador, sin UI)

- **Rutas:**
  - `GET /support/auth` → `TenantImpersonationController::authenticate()` — autentica operador con token one-time en dominio tenant
  - `POST /support/logout` → `TenantImpersonationController::logout()` — termina sesión de impersonación
- **Contexto → Módulo:** `Central\Support` (scope tenant, sin middleware de auth)
- **Propósito:** Endpoints de transición para impersonación. El `authenticate()` recibe un token de un solo uso, autentica al operador en el tenant, marca la sesión como activa, y redirige al dashboard. El `logout()` termina la sesión, notifica al tenant dueño que hubo impersonación.
- **Actions invocadas:**
  - `SupportSession::where('token', $token)->...firstOrFail()` — valida el token one-time
  - `$session->tenant->notify(ImpersonationEndedNotification)` — notifica al tenant que la impersonación terminó
- **Notas UI_RULES:**
  - El token es one-time: se reemplaza por `'used_' . Str::random(10)` tras su uso.
  - La sesión expira a las 2 horas (configurado en `ImpersonateTenantAction`).
  - Al logout, notifica al tenant dueño vía `ImpersonationEndedNotification`.
  - No son Livewire components — son controllers HTTP tradicionales con redirects.

---

### Estructura de Layouts usados en Central\Support

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.central` | `resources/views/layouts/central/sidebar.blade.php` | Central Admin | Sidebar. Usado por BroadcastCenter. |
| *(embebido)* | `layouts::app.sidebar` | Tenant | GlobalAnnouncements se renderiza dentro del sidebar del tenant. |
| *(embebido)* | `provisioning::pages.manage-tenant` | Central Admin | TenantSupportBitacora se embebe en la página de edición de tenant. |

### Flux Components utilizados en Central\Support

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:textarea`, `flux:select`, `flux:checkbox.group`, `flux:checkbox`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:icon`

### Incidencias de Arquitectura detectadas en Central\Support

| Vista | Problema | Impacto |
|-------|----------|---------|
| `BroadcastCenter` | Importa `Plan` de `Central\Catalog\Domain\Models\Plan` | Acopla Support → Catalog. Usar un Query object o contrato en Platform. |
| `SendBroadcastAction` | Importa `Tenant` de `Central\Provisioning\Models\Tenant` | Acopla Support → Provisioning. Usar `TenantContract`. |
| `TenantSupportBitacora` (mount) | Importa `Tenant` de Provisioning para route-model-binding | Acopla Support → Provisioning. Aceptar solo `tenantId` string. |
| `TenantSupportBitacora` (render) | Queries Eloquent directas en `render()` | Deberían usarse queries/repositories. |
| `GlobalAnnouncements` | Usa `DB::connection('central')` directamente | Frágil. Usar un service de Platform que abstraiga la conexión central. |

---

## Tenant\Access — Autenticación, Roles, API Keys y Export

### Login Tenant (`identity::livewire.login`)

- **Ruta:** `GET /auth/login` → name: `login`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant, guest)
- **Middleware:** Invitado (guest)
- **Propósito:** Autenticación de usuarios del workspace (email + password). Si el usuario tiene 2FA habilitado, redirige al challenge. La query de usuario se aplica automáticamente el scope `BelongsToTenant` (solo usuarios del tenant actual). Registra actividad de login.
- **Actions invocadas:** Ninguna directa. Operaciones inline:
  - `User::where('email', $email)->first()` — consulta scoped por tenant
  - `Hash::check($password, $user->password)` — verificación de password
  - `Auth::guard('web')->login($user, $remember)` — login nativo de Laravel
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:input`, `flux:link`, `flux:checkbox`, `flux:button`, `flux:card`, `flux:text`
- **Componentes Blade compartidos:** `x-auth-header`, `x-auth-session-status`
- **Notas UI_RULES:**
  - **⚠️ No usa Action class** — la lógica de autenticación está inline en el Livewire component. A diferencia del Login de Central\Auth que usa `LoginCentralUserAction`, este no tiene una capa Application. El login real lo maneja Fortify en la ruta POST.
  - Verifica `$user->is_active` — si el usuario está inactivo, rechaza login.
  - Si el usuario tiene 2FA, guarda `login.id` y `login.remember` en sesión y redirige a `two-factor.login`.
  - Después de login exitoso, redirige a `dashboard` con `redirectIntended`.

---

### 2FA Challenge Tenant (`identity::livewire.login-challenge`)

- **Ruta:** `GET /auth/2fa/verify` → name: `two-factor.login`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant, guest)
- **Middleware:** Invitado
- **Propósito:** Verificación de código 2FA (TOTP) después de login exitoso con credenciales en tenant. Solo accesible si existe `session('login.id')`. Usa `Google2FA` para verificar el código contra el secreto almacenado en `UserMfa`.
- **Actions invocadas:** Ninguna directa. Operaciones inline:
  - `Google2FA::verifyKey($secret, $code)` — verifica código TOTP
  - `User::findOrFail($userId)` — con tenant scope automático
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:card`, `flux:input`, `flux:button`, `flux:link`
- **Componentes Blade compartidos:** `x-auth-header`
- **Notas UI_RULES:**
  - `mount()` redirige a `login` si no hay `login.id` en sesión.
  - Usa `$user->mfa->secret` para obtener el secreto — relación HasOne con `UserMfa`.
  - Tras éxito, hace login, regenera sesión, olvida datos de sesión temporales, y redirige a dashboard.

---

### 2FA Enrollment Tenant (`identity::livewire.two-factor-enrollment`)

- **Ruta:** `GET /settings/security/2fa` → name: `tenant.settings.security.2fa`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Habilitar/deshabilitar autenticación de dos factores (TOTP) para usuario tenant. Mismo flujo que Central\Auth: estado inicial → Setup → QR → confirmar código → recovery codes. Persiste en `UserMfa` y marca `mfa_enabled = true` en el usuario.
- **Actions invocadas:**
  - `Tenant\Access\Actions\EnrollTenantMfa::initiate(User)` — genera secreto + QR URL
  - `Tenant\Access\Actions\EnrollTenantMfa::confirm(User, secret, code)` — verifica código, persiste `UserMfa`, actualiza `mfa_enabled`
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:badge`, `flux:input`, `flux:button`
- **Notas UI_RULES:**
  - Mismos 3 estados UI que Central\Auth. Cambia layout a `layouts.app` (tenant).
  - `EnrollTenantMfa` es específico de Tenant (usa `UserMfa` y `User` con tenant_id).
  - Recovery codes: 8 códigos en grid 2-columnas.

---

### Accept Invitation (`identity::livewire.accept-invitation`)

- **Ruta:** `GET /auth/invitations/{token}/accept` → name: `tenant.invitations.accept`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant, guest)
- **Middleware:** Invitado
- **Propósito:** Aceptar invitación a un workspace. El usuario establece su nombre y password. La invitación se valida por token hasheado (SHA256) y expiración. Tras aceptar, se crea/restaura el usuario, se asigna el rol de la invitación, y se logea automáticamente.
- **Actions invocadas:**
  - `Tenant\Access\Actions\AcceptInvitation::execute(UserAcceptanceData)` — valida token, crea/restaura usuario, asigna rol, marca invitación como aceptada, registra auditoría y dispatches `TenantUserJoined`
- **DTOs:** `Tenant\Access\DTOs\UserAcceptanceData` — token, name, password
- **Layout:** `layouts.auth` → `layouts::auth.split`
- **Componentes Flux:** `flux:card`, `flux:input`, `flux:button`
- **Componentes Blade compartidos:** `x-auth-header`
- **Notas UI_RULES:**
  - El token se hashea con SHA256 para la búsqueda (`hash('sha256', $token)`) — no se almacena el token en texto plano.
  - `mount()` valida que la invitación exista y no haya expirado (aborta 404 o 410).
  - Si el usuario ya existe (soft-deleted), lo restaura y actualiza datos.
  - Password mínimo 12 caracteres.
  - Tras aceptar, logea al usuario y redirige al dashboard.

---

### Role Management (`identity::livewire.role-management`)

- **Ruta:** `GET /settings/roles` → name: `tenant.roles.index`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Gestión de roles y permisos personalizados. Tabla de roles con permisos asociados (badges), tipo (SYSTEM / CUSTOM). Modal de creación y edición con checkboxes de permisos. Los roles del sistema no pueden renombrarse ni eliminarse. Al crear/editar, sincroniza permisos con Spatie, flushes cache de permisos, registra auditoría y dispatches eventos `TenantRoleCreated`/`TenantRoleUpdated`.
- **Actions invocadas:**
  - `Tenant\Audit\Actions\RecordAuditLogAction::execute(AuditLogData)` — registra en auditoría las operaciones de roles
  - `Spatie\Permission\PermissionRegistrar::forgetCachedPermissions()` — flush de cache de Spatie
  - CRUD y sync directo sobre `Role` y `Permission`
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:button`, `flux:label`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ CRUD directo sin Action class** — `create()`, `update()` y `delete()` operan directamente sobre `Role` model. Solo `RecordAuditLogAction` se delega a otro módulo.
  - **⚠️ Dependencia a `Tenant\Audit`** — invoca `RecordAuditLogAction` de Audit y usa `AuditAction` enum y `AuditLogData` DTO. Acopla Access → Audit.
  - Permisos hardcodeados en `$availablePermissions` (6 scopes: team:read, team:manage, roles:manage, settings:manage, billing:manage, audit:read).
  - `mount()` asegura que los permisos existan en BD (`Permission::firstOrCreate`).
  - Roles del sistema (`is_system = true`) no pueden editarse ni eliminarse.
  - Al eliminar, verifica que el rol no tenga usuarios activos (aborta 409).
  - Dispatches eventos de Platform para integraciones externas.

---

### Manage API Keys (`identity::livewire.manage-api-keys`)

- **Ruta:** `GET /settings/api-keys` → name: `tenant.api-keys.index`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Gestión de API keys para integraciones externas. Tabla de keys con nombre, scopes (badges), último uso, estado (ACTIVE/REVOKED). Modal de generación con nombre + checkboxes de scopes. Modal de resultado muestra la key en texto plano una sola vez. Verifica límite de keys por plan vía `QuotaManager` antes de generar.
- **Actions invocadas:**
  - `Tenant\Access\Actions\GenerateApiKey::execute(name, scopes, creator)` — genera key con prefijo `tnt_` + 32 bytes aleatorios, persiste con hash HMAC, registra auditoría, dispatches `TenantApiKeyCreated`
  - `Tenant\Access\Actions\RevokeApiKey::execute(ApiKey)` — marca `revoked_at`, registra auditoría, dispatches `TenantApiKeyRevoked`
  - `Platform\Tenancy\Services\QuotaManager::increment(tenant, 'api_keys')` — verifica y actualiza quota de API keys
  - `Tenant\Audit\Actions\RecordAuditLogAction::execute(...)` — registro de auditoría
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:button`, `flux:label`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ Dependencia a `Tenant\Audit`** — igual que RoleManagement, usa `RecordAuditLogAction`.
  - **⚠️ Dependencia a `Platform\Tenancy\QuotaManager`** — correcto (es de Platform), pero debería usarse un contrato.
  - Scopes hardcodeados (5 scopes: identity:read, identity:write, settings:read, settings:write, audit:read).
  - La key se genera con `bin2hex(random_bytes(32))` — 64 caracteres hexadecimales.
  - Se almacena hasheada con `HmacSigner::hash()` — no se guarda en texto plano.
  - La key en texto plano solo se muestra una vez en un modal especial con `:open="$showingKey"`.
  - Revocación con `wire:confirm` para confirmación del usuario.

---

### Data Export (`identity::livewire.data-export`)

- **Ruta:** `GET /settings/export` → name: `tenant.settings.export`
- **Contexto → Módulo:** `Tenant\Access` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Solicitar exportación de datos del tenant (GDPR / portabilidad). Botón que dispara un job en background que recolecta datos de identity, settings y billing. El usuario recibe un email con el enlace de descarga del archivo JSON.
- **Actions invocadas:**
  - `Tenant\Access\Actions\ExportTenantData::execute(userId)` — dispatches `ExportTenantDataJob` en background
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:button`
- **Notas UI_RULES:**
  - Componente minimalista: solo título, descripción y botón.
  - Botón con estado de loading (`wire:loading.attr="disabled"` o `:loading`).
  - El job se ejecuta en background — no bloquea el UI.
  - Sin incidencias de arquitectura: usa solo Actions de su propio módulo.

---

### Rutas de Fortify + Passkeys (sin componente Livewire propio)

- **Rutas:** Varias rutas `GET /auth/*` + `POST /auth/*` delegadas a Fortify y Passkeys
- **Contexto → Módulo:** `Tenant\Access`
- **Propósito:** Las rutas de forgot-password, reset-password, register, verify-email, confirm-password, login/out POST, y passkeys son manejadas por Laravel Fortify y Laravel Passkeys. Las vistas GET correspondientes viven en `resources/views/pages/auth/` (shared).
- **Notas UI_RULES:**
  - Las vistas GET son closures simples que retornan `view('pages::auth.*')` desde shared views.
  - 6 rutas de Passkeys (WebAuthn) para login, registro y confirmación con passkeys.
  - No son Livewire components — son controllers de Fortify/Passkeys.

---

### Estructura de Layouts usados en Tenant\Access

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.auth` | `resources/views/layouts/auth/split.blade.php` | Guest | Auth split layout. Usado por Login, LoginChallenge, AcceptInvitation, y rutas Fortify. |
| `layouts.app` | `resources/views/layouts/app/sidebar.blade.php` | Tenant | Sidebar de tenant. Usado por TwoFactorEnrollment, RoleManagement, ManageApiKeys, DataExport. |

### Flux Components utilizados en Tenant\Access

`flux:input`, `flux:link`, `flux:checkbox`, `flux:button`, `flux:card`, `flux:text`, `flux:heading`, `flux:subheading`, `flux:badge`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:label`, `flux:spacer`

### Incidencias de Arquitectura detectadas en Tenant\Access

| Vista | Problema | Impacto |
|-------|----------|---------|
| `Login` | Lógica de autenticación inline sin Action class | Central\Auth sí tiene `LoginCentralUserAction` — inconsistencia. Extraer a `Tenant\Access\Actions\LoginTenantUserAction`. |
| `Login` | `User::where('email', $email)->first()` directo en el componente | El scope `BelongsToTenant` protege, pero debería estar en un Action o Query. |
| `RoleManagement` | `create()`, `update()`, `delete()` son CRUD directo sin Action class | Sin capa Application. Extraer a Actions. |
| `RoleManagement` | Invoca `RecordAuditLogAction` de `Tenant\Audit` | Acopla Access → Audit. Usar eventos de dominio. |
| `ManageApiKeys` | Invoca `RecordAuditLogAction` de `Tenant\Audit` | Misma incidencia que RoleManagement. |
| `ManageApiKeys` | `QuotaManager` inyectado con `app()` inline | Usar inyección de dependencias. |
| `RoleManagement` / `ManageApiKeys` | Permisos y scopes hardcodeados en arrays PHP | Deberían definirse en BD o config, no en el código del componente. |

---

## Tenant\Audit — Visor de Auditoría

### Audit Log Viewer (`audit::pages.viewer`)

- **Ruta:** `GET /audit` → name: `tenant.audit.index`
- **Contexto → Módulo:** `Tenant\Audit` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Visor de log de auditoría con filtros avanzados y exportación CSV. Muestra tabla paginada (50 por página) con: acción (badge), miembro (avatar + nombre), recurso, IP, fecha. Filtros: por miembro (select), búsqueda de acción (texto con debounce), rango de fechas (date pickers). Los filtros se persisten en la URL vía `#[Url]`. Incluye modal de exportación con rango de fechas (máx 90 días).
- **Actions invocadas:**
  - `Tenant\Audit\Actions\RecordAuditLogAction::execute(AuditLogData)` — registra en auditoría la solicitud de exportación
  - `Tenant\Audit\Jobs\ExportAuditLogsJob::dispatch(...)` — job en background que genera CSV y notifica al usuario por email
- **DTOs:** `Tenant\Audit\DTOs\AuditLogData` — action, resource, resourceId, metadata, ip, userId
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:select`, `flux:input`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:avatar`, `flux:modal`, `flux:modal.close`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ Importa `User` de `Tenant\Access\Domain\Models\User`** en el `render()` para el select de filtro de miembros. Acopla Audit → Access. Usar un Query object o contrato en Platform.
  - Filtros con `#[Url]` — se persisten en query string, permiten compartir URLs con filtros aplicados.
  - `updated()` detecta cambios en filtros y resetea la página de paginación.
  - Debounce de 500ms en `filterAction` para evitar demasiados requests.
  - Exportación limitada a 90 días — política de seguridad aplicada tanto en frontend como en el job.
  - El job `ExportAuditLogsJob` inicializa tenancy manualmente (`tenancy()->initialize($tenantId)`) y la finaliza en el `finally`.
  - El CSV se almacena en disco `private` y se descarga vía `AuditDownloadController` con signed URL.
  - `AuditLog` usa `BelongsToTenant` trait — scope automático por tenant.

---

### Audit Data Download (controlador, sin UI)

- **Rutas:**
  - `GET /audit/download` → name: `tenant.audit.download`
  - `GET /data/download` → name: `tenant.data.download`
- **Contexto → Módulo:** `Tenant\Audit`
- **Middleware:** Requiere signed URL (`$request->hasValidSignature()`)
- **Propósito:** Descarga segura de archivos de exportación (audit logs CSV y data export JSON). Verifica que la ruta solicitada tenga un prefijo permitido específico del tenant (`exports/audit/audit_log_{tenantId}` o `exports/tenant_data_{tenantId}`). Previene path traversal y acceso cross-tenant.
- **Actions invocadas:** Ninguna. Llamadas directas a Storage de Laravel.
- **Layout:** No aplica (descarga de archivo)
- **Notas UI_RULES:**
  - Requiere signed URL (Laravel `URL::signedRoute()`) — evita descargas no autorizadas.
  - Validación de path por prefijo: solo permite archivos que comiencen con los prefijos específicos del tenant.
  - Usa disco `private` de Laravel Storage.
  - Logea intentos de acceso no autorizados.

---

### Estructura de Layouts usados en Tenant\Audit

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.app` | `resources/views/layouts/app/sidebar.blade.php` | Tenant | Sidebar de tenant. Usado por AuditLogViewer. |

### Flux Components utilizados en Tenant\Audit

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:select`, `flux:input`, `flux:button`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:badge`, `flux:avatar`, `flux:modal`, `flux:modal.close`, `flux:spacer`

### Incidencias de Arquitectura detectadas en Tenant\Audit

| Vista | Problema | Impacto |
|-------|----------|---------|
| `AuditLogViewer` | Importa `User` de `Tenant\Access\Domain\Models\User` para el selector de filtro | Acopla Audit → Access. Usar un Query object o contrato en Platform. |
| `ExportAuditLogsJob` | Importa `User` de `Tenant\Access\Domain\Models\User` | Misma incidencia. Usar `TenantUserContract` o notificar sin resolver el modelo User. |

---

## Tenant\Experience — Branding, Localización y Landing Builder

### Branding Settings (`settings-tenant::livewire.branding-settings`)

- **Ruta:** `GET /settings/branding` → name: `tenant.settings.branding`
- **Contexto → Módulo:** `Tenant\Experience` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Personalización de la identidad visual del tenant. Incluye: nombre visible, upload de logo (max 2MB), selector de color primario con presets de tema (saas, modern, nature, corporate, custom), preview de logo. Además: toggle de MFA obligatorio para todos los miembros, y sección de inicialización/ acceso al Landing Builder.
- **Actions invocadas:**
  - `Tenant\Experience\Actions\UpdateTenantBranding::execute(BrandingData)` — actualiza nombre, color, logo (sube a storage public, limpia logo anterior), sincroniza con `Tenant` record central, sincroniza tema del saas-landing, dispatches eventos `TenantSettingsUpdated` y `TenantMfaRequirementChanged`
  - `Tenant\Experience\Actions\InitializeTenantLanding::execute(themePreset, primaryColor)` — crea la landing page por defecto (saas-landing) con el tema actual
- **DTOs:** `Tenant\Experience\DTOs\BrandingData` — name, primaryColor, themePreset, mfaRequired, logo (UploadedFile)
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:select`, `flux:button`, `flux:switch`, `flux:icon.megaphone`, `flux:error`
- **Notas UI_RULES:**
  - **⚠️ `BrandingSettings` importa `Landing` model directamente** en la vista (línea 80) para verificar si existe landing. Esto es código en la vista que debería estar en el componente o en un action.
  - Usa `Livewire\WithFileUploads` para upload de logo.
  - `updatedThemePreset()` cambia automáticamente el color primario según el preset seleccionado.
  - `getLogoPreviewUrlProperty()` usa `temporaryUrl()` de Livewire para preview antes de guardar.
  - El toggle de MFA (`flux:switch`) dispara `save()` en cada cambio (`wire:click="save"`) — guarda inmediatamente.
  - `InitializeTenantLanding` verifica policy `update` mediante `Gate::authorize`.
  - `UpdateTenantBranding` sincroniza el nombre con el modelo `Tenant` central (`tenant()->update(...)`) — esto acopla Experience → Provisioning a nivel de action, pero es un side-effect controlado.

---

### Localization Settings (`settings-tenant::livewire.localization-settings`)

- **Ruta:** `GET /settings/localization` → name: `tenant.settings.localization`
- **Contexto → Módulo:** `Tenant\Experience` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Configuración regional del tenant: timezone (búsqueda), idioma (en/es), moneda preferida (USD, EUR, MXN, COP, BRL). Incluye nota informativa sobre el impacto de cambiar moneda en suscripciones activas.
- **Actions invocadas:**
  - `Tenant\Experience\Actions\UpdateTenantLocalization::execute(LocalizationData)` — persiste timezone/locale/currency en `TenantSetting`, registra auditoría vía `RecordAuditLogAction` de Audit, dispatches `TenantSettingsUpdated`
- **DTOs:** `Tenant\Experience\DTOs\LocalizationData` — timezone, locale, currency
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:select`, `flux:button`, `flux:icon`
- **Notas UI_RULES:**
  - **⚠️ Dependencia a `Tenant\Audit`** — `UpdateTenantLocalization` invoca `RecordAuditLogAction` de Audit. Acopla Experience → Audit.
  - El select de timezone es `searchable` — permite búsqueda entre cientos de timezones.
  - Los arrays de locales y monedas están hardcodeados en el `render()`.
  - Incluye nota informativa (banner amber) sobre implicaciones de cambiar moneda para transacciones existentes.
  - `mount()` carga valores del `TenantSetting` existente o usa defaults.

---

### Landing Builder (`settings-tenant::landings.livewire.landing-builder`)

- **Ruta:** `GET /landings/{landing}/builder` → name: `tenant.landings.builder`
- **Contexto → Módulo:** `Tenant\Experience` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Constructor visual drag-and-drop de landing pages. UI de 3 columnas:
  - **Izquierda:** librería de bloques (Hero, CTA, Features, Pricing, Testimonials, FAQ, Contact, About, Statistics, Gallery, Lead Form, Trust Signals, Footer) + lista de bloques agregados con reordenamiento y delete.
  - **Centro:** Canvas de preview con cambio de viewport (desktop/tablet/mobile).
  - **Derecha:** Editor de propiedades del bloque seleccionado (variant, headline, subtitle, y config específica por tipo).
  - Toolbar superior con acciones: Save Draft, Publish, Preview.
- **Actions invocadas:**
  - `Tenant\Experience\Actions\PublishLanding::execute(Landing, publisherId)` — renderiza HTML final, crea snapshot de versión (`LandingVersion`), actualiza estado a published con HTML cacheado
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:badge`, `flux:button`, `flux:select`, `flux:input`, `flux:textarea`, `flux:switch`, `flux:icon.*`, `flux:label`, `flux:field`
- **Notas UI_RULES:**
  - **El componente es mayoritariamente Alpine.js** — todo el estado de edición (blocks, theme, selectedBlockId, isDirty, viewMode) se maneja en el frontend con `x-data`. Livewire se usa solo para persistencia (`$wire.save()` y `$wire.publish()`).
  - 14 tipos de bloques con variantes específicas (hero: 5 variantes, features: 4, pricing: 2, testimonials: 3, cta: 3, footer: 2, faq: 3, contact: 3, about: 4, statistics: 3, gallery: 3, lead-form: 3, trust-signals: 2).
  - El canvas muestra previews condicionales para cada tipo de bloque usando `@include('landings::livewire.previews.*')` con Alpine `x-show` para cada tipo.
  - Las previews de bloques son puras representaciones visuales — no hay interacción real en el canvas.
  - `save()` pasa arrays `blocks` y `theme` a Livewire mediante `$wire.save()` y los persiste en el modelo `Landing`.
  - `publish()` renderiza el HTML final mediante `PublishLanding` (que usa `RenderLanding` action) y guarda una versión snapshot en `LandingVersion`.
  - `mount(Landing $landing)` verifica que el landing pertenezca al tenant actual (aborta 403 si no).
  - `save()` en el componente recibe `$blocks` y `$theme` como parámetros — Livewire hydration maneja arrays complejos.

---

### Serve Tenant Landing (controlador, sin interfaz de admin)

- **Ruta:** `GET /` (tenant root) → sirve por `ServeTenantLandingController`
- **Contexto → Módulo:** `Tenant\Experience` (scope tenant, público)
- **Middleware:** Público (sin autenticación)
- **Propósito:** Sirve la landing page publicada del tenant en su dominio raíz. Busca una landing publicada (primero con slug `saas-landing`, luego cualquier otra). Si existe y tiene `published_html`, lo retorna como HTML. Si no, fallback a `welcome.blade.php` con datos del tenant.
- **Actions invocadas:** Ninguna directa. Consultas:
  - `Landing::where('tenant_id', $tenantId)->where('status', 'published')->first()`
- **Layout:** No aplica (retorna `published_html` directamente o `view('welcome')`)
- **Notas UI_RULES:**
  - Implementado como Controller invocable tradicional, no Livewire.
  - Prioriza landing con slug `saas-landing` usando `orderByRaw`.
  - El HTML publicado se cachea en el campo `published_html` del modelo `Landing` — evita renderizar en cada request.
  - Fallback a `welcome.blade.php` (shared view) si no hay landing publicada.

---

### Estructura de Layouts usados en Tenant\Experience

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.app` | `resources/views/layouts/app/sidebar.blade.php` | Tenant | Sidebar de tenant. Usado por BrandingSettings, LocalizationSettings, LandingBuilder. |
| *(ninguno)* | — | Público | ServeTenantLandingController retorna HTML directo o `view('welcome')` sin layout de app. |

### Flux Components utilizados en Tenant\Experience

`flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:select`, `flux:button`, `flux:switch`, `flux:icon.*`, `flux:error`, `flux:label`, `flux:field`, `flux:badge`, `flux:textarea`

### Incidencias de Arquitectura detectadas en Tenant\Experience

| Vista | Problema | Impacto |
|-------|----------|---------|
| `BrandingSettings` | Consulta directa a `Landing` model en la vista (línea 80 del blade) | Lógica de negocio en la capa de presentación. Mover al componente o a un action. |
| `UpdateTenantBranding` (action) | Sincroniza `tenant()->update(['name'])` con el modelo central | Acopla Experience → Provisioning. Usar un evento `TenantRenamed` en Platform. |
| `UpdateTenantLocalization` (action) | Invoca `RecordAuditLogAction` de `Tenant\Audit` | Acopla Experience → Audit. Usar eventos de dominio. |
| `LandingBuilder` (mount) | `$landing->tenant_id !== tenant('id')` — verificación inline en el componente | Debería ser una Policy. |
| `LandingBuilder` | `save()` y `publish()` con CRUD directo sobre `Landing` model | Publish usa `PublishLanding` action (correcto), pero save es inline update del modelo. |

---

## Tenant\Integrations — Configuración SMTP

### SMTP Settings (`settings-tenant::livewire.smtp-settings`)

- **Ruta:** `GET /settings/smtp` → name: `tenant.settings.smtp`
- **Contexto → Módulo:** `Tenant\Integrations` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Configuración del servidor SMTP personalizado del tenant. Formulario con: host, puerto, usuario, password (opcional al editar), sender email y sender name. Incluye sección de test de conexión que envía un email de prueba y verifica la configuración. Muestra badge de estado (verificado / no verificado).
- **Actions invocadas:**
  - `Tenant\Integrations\Actions\UpdateTenantSmtp::execute(SmtpConfigData)` — actualiza configuración SMTP en `TenantSetting`, resetea `smtp_verified` a false, registra auditoría vía `RecordAuditLogAction` de Audit, dispatches evento `TenantSmtpConfigured`
  - `Tenant\Integrations\Services\TenantMailerService::withConfig(SmtpConfigData, callback)` — configura mailer temporal con los datos SMTP, ejecuta callback de envío, restaura configuración original (previene side-effects en el mismo proceso)
- **DTOs:** `Tenant\Integrations\DTOs\SmtpConfigData` — host, port, user, password, fromEmail, fromName
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:input`, `flux:button`, `flux:icon`
- **Notas UI_RULES:**
  - **⚠️ Dependencias externas pesadas** — el componente importa:
    - `Tenant\Experience\Domain\Models\TenantSetting` (acopla Integrations → Experience)
    - `Tenant\Audit\Actions\RecordAuditLogAction` en `UpdateTenantSmtp` (acopla Integrations → Audit)
  - **⚠️ `TenantSetting` model compartido entre Experience e Integrations** — ambos módulos usan el mismo modelo `TenantSetting` del módulo Experience. Esto crea acoplamiento fuerte. Los campos SMTP deberían estar en su propio modelo dentro de Integrations.
  - La contraseña SMTP se almacena encriptada (`smtp_password` en BD con cast `encrypted`).
  - `mount()` no popula `smtp_password` por seguridad — solo se reemplaza si el usuario escribe una nueva.
  - `testConnection()` descifra la password guardada si no se proporcionó una nueva.
  - `TenantMailerService::withConfig()` usa `Config::set()` para crear un mailer temporal (`mail.mailers.tenant_test`) y lo limpia en el `finally` — esto es thread-safe para FPM pero precaución en Octane.
  - Tras test exitoso, marca `smtp_verified = true` y muestra badge verde de verificación.
  - El badge de estado se determina con una consulta inline en el blade (línea 7: `TenantSetting::where(...)->first()?->smtp_verified`) — lógica en la vista.

---

## Tenant\Workspace — Dashboard, Team, Notificaciones y Uso

### Dashboard (`workspace::pages.dashboard`)

- **Ruta:** `GET /dashboard` → name: `dashboard`
- **Contexto → Módulo:** `Tenant\Workspace` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Página principal del workspace del tenant. Embebe el componente `tenant-usage-overview` (métricas de uso) y muestra 4 placeholders visuales para futuros widgets (cards con patrón decorativo). Es el destino de redirección post-login.
- **Actions invocadas:** Ninguna directa. Embebe `UsageOverview`.
- **Layout:** `layouts.app` → `layouts::app.sidebar` (usando `x-layouts::app` directamente en el blade)
- **Componentes Flux:** Ninguno directo. El layout usa `flux:main` y `flux:toast`.
- **Componentes Livewire embebidos:** `<livewire:tenant-usage-overview />`
- **Notas UI_RULES:**
  - Es una vista blade simple (closure en ruta), no un Livewire component — `Route::get('/dashboard', fn() => view(...))`.
  - El dashboard es mayoritariamente placeholder — solo el componente de uso está implementado.
  - Las cards con `x-placeholder-pattern` son decorativas, sin funcionalidad.

---

### Team Management (`workspace::livewire.team-management`)

- **Ruta:** `GET /team/members` → name: `tenant.team.index`
- **Contexto → Módulo:** `Tenant\Workspace` (scope tenant)
- **Middleware:** `auth` (usuario autenticado)
- **Propósito:** Gestión completa del equipo del tenant. Incluye:
  - **Tabla de miembros:** avatar, nombre, email, rol (badge), estado (ACTIVE/REVOKED), fecha de ingreso. Acciones por miembro: cambiar rol (modal), revocar acceso (soft delete).
  - **Invitaciones pendientes:** email, rol, expiración, acciones de reenviar y cancelar.
  - **Modal de invitación:** email + selección de rol.
  - **Modal de cambio de rol:** selector de roles disponibles.

- **Actions invocadas:**
  - `Tenant\Access\Actions\SendInvitation::execute(InvitationData, user)` — envía invitación y crea registro en `Invitations` (módulo Access)
  - CRUD directo sobre `Invitation`, `User`, `Role` — delete, syncRoles, update status
  - Dispara evento `TenantUserRevoked` de Platform al revocar acceso
- **DTOs:** `Tenant\Access\DTOs\InvitationData` — email, roleName
- **Layout:** `layouts.app` → `layouts::app.sidebar`
- **Componentes Flux:** `flux:heading`, `flux:subheading`, `flux:card`, `flux:text`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:avatar`, `flux:badge`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:button`, `flux:input`, `flux:select`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`
- **Notas UI_RULES:**
  - **⚠️ Dependencias a `Tenant\Access`** — importa `SendInvitation` action, `Invitation`, `Role`, `User` models de Access. Acopla Workspace → Access. Aunque es esperable (Workspace orquesta team management), el uso de modelos directamente viola la regla de no acceso directo a Models de otro módulo.
  - **⚠️ CRUD directo sobre modelos de otro módulo** — `cancelInvitation()` hace `Invitation::findOrFail($id)->delete()` directamente; `revokeAccess()` hace `User::findOrFail($userId)->update(...)->delete()` directamente.
  - `selectMember()` carga el usuario en `$selectedMember` para el modal de cambio de rol, con validación de que no sea auto-asignación.
  - `updateRole()` usa `setPermissionsTeamId(tenant('id'))` de Spatie antes de `syncRoles`.
  - Las invitaciones expiradas se muestran con badge rojo EXPIRED y fecha de expiración.
  - `resendInvitation()` crea una nueva invitación y elimina la anterior.
  - Los menús de acción solo aparecen para miembros que no sean el usuario actual.

---

### Notification Center (`workspace::livewire.notification-center`)

- **Ruta:** No tiene ruta directa. Se embebe como `<livewire:tenant-notification-center />`.
- **Contexto → Módulo:** `Tenant\Workspace` (scope tenant)
- **Middleware:** `auth` (hereda del layout)
- **Propósito:** Lista paginada de notificaciones del usuario autenticado. Cada notificación muestra mensaje, fecha relativa, y acciones: marcar como leída (check) y eliminar (trash).
- **Actions invocadas:**
  - `Tenant\Workspace\Actions\MarkNotificationAsRead::execute(notificationId)` — marca `read_at` en la notificación
  - `Tenant\Workspace\Actions\DeleteNotification::execute(notificationId)` — elimina la notificación
- **Layout:** No aplica (componente embebido)
- **Componentes Flux:** `flux:heading`, `flux:card`, `flux:text`, `flux:button`
- **Notas UI_RULES:**
  - Ambas acciones verifican que la notificación pertenezca al usuario autenticado (`where('notifiable_id', auth()->id())`).
  - Usa `auth()->user()->tenantNotifications()` — relación personalizada definida en `HasTenantNotifications` trait de `Tenant\Access`.
  - Las notificaciones no leídas se muestran con texto oscuro, las leídas en gris.

---

### Usage Overview (`workspace::livewire.usage-overview`)

- **Ruta:** No tiene ruta directa. Se embebe como `<livewire:tenant-usage-overview />` dentro del dashboard.
- **Contexto → Módulo:** `Tenant\Workspace` (scope tenant)
- **Middleware:** `auth` (hereda del dashboard)
- **Propósito:** Cards de métricas de uso del tenant: staff activos, bookings mensuales, invitaciones pendientes, API keys activas. Cada card muestra valor actual / límite, barra de progreso, y badge de estado (UNLIMITED, FULL, NEAR LIMIT). Los límites se obtienen del plan vía `QuotaManager`.
- **Actions invocadas:**
  - `Platform\Tenancy\Services\QuotaManager::getCurrentUsage(tenant, metric)` — obtiene uso actual
  - `Platform\Tenancy\Services\QuotaManager::getLimit(tenant, metric)` — obtiene límite según el plan
- **Layout:** No aplica (componente embebido)
- **Componentes Flux:** `flux:card`, `flux:heading`, `flux:badge`
- **Notas UI_RULES:**
  - Usa `QuotaManager` de Platform (correcto, depende de Platform).
  - Las métricas están hardcodeadas en `$metrics` array (staff, bookings, invitations, api_keys).
  - La barra de progreso usa clases condicionales: rojo si ≥100%, ámbar si ≥80%, índigo si <80%.
  - `percentage` calculado con `min(100, round(...))` para evitar overflow visual.
  - `is_unlimited` se determina con `limit === -1`.

---

### Estructura de Layouts usados en Tenant\Workspace

| Layout | Ruta vista | Scope | Descripción |
|--------|-----------|-------|-------------|
| `layouts.app` | `resources/views/layouts/app/sidebar.blade.php` | Tenant | Sidebar de tenant. Usado por Dashboard y TeamManagement. |

### Flux Components utilizados en Tenant\Workspace

`flux:card`, `flux:heading`, `flux:subheading`, `flux:text`, `flux:badge`, `flux:table`, `flux:table.columns`, `flux:table.column`, `flux:table.rows`, `flux:table.row`, `flux:table.cell`, `flux:avatar`, `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator`, `flux:button`, `flux:input`, `flux:select`, `flux:modal`, `flux:modal.trigger`, `flux:modal.close`, `flux:spacer`

### Incidencias de Arquitectura detectadas en Tenant\Workspace

| Vista | Problema | Impacto |
|-------|----------|---------|
| `TeamManagement` | Importa `SendInvitation`, `Invitation`, `Role`, `User` de `Tenant\Access` directamente | Acopla Workspace → Access. Aunque es orquestación esperable, debería usar Actions de Access como intermediarios. |
| `TeamManagement` | `cancelInvitation()` y `revokeAccess()` hacen CRUD directo sobre modelos de Access | Violación de encapsulamiento. Delegar a Actions de Access. |
| `NotificationCenter` | `tenantNotifications()` es una relación definida en `HasTenantNotifications` trait de Tenant\Access | Dependencia implícita. El trait debería estar en Platform si es transversal. |
| `UsageOverview` | Métricas hardcodeadas en array PHP | Deberían definirse en config o BD para extensibilidad. |
