# Auditoría — Tenant/Workspace

> Fecha: 2026-08-28
> Estado: 🔴 Requiere intervención

## 1. Resumen ejecutivo

El módulo `Tenant/Workspace` agrupa las funciones de administración interna del inquilino (Gestión del Equipo, Centro de Notificaciones y Resumen de Uso/Cuotas). A pesar de su diseño sencillo, **presenta la vulnerabilidad más crítica descubierta hasta el momento (P0)**, la cual permite a cualquier empleado tomar el control total de la organización y expulsar a los administradores. Además, el sistema de cuotas subyacente exhibe un fallo arquitectónico (P1) que permite a los usuarios eludir los límites de su plan comercial.

## 2. Alcance

Se inspeccionaron:
- Interfaces Livewire (`TeamManagement`, `NotificationCenter`, `UsageOverview`)
- Rutas (`web.php`)
- Actions de notificaciones (`DeleteNotification`, `MarkNotificationAsRead`)
- Servicio subyacente de Cuotas (`QuotaManager`)

## 3. Arquitectura actual

- `TeamManagement`: Invoca Actions del módulo `Tenant/Access` (como `SendInvitation`, `UpdateTenantUserRole`) mediante Inyección de Dependencias.
- `NotificationCenter`: Modifica directamente el modelo local `Notification` vinculado al usuario.
- `UsageOverview`: Lee del servicio transversal `QuotaManager` (en `Platform/Tenancy`).

## 4. Dependencias

- `Tenant/Access` (Actions de invitación y modificación de roles).
- `Platform/Tenancy` (Gestor de cuotas).

## 5. Health Score

| Área         | Estado             |
| ------------ | ------------------ |
| Arquitectura | 🟡                 |
| Backend      | 🟡                 |
| Database     | 🟢                 |
| Frontend     | 🔴                 |
| Security     | 🔴                 |
| Testing      | 🔴                 |
| Performance  | 🟢                 |
| Operabilidad | 🟡                 |

**Estado general:** 🔴 Requiere intervención

## 6. Hallazgos

### [P0] Escalada Crítica de Privilegios y Toma de Control del Tenant (Account Takeover)

**Categoría:** Security / Frontend

**Ubicación:**
`app/Modules/Tenant/Workspace/Interface/Livewire/TeamManagement.php`
`app/Modules/Tenant/Workspace/Interface/Routes/web.php`

**Problema:**
La interfaz de gestión de equipos no implementa **ningún tipo de control de acceso**.

**Evidencia:**
La ruta `/team/members` no tiene middleware. Dentro de la clase `TeamManagement`, los métodos `invite()`, `updateRole()` y `revokeAccess()` omiten por completo llamadas a `$this->authorize('team:manage')`. Tampoco lo hacen los Actions internos a los que se delega el trabajo.

**Impacto:**
Cualquier empleado raso que conozca la URL (o navegue directamente al panel) puede:
1. Asignarse a sí mismo (u a otros) el rol `admin`.
2. Revocar el acceso de los fundadores/dueños del Tenant.
3. Invitar correos externos con privilegios máximos.

**Recomendación:**
Añadir inmediatamente el middleware `can:team:manage` a la ruta `tenant.team.index` en `web.php` y llamadas a `$this->authorize('team:manage')` en todas las funciones mutadoras del componente Livewire.

**Complejidad:** Muy Baja
**Prioridad:** Inmediata (Parada de línea)

---

### [P1] Evasión de Límites Comerciales por Volatilidad de Caché

**Categoría:** Architecture / Billing

**Ubicación:**
`app/Modules/Platform/Tenancy/Application/Services/QuotaManager.php:53` (Invocado por `UsageOverview.php`)

**Problema:**
El consumo del inquilino (ej. API keys, Staff, Bookings mensuales) se almacena *exclusivamente* en la caché de Laravel (`Cache::increment`).

**Evidencia:**
`forceIncrement()` utiliza `Cache::put()` con un TTL de 32 días para rastrear la métrica mensual.

