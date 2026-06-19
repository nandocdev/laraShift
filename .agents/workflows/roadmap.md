---
description: Este proceso busca mantener consistencia arquitectónica, aislamiento multi-tenant y facilidad de mantenimiento.
---


---

### 1. Definir el caso de uso y contexto

Antes de escribir una sola línea de código:

* Identificar si pertenece a **Central** o **Tenant**.
* Definir actores involucrados.
* Definir reglas de negocio.
* Identificar impacto sobre:

  * Tenancy
  * Billing
  * Features
  * Quotas
  * Auditoría
  * Seguridad

Preguntas obligatorias:

* ¿Quién usa esta funcionalidad?
* ¿Qué problema resuelve?
* ¿Puede generar acceso cross-tenant?
* ¿Requiere permisos?
* ¿Requiere suscripción activa?

Ejemplo:

```text
Tenant/Contacts

Caso de uso:
Permitir gestionar contactos comerciales.

Actores:
- Admin
- Manager

Restricciones:
- Tenant aislado
- Plan Pro requerido
```

---

### 2. Crear la estructura del módulo

Crear el bounded context completo desde el inicio.

```text
app/Modules/Tenant/Contacts/
├── Actions/
├── DTOs/
├── Models/
├── Policies/
├── Livewire/
├── Events/
├── Listeners/
├── Jobs/
├── Exceptions/
├── Providers/
└── Tests/
```

Evitar crear carpetas cuando "hagan falta".

La estructura debe reflejar la arquitectura desde el día uno.

---

### 3. Diseñar el modelo de datos

Antes de crear migraciones:

* Definir entidades.
* Definir relaciones.
* Definir ownership.
* Definir índices.

Ejemplo:

```text
Contact
 └─ belongsTo Tenant

ContactNote
 └─ belongsTo Contact
```

Validar:

* tenant_id obligatorio
* claves foráneas
* índices compuestos

Ejemplo:

```php
$table->foreignId('tenant_id')->constrained();

$table->index(['tenant_id', 'id']);
$table->index(['tenant_id', 'created_at']);
```

Pensar en consultas reales, no solo en almacenamiento.

---

### 4. Crear migraciones y modelos

Crear:

```bash
php artisan make:model Contact -m
```

Responsabilidades del modelo:

* Relaciones
* Casts
* Scopes
* Helpers simples

No incluir:

* Flujos de negocio
* Reglas complejas
* Procesos de aprobación
* Integraciones

Los modelos representan persistencia.

Nada más.

---

### 5. Diseñar DTOs

Todo dato que entre a la capa de negocio debe pasar por DTOs.

Ejemplo:

```php
final class CreateContactData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
    ) {}
}
```

Beneficios:

* Tipado fuerte
* Validación consistente
* Contratos claros
* Menos arrays mágicos

---

### 6. Implementar Actions

Aquí vive la lógica de negocio.

Ejemplo:

```php
final readonly class CreateContactAction
{
    public function execute(
        CreateContactData $data
    ): Contact {
        return DB::transaction(function () use ($data) {

            return Contact::create([
                //
            ]);
        });
    }
}
```

Validar:

* Permisos
* Quotas
* Features
* Reglas de negocio

Toda lógica importante debe terminar aquí.

Nunca en:

* Controllers
* Livewire
* Models

---

### 7. Crear Events y Listeners

Solo cuando exista desacoplamiento real.

Correcto:

```text
ContactCreated
 ├─ SendWelcomeEmail
 ├─ SyncCRM
 └─ RegisterAuditLog
```

Incorrecto:

```text
ContactCreated
 └─ CreateContactListener
```

Si el listener es obligatorio para completar el caso de uso:

```text
Action → llamada directa
```

No evento.

---

### 8. Implementar componentes Livewire

Responsabilidades:

* Estado UI
* Interacciones
* Validación visual

No:

* Reglas de negocio
* Persistencia compleja
* Integraciones

Ejemplo:

```php
public function save(
    CreateContactAction $action
): void
{
    $action->execute(
        CreateContactData::from(...)
    );
}
```

Livewire orquesta.

Action decide.

---

### 9. Crear vistas

Mantener vistas:

* Simples
* Declarativas
* Sin lógica de negocio

Evitar:

```blade
@if($user->plan->canUseFeature())
```

Preferir:

```php
$this->canCreateContacts
```

La vista consume estado preparado.

No calcula reglas.

---

### 10. Definir autorización

Crear Policy desde el inicio.

```php
ContactPolicy
```

Definir:

```php
view()
create()
update()
delete()
```

Validar ownership tenant.

Resultado esperado ante acceso inválido:

```text
404
```

No:

```text
403
```

---

### 11. Registrar Service Provider

Responsabilidades:

* Rutas
* Policies
* Eventos
* Configuración del módulo

Ejemplo:

```php
ContactsServiceProvider
```

Mantener el módulo autocontenido.

---

### 12. Definir rutas

Separar claramente:

```php
routes/tenant.php
routes/central.php
```

Aplicar middleware correcto:

```php
InitializeTenancy
ApplyTenantScopes
Authenticate
CheckSubscription
```

Nunca invertir este orden.

---

### 13. Escribir pruebas

El módulo no está terminado sin tests.

Mínimo:

#### Feature Tests

```text
Crear contacto
Editar contacto
Eliminar contacto
```

#### Isolation Tests

```text
Tenant A -> recurso Tenant B

Resultado esperado: 404
```

#### Security Tests

```text
Permisos
Roles
Ownership
```

#### Quota Tests

```text
Límite alcanzado
Bloqueo correcto
```

#### Idempotency Tests

```text
Duplicar evento
Duplicar webhook
Duplicar job
```

---

### 14. Registrar autoload y descubrimiento

Si el módulo requiere namespaces propios:

```json
"autoload": {
    "psr-4": {
        "App\\Modules\\": "app/Modules/"
    }
}
```

Ejecutar:

```bash
composer dump-autoload
```

Verificar:

```bash
php artisan optimize:clear
```

---

### 15. Ejecutar checklist arquitectónico

Antes de considerar terminado:

#### Arquitectura

* [ ] Pertenece al contexto correcto
* [ ] No existen Services genéricos
* [ ] No existen Helpers mágicos
* [ ] Actions contienen la lógica

#### Seguridad

* [ ] tenant_id presente
* [ ] Policies implementadas
* [ ] Isolation validada
* [ ] Sin acceso cross-tenant

#### Performance

* [ ] Índices creados
* [ ] Sin N+1
* [ ] Eager loading revisado

#### Operación

* [ ] Logs relevantes
* [ ] Eventos auditables
* [ ] Manejo de errores

#### Testing

* [ ] Feature tests
* [ ] Isolation tests
* [ ] Security tests
* [ ] Quota tests

---

### 16. Verificación final

Ejecutar:

```bash
composer test
php artisan test
php artisan pint
php artisan optimise
```

Verificar:

* Sin errores.
* Sin warnings críticos.
* Sin consultas N+1.
* Sin fallos de tenancy.

---

