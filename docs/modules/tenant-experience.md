# Auditoría — Tenant/Experience

> Fecha: 2026-08-28
> Estado: 🔴 Requiere intervención

## 1. Resumen ejecutivo

El módulo `Tenant/Experience` gestiona la cara visible del tenant (Branding, Localización, Configuración SMTP) y provee un constructor de Landing Pages (Builder). 

El módulo encapsula correctamente la lógica de renderizado en Actions (`RenderLanding`) y protege la inyección de vistas. Sin embargo, sufre de **defectos graves en el Control de Acceso (Autorización rota P0 y Política mal configurada P1)** que permiten la manipulación no autorizada de las páginas de destino por usuarios sin privilegios. Adicionalmente, expone la base de datos a un riesgo de denegación de servicio (DoS) por falta de caché en el controlador público de visitas (P1).

## 2. Alcance

Se auditaron los siguientes componentes:
- Modelos (`Landing`, `LandingVersion`, `TenantSetting`)
- Interfaces Livewire (`LandingBuilder`, `BrandingSettings`, `LocalizationSettings`)
- Controladores (`ServeTenantLandingController`)
- Actions de lógica de negocio (`PublishLanding`, `RenderLanding`, SMTP actions)
- Políticas de acceso (`TenantSettingPolicy`)
- Rutas (`web.php`)

## 3. Arquitectura actual

El diseño separa la configuración del inquilino (Settings) de la experiencia pública (Landings). 
- Los *Settings* utilizan un único registro por tenant (`TenantSetting`) y la configuración SMTP intercepta los correos salientes (gestionado en otro lugar).
- Las *Landings* almacenan el diseño estructural (bloques y tema en JSON) y generan un `published_html` estático mediante Blade. Esta cadena HTML se guarda en base de datos para servir a los visitantes sin renderizado en tiempo real. 

## 4. Dependencias

- `Platform/Tenancy` (Aislamiento de base de datos RLS y resolución de dominio).
- `Tenant/Access` (Resolución de permisos y roles).
- `Spatie/Laravel-Permission` (Validación de roles).

## 5. Health Score

| Área         | Estado             |
| ------------ | ------------------ |
| Arquitectura | 🟢                 |
| Backend      | 🟡                 |
| Database     | 🟢                 |
| Frontend     | 🔴                 |
| Security     | 🔴                 |
| Testing      | 🔴                 |
| Performance  | 🔴                 |
| Operabilidad | 🟡                 |

**Estado general:** 🔴 Requiere intervención

## 6. Hallazgos

### [P0] Manipulación no autorizada del Landing Builder (IDOR Vertical)

**Categoría:** Security / Frontend

**Ubicación:**
`app/Modules/Tenant/Experience/Interface/Livewire/LandingBuilder.php`
`app/Modules/Tenant/Experience/Interface/Routes/web.php`

**Problema:**
El componente `LandingBuilder` y su ruta no poseen verificación de permisos. 

**Evidencia:**
Cualquier usuario autenticado en el Tenant puede navegar a `/landings/{landing}/builder` y ejecutar los métodos `save()` y `publish()`. No hay chequeos de `Gate::authorize()` ni middlewares `can:` que protejan la edición del portal público.

**Impacto:**
Defacement (desfiguración del sitio) o Phishing. Un usuario con nivel de acceso mínimo puede alterar la landing page pública de la organización para redirigir tráfico o dañar la imagen de la marca.

**Recomendación:**
Aplicar `$this->authorize('settings:manage')` o un permiso dedicado (`landings:manage`) en los métodos que modifican el estado, y proteger la ruta con el middleware de Laravel.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Broken Access Control por Nombre de Permiso Incorrecto

**Categoría:** Security / Backend

**Ubicación:**
`app/Modules/Tenant/Experience/Domain/Policies/TenantSettingPolicy.php:20`

**Problema:**
La política autoriza a usuarios con rol `admin` o con el permiso explícito `'manage settings'`.

**Evidencia:**
La definición oficial de permisos en `RoleManagement` establece que el permiso se llama `'settings:manage'`, no `'manage settings'`. Como resultado, el chequeo `$user->hasPermissionTo('manage settings')` siempre fallará (lanzando `PermissionDoesNotExist`). 

**Impacto:**
Solo el rol estático "admin" puede editar Settings. Cualquier rol personalizado al que se le asigne la capacidad de administrar configuraciones recibirá un error 403 Forbidden.

**Recomendación:**
Corregir la cadena a `$user->hasPermissionTo('settings:manage')`.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Riesgo de Denegación de Servicio (DoS) por falta de Caché Pública

**Categoría:** Performance

**Ubicación:**
`app/Modules/Tenant/Experience/Interface/Http/Controllers/ServeTenantLandingController.php:23`

**Problema:**
El endpoint raíz (`/`) consulta el HTML publicado directamente desde PostgreSQL en cada visita anónima.

**Evidencia:**
El controlador hace `Landing::where(...)->first()` sin mecanismos de caché. 

