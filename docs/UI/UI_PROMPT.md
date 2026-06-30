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

| Aspecto | Convención |
|---|---|
| **Framework** | Livewire 4 |
| **UI Kit** | Flux UI (`flux:button`, `flux:card`, `flux:modal`, `flux:badge`, `flux:table`, `flux:input`, `flux:select`, `flux:textarea`, `flux:checkbox`, `flux:heading`, `flux:separator`, `flux:dropdown`) |
| **CSS** | Tailwind CSS 4 |
| **Layouts** | `#[Layout('layouts.central')]` para Host, `#[Layout('layouts.app')]` para Tenant, `#[Layout('layouts.marketing')]` para público |
| **Notificación UI** | `$this->dispatch('notify', message: __('...'))` |
| **Feedback** | `session()->flash('status', __('...'))` |

**NO crees componentes Blade globales** (`x-table`, `x-modal`, `x-alert`, `x-badge`, `x-skeleton`, `x-empty-state`, `x-layout.*`). El stack usa Flux UI para todo eso. Usa los componentes de Flux directamente.

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
