# UI IMPLEMENTATION AGENT

Actúa como Lead UI Engineer del proyecto LaraShift (SaaS Modular Monolith Laravel).

## Contexto

El backend del módulo ya existe y está completo (Actions, DTOs, Models, Controllers, Events/Jobs, Tests). Tu tarea es crear únicamente la capa UI:

- Livewire Components
- Blade Views (en `UI/` del módulo)
- Web Routes (en el ServiceProvider del módulo o en `routes/tenant.php`)
- UI Tests

No modifiques lógica de negocio existente.

---

## tarea

Estás trabajando en el módulo `app/Modules/{{ BoundedContext }}/{{ DomainModule }}/` de un Monolito Modular Laravel (Blade + Livewire + FluxUI).

Reglas de scope — no las rompas:
- Solo puedes leer/escribir dentro de este módulo: Actions/, DTOs/, Events/, Http/, Livewire/, Models/, Resources/, Providers/, routes/.
- No toques otros módulos ni Shared/ salvo que la tarea lo pida explícitamente.
- No modifiques Actions/, Models/, DTOs/ ni Http/Controllers/ existentes si la tarea es de UI — solo consúmelos.
- Los componentes Livewire solo orquestan estado de UI y llaman Actions ya existentes; nunca reimplementan lógica de negocio.
- Usa exclusivamente los componentes globales del Design System (`<x-table>`, `<x-modal>`, `<x-badge>`, `<x-alert>`, `<x-empty-state>`, `<x-skeleton>`, `<x-layout.host|tenant|public>`). No crees alternativas propias.
- Respeta el namespace de vistas del módulo y el layout correspondiente al scope (Central → host, Tenant → tenant).
- Toda tabla o query tenant-scoped debe respetar el scope/aislamiento de tenant existente — no lo bypasees.
- Si necesitas algo que no existe en este módulo (una Action, un componente global, una ruta), repórtalo y detente — no lo improvises ni lo construyas en otro módulo.

## 💳 FASE UI-4 — Billing & Provisioning

> **Objetivo:** El backoffice host tiene UI completa para gestionar planes, suscripciones, pagos y el ciclo de vida de provisioning de tenants.

---

### Sprint U04 — Billing — Planes, Suscripciones & Pagos
**Módulos:** `Central/Billing` · `Central/Payments`

**`Central/Payments`**

- [ ] `Payments/Livewire/GatewaySettings.php` —  (configuración de pasarela)
- [ ] `Payments/Livewire/WebhookLog.php` —  (log de webhooks entrantes)
- [x] `Payments/Livewire/PayoutRequests.php` — Gestión de payouts
- [x] `Payments/Livewire/PayoutSettings.php` — Configuración de payouts
- [x] `Payments/Livewire/CheckoutComponent.php` — Checkout


---

## Antes de programar

Consulta:

- `docs/ARCHITECTURE.md`
- `docs/CODINGSTANDARD.md`
- `docs/UI/UI_DOCS.md` — especificación de pantallas
- `docs/UI/ROADMAP_UI.md` — estado actual del UI roadmap
- `docs/UI/UI_GENERATED.md` — design system de referencia (opcional, el stack real usa Flux UI)

Analiza el módulo backend:

```text
app/Modules/{Scope}/{Module}/
├── Actions/
├── DTOs/
├── Models/
├── Livewire/      ← (si ya existe UI)
├── Providers/
└── UI/            ← (vistas existentes)
```

Identifica:

1. Actions disponibles (qué operaciones puedo llamar desde el Livewire)
2. DTOs disponibles (qué datos entran/salen de las Actions)
3. Componentes Livewire necesarios vs existentes
4. Archivos a crear/modificar
5. Rutas existentes en `routes/tenant.php` o providers

Detente si:
- Falta una Action necesaria para la UI
- Existe conflicto de rutas
- El módulo backend no está completo

---

## Stack UI Real

| Aspecto             | Convención                                                                                                                                                                                       |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Framework**       | Livewire 4                                                                                                                                                                                       |
| **UI Kit**          | Flux UI (`flux:button`, `flux:card`, `flux:modal`, `flux:badge`, `flux:table`, `flux:input`, `flux:select`, `flux:textarea`, `flux:checkbox`, `flux:heading`, `flux:separator`, `flux:dropdown`) |
| **CSS**             | Tailwind CSS 4                                                                                                                                                                                   |
| **Layouts**         | `#[Layout('layouts.central')]` para Host, `#[Layout('layouts.app')]` para Tenant, `#[Layout('layouts.marketing')]` para público                                                                  |
| **Notificación UI** | `$this->dispatch('notify', message: __('...'))`                                                                                                                                                  |
| **Feedback**        | `session()->flash('status', __('...'))`                                                                                                                                                          |

**NO crees componentes Blade globales si ya Flux los ofrece** (`x-table`, `x-modal`, `x-alert`, `x-badge`, `x-skeleton`, `x-empty-state`, `x-layout.*`). El stack usa Flux UI para todo eso. Usa los componentes de Flux directamente.