**Impacto:**
Si la landing page pública del Tenant experimenta un pico de tráfico, se saturarán las conexiones a la base de datos (Postgres). El HTML estático no debería requerir hidratación de Eloquent por request.

**Recomendación:**
Almacenar el resultado con `Cache::rememberForever("tenant:{$tenantId}:landing:html", ...)` y purgar esta caché (`Cache::forget(...)`) desde el Action `PublishLanding`.

**Complejidad:** Baja
**Prioridad:** Próximo sprint

---

### [P2] Pérdida de trazabilidad de publicación (Publisher ID)

**Categoría:** Architecture / Operabilidad

**Ubicación:**
`app/Modules/Tenant/Experience/Interface/Livewire/LandingBuilder.php:57`

**Problema:**
El builder asume que quien publica es un administrador de plataforma (Central) en modo "Impersonate".

**Evidencia:**
`$publisherId = auth('central')->check() ? auth('central')->id() : null;`. Si el propio dueño del Tenant edita y publica su landing page, el campo `published_by` se guardará como `null`.

**Impacto:**
Imposibilidad de auditar qué usuario específico del Tenant realizó la publicación, arruinando la cadena de responsabilidad.

**Recomendación:**
Modificar la migración/esquema de `LandingVersion` para soportar tanto `central_user_id` como `tenant_user_id` de forma independiente, y registrar correctamente quién efectuó la acción.

**Complejidad:** Media
**Prioridad:** Backlog

---

## 7. Matriz de riesgos

| ID   | Severidad | Categoría | Hallazgo | Impacto | Complejidad | Prioridad      |
| ---- | --------- | --------- | -------- | ------- | ----------- | -------------- |
| E001 | P0        | Security  | Autorización faltante en Landing Builder | Alto | Baja | Inmediata |
| E002 | P1        | Security  | Nombre de permiso incorrecto en Policy | Medio | Baja | Inmediata |
| E003 | P1        | Perform.  | Renderizado público sin caché (Riesgo DoS) | Alto | Baja | Próximo sprint |
| E004 | P2        | Architect.| Pérdida de trazabilidad en autor de Landing | Bajo | Media | Backlog |

## 8. Ruta de trabajo

1. **E001 & E002 — Sanitización de Permisos (Seguridad Crítica)**
   - Dependencias: ninguna.
   - Esfuerzo: Muy Bajo.
   - Riesgo: Bajo.
   - Resultado: Asegurar el portal público contra alteraciones internas no autorizadas y habilitar acceso real a roles custom.

2. **E003 — Implementación de Caché en Frontend Público**
   - Dependencias: ninguna.
   - Esfuerzo: Bajo.
   - Riesgo: Bajo.
   - Resultado: Soportar miles de requests concurrentes a las páginas de aterrizaje sin afectar la DB.

3. **E004 — Corrección de Trazabilidad en Publicación**
   - Dependencias: ninguna.
   - Esfuerzo: Medio.
   - Riesgo: Bajo.
   - Resultado: Mantener logs de versiones precisos sobre los autores.

## 9. Quick Wins

- Cambiar `'manage settings'` por `'settings:manage'` en `TenantSettingPolicy.php`. Toma menos de un minuto y restaura la funcionalidad core de roles personalizados.
- Envolver la consulta de `ServeTenantLandingController` en un `Cache::remember()`.

## 10. Qué NO hacer

- **NO forzar una renderización Livewire para las landings públicas.** Guardar el HTML pre-renderizado (`published_html`) en `PublishLanding` es una decisión excelente para la velocidad del usuario final. Mantener esta arquitectura y solo inyectarle Redis (Caché).
- **NO crear un guard adicional.** La lectura dual de `auth('central')` en el Builder es un anti-patrón en un componente Tenant. Pasar el ID del publicador debe hacerse limpiamente desde la sesión actual (sea impersonada o no) mediante logs de auditoría centralizados o dos columnas foráneas explícitas.

## 11. Cobertura de pruebas

La cobertura es deficiente:
- `TenantSettingsTest` existe y valida funcionalidad base del modelo (como encriptación de contraseñas), pero no cubre la protección por Policies. Un test con un usuario no administrador hubiera revelado el error tipográfico de la Policy (E002).
- No hay pruebas HTTP/Livewire para el controlador de `Landing` o el `LandingBuilder`.

## 12. Riesgos pendientes

Si las configuraciones de `smtp_password` se manejan en texto claro al renderizar componentes Livewire en algún momento no identificado, podrían fugarse al DOM del navegador. La implementación actual del Action que descifra esto (`GetTenantSmtpSettings`) debe usarse con cuidado, nunca pasándola directamente a atributos de componentes públicos.

## 13. Conclusión

El módulo `Tenant/Experience` cumple con su función estética y estructural, pero requiere parches de seguridad críticos inmediatos. El error en la nomenclatura de permisos y la desprotección total del generador de landing pages ponen en riesgo tanto el uso del sistema como la imagen pública de los clientes finales. Con un esfuerzo mínimo (Quick Wins), el módulo pasará rápidamente a un estado estable y saludable.