**Impacto:**
Cualquier reinicio del servidor de Redis, desalojo por presión de memoria (Eviction), o limpieza de caché (`php artisan cache:clear`) **borrará por completo** el historial de consumo del mes de todos los Tenants. Esto resetea sus cuotas a 0, permitiendo abusar de los límites comerciales gratuitamente.

**Recomendación:**
Rediseñar `QuotaManager` para que el origen de la verdad (Source of Truth) de métricas críticas resida en una tabla de base de datos (`tenant_usage_logs`), y utilizar la caché solo como capa de lectura con *Write-Through* o *Write-Behind*. 

**Complejidad:** Media
**Prioridad:** Próximo sprint

---

### [P3] Exposición Innecesaria de Menú (UX/Security)

**Categoría:** Security / Frontend

**Ubicación:**
`app/Modules/Tenant/Workspace/Interface/Views/pages/dashboard.blade.php` (o donde resida el layout)

**Problema:**
Consecuencia del P0, no hay protección en la UI que oculte el link al panel de equipo.

**Evidencia:**
Se requiere asegurar que en los menús de navegación globales se envuelva el enlace al `TeamManagement` con `@can('team:manage')` para que un usuario sin permisos no vea siquiera la opción de entrar.

**Impacto:**
Confusión de usuarios, exploración de rutas prohibidas.

**Recomendación:**
Asegurar directivas de Blade en el layout de navegación.

**Complejidad:** Muy Baja
**Prioridad:** Baja

---

## 7. Matriz de riesgos

| ID   | Severidad | Categoría | Hallazgo | Impacto | Complejidad | Prioridad      |
| ---- | --------- | --------- | -------- | ------- | ----------- | -------------- |
| W001 | P0        | Security  | Ausencia total de Autorización en Team Management | Crítico | Muy Baja | Inmediata |
| W002 | P1        | Architect.| Cuotas almacenadas exclusivamente en Caché | Alto | Media | Próximo sprint |
| W003 | P3        | Frontend  | Ocultación condicional faltante en Menús | Bajo | Muy Baja | Baja |

## 8. Ruta de trabajo

1. **W001 — Blindaje Inmediato del Team Management (Hotfix)**
   - Esfuerzo: 5 minutos.
   - Acción: Añadir `$this->authorize('team:manage');` al inicio de cada acción mutadora y proteger la ruta.
   - Resultado: Cerrar la brecha de Account Takeover de los inquilinos.

2. **W002 — Migración de Cuotas a Base de Datos (Refactor)**
   - Esfuerzo: Alto (requiere migración, modelo `TenantUsage` y refactorizar la lógica del servicio `QuotaManager`).
   - Acción: Sincronizar el conteo a PostgreSQL y dejar la caché para lecturas rápidas.
   - Resultado: Asegurar el modelo de negocio (Billing) contra pérdida de datos volátiles.

## 9. Quick Wins

- La protección del IDOR (W001) requiere una sola línea en `routes/web.php` (`->middleware('can:team:manage')`).

## 10. Qué NO hacer

- **NO confiar en `auth()->user()->hasRole('admin')` dentro del Livewire.** Se debe usar el sistema de Gates de Laravel `$this->authorize('team:manage')` para respetar a los custom roles definidos en el módulo `Access`.

## 11. Cobertura de pruebas

El módulo adolece (de nuevo) de pruebas de integración para la capa HTTP/Livewire. La existencia de un simple test asertando un 403 Forbidden para un rol de empleado habría prevenido el pase a producción de la vulnerabilidad P0.

## 12. Conclusión

El módulo `Tenant/Workspace` falla estrepitosamente en proteger una de las superficies más críticas de cualquier SaaS: la administración de identidades delegada. Esto refuerza la observación sistémica en todo el código base auditado: **la lógica de negocio existe y funciona felizmente (happy path), pero las barreras perimetrales (Middleware, Gates, Policies) han sido ignoradas o implementadas de forma inoperante.**