---

## Estructura UI (convención actual del código)

Los módulos siguen dos convenciones. Usa la que ya tenga el módulo que modificas:

### Convención A (recomendada, usada por ~14 módulos)

```
app/Modules/{Scope}/{Module}/
├── Livewire/          ← Componentes Livewire
│   ├── MiComponente.php
│   └── ...
├── Providers/
│   └── XxxServiceProvider.php   ← registro de vistas, Livewire y rutas
└── UI/                ← Vistas Blade
    └── livewire/
        └── mi-componente.blade.php
```

Registro en ServiceProvider:
```php
$this->loadViewsFrom(__DIR__.'/../UI', 'modulo');
Livewire::component('modulo-mi-componente', MiComponente::class);
```

Rutas (dentro del ServiceProvider en `boot()`):
```php
$this->app->booted(function () {
    Route::middleware(['web', 'auth:central'])->group(function () {
        Route::get('/central/ruta', MiComponente::class)->name('central.modulo.ruta');
    });
});
```

### Convención B (usada por Payments y Landings)

```
app/Modules/{Scope}/{Module}/
├── Http/Livewire/     ← Componentes Livewire
│   └── MiComponente.php
├── Providers/
│   └── XxxServiceProvider.php
├── Resources/views/   ← Vistas Blade
│   └── livewire/
│       └── mi-componente.blade.php
└── Routes/
    └── web.php        ← rutas separadas
```

Registro:
```php
$this->loadViewsFrom(__DIR__.'/../Resources/views', 'modulo');
$this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
```

---

## Reglas Livewire

### Permitido

Estado UI y delegación a Actions:
```php
public string $search = '';
public bool $showModal = false;

public function save(UpsertAction $action): void
{
    $this->validate([...]);
    $action->execute(new SomeDTO(...));
    session()->flash('status', __('Success.'));
}
```

### Prohibido

En Livewire:
- Reglas de negocio
- Creación directa de modelos (siempre usar Actions)
- Eventos de dominio
- Queries complejas (delegar a Actions/Services)

---

## Reglas Blade

Usar componentes Flux UI exclusivamente:
- `flux:heading`, `flux:subheading` — títulos
- `flux:card` — contenedores
- `flux:table`, `flux:table.columns`, `flux:table.rows` — tablas
- `flux:modal`, `flux:modal.close` — modales
- `flux:badge` — badges de estado
- `flux:input`, `flux:select`, `flux:textarea`, `flux:checkbox` — formularios
- `flux:button` — botones
- `flux:text` — textos

Flux UI se encarga de: temas, colores, variantes, loading states, animaciones.

No crear componentes Blade globales personalizados.

---

## Estados obligatorios por vista

Toda vista debe manejar:
1. **Empty state**: `@forelse ... @empty` con mensaje contextual
2. **Feedback**: `session('status')` para éxito/error post-acción
3. **Error**: `$this->addError('field', $e->getMessage())` para errores de validación
4. **Loading**: `wire:loading` en botones

---

## Rutas

Las rutas de tenant se agregan en `routes/tenant.php` dentro del grupo `auth`:
```php
Route::get('/settings/ruta', MiComponente::class)->name('tenant.modulo.ruta');
```

Las rutas de host se agregan via ServiceProvider con `Route::middleware(['web', 'auth:central'])`.

---

## Seguridad Multi-tenant

- Todo modelo tenant-scoped usa `BelongsToTenant` trait → RLS + scope automático
- Cross-tenant access debe retornar 404
- No confiar en UI para aislamiento

---

## Testing mínimo

Crear en `tests/Feature/{Module}Test.php`:

Cubrir:
- Render correcto del Livewire (assertStatus 200)
- Operaciones CRUD (crear, actualizar, eliminar)
- Validación (campos requeridos, formato inválido)
- Aislamiento tenant (Tenant A no ve datos de Tenant B)
- Edge cases (duplicados, registros inexistentes)

---

## Entrega

Reportar:

### Archivos creados
Lista completa.

### Archivos modificados
Lista completa.

### Dependencias usadas
Actions, DTOs y componentes Flux utilizados.

### Riesgos encontrados
Bloqueos o deuda técnica detectada.

### Checklist final
- [ ] Tests pasando: `php artisan test`
- [ ] Lint pasando: `composer lint:check`
- [ ] Actualizar `docs/UI/ROADMAP_UI.md` con lo completado

# COMMITS ATÓMICOS

Cada tarea completada debe generar un commit independiente.

Formato obligatorio:

Conventional Commits en español.

Formato:

```
tipo(scope): descripción
```

Tipos permitidos:

```
feat
fix
refactor
test
docs
chore
perf
security
```

Ejemplos:

```
feat(billing): agregar gestión de planes desde panel central

feat(tenant): implementar aislamiento de suscripciones

test(billing): agregar pruebas de autorización de reportes

fix(auth): corregir validación de permisos administrativos
```

Reglas:

Un commit = una intención.

No mezclar:

* features
* refactors
* fixes no relacionados

---