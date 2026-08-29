# Auditoría — Tenant/Compliance

> Fecha: 2026-08-28
> Estado: 🔴 Requiere intervención

## 1. Resumen ejecutivo

El módulo `Tenant/Compliance` proporciona capacidades de visibilidad, cumplimiento normativo y portabilidad de datos para las organizaciones (Audit Logs y Exportación de Datos).
Su arquitectura hace un uso correcto de las colas (Jobs asíncronos), eventos globales (Listener de identidades) y firmas criptográficas (Signed URLs para descargas). Sin embargo, el módulo presenta fallos severos de **Autorización (IDOR/Escalamiento de Privilegios)** en sus puntos de entrada web y vulnerabilidades graves de **estabilidad y performance (OOM)** en los Workers de Cola, lo que compromete tanto la seguridad de la información como la disponibilidad del sistema de background.

## 2. Alcance

Se inspeccionaron:
- Interfaces Livewire (`AuditLogViewer`, `DataExport`)
- Controladores y Rutas (`AuditDownloadController`, `web.php`)
- Jobs de background (`ExportAuditLogsJob`, `ExportTenantDataJob`)
- Actions de dominio (`RecordAuditLogAction`)
- Notificaciones (`AuditLogExportNotification`, `TenantDataExportNotification`)
- Modelos Eloquent (`AuditLog`)

## 3. Arquitectura actual

El sistema escucha pasivamente eventos de plataforma para registrar logs mediante `RecordAuditLogAction`. Los usuarios consultan esta data vía Livewire o solicitan reportes asíncronos. Los reportes son procesados por Jobs, que generan un archivo CSV o JSON almacenado localmente en `private` y notifican al usuario con una *Signed URL* temporal que el `AuditDownloadController` valida y sirve, protegiendo las descargas contra acceso anónimo.

## 4. Dependencias

- `Platform/Tenancy` (Contexto y persistencia multi-tenant).
- `Tenant/Access` (Usuarios y roles para filtrado y notificaciones).
- `Storage` local privado de Laravel.
- `Notifications` (Email + Signed URLs).
- `Central/Billing` y `Tenant/Experience` (Servicios de recolección de datos para la exportación completa).

## 5. Health Score

| Área         | Estado             |
| ------------ | ------------------ |
| Arquitectura | 🟢                 |
| Backend      | 🔴                 |
| Database     | 🟡                 |
| Frontend     | 🔴                 |
| Security     | 🔴                 |
| Testing      | 🔴                 |
| Performance  | 🔴                 |
| Operabilidad | 🟡                 |

**Estado general:** 🔴 Requiere intervención

## 6. Hallazgos

### [P0] Exposición de Audit Logs y Datos del Tenant (Autorización rota)

**Categoría:** Security

**Ubicación:**
`app/Modules/Tenant/Compliance/Interface/Routes/web.php`
`app/Modules/Tenant/Compliance/Interface/Livewire/AuditLogViewer.php`
`app/Modules/Tenant/Compliance/Interface/Livewire/DataExport.php`

**Problema:**
No existe protección de Middleware de Gates (`can:`) en las rutas `/audit` y `/settings/export`. Asimismo, los componentes Livewire carecen de llamadas a `$this->authorize('audit:read')` o similar.

**Evidencia:**
Cualquier miembro autenticado, sin importar su rol o permisos, puede acceder a estas vistas, leer todos los logs de auditoría de administradores, y encolar exportaciones masivas de datos confidenciales de facturación, configuraciones e identidad. 

**Impacto:**
Escalada de privilegios vertical y filtración de información sensible (Fuga de Datos Internos).

**Recomendación:**
Añadir middleware `can:audit:read` en `web.php` y llamadas a `$this->authorize()` en los métodos de renderizado y exportación de Livewire.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Out Of Memory (OOM) en ExportAuditLogsJob

**Categoría:** Performance / Backend

**Ubicación:**
`app/Modules/Tenant/Compliance/Application/Jobs/ExportAuditLogsJob.php:51`

**Problema:**
El Job extrae todos los registros del rango de fechas hacia la RAM del Worker y procesa el CSV en memoria.

**Evidencia:**
Usa `->get()` para extraer modelos Eloquent masivamente en lugar de `.chunk()` o `.cursor()`. Luego genera el CSV en el descriptor de memoria `php://temp` y usa `stream_get_contents()` cargando todo el texto en una variable `$content` antes de guardarlo.

**Impacto:**
En Tenants con alto volumen transaccional o exportaciones cercanas a 90 días, el array de modelos y el string final consumirán cientos de Megabytes, crasheando el proceso Worker (OOM) e interrumpiendo la cola de Horizon continuamente.

**Recomendación:**
Utilizar `$query->cursor()` para iterar sobre los resultados hidratando un modelo a la vez. Escribir directamente a un archivo local temporal en `/tmp` con `fputcsv`, subir el archivo a Storage y luego eliminar el temporal, o usar un Stream para `Storage::put()`.

**Complejidad:** Media
**Prioridad:** Inmediata

---

### [P1] Out Of Memory (OOM) en ExportTenantDataJob

**Categoría:** Performance / Backend

**Ubicación:**
`app/Modules/Tenant/Compliance/Application/Jobs/ExportTenantDataJob.php:45`

**Problema:**
La exportación global de datos del inquilino ensambla un mega-arreglo y lo codifica síncronamente.

**Evidencia:**
El script hace `array_merge()` de varios servicios pesados y luego aplica `json_encode($data)` para subirlo a Storage.

**Impacto:**
Crasheo garantizado de Workers por límite de memoria (Memory Exhausted Exception) en clientes consolidados.

**Recomendación:**
Refactorizar los servicios de exportación para que utilicen Generadores (`yield`) o escriban datos JSON iterativamente en un archivo de disco en lugar de retornar arrays gigantes.

**Complejidad:** Alta
**Prioridad:** Próximo sprint

---

### [P2] N+1 Encubierto y Sobrecarga de Memoria en Frontend (Filtro de Usuarios)

**Categoría:** Frontend / Database

**Ubicación:**
`app/Modules/Tenant/Compliance/Interface/Livewire/AuditLogViewer.php:97`

**Problema:**
La UI carga todos los usuarios de la base de datos para renderizar un dropdown estático.

**Evidencia:**
Se inyecta `'users' => User::select(['id', 'name'])->get()` en cada ciclo de render de Livewire. 

**Impacto:**
Si el Tenant tiene 10,000 usuarios, el payload HTML explota, el navegador se bloquea, y la base de datos transfiere un volumen de red masivo e inútil repetidas veces por segundo.

**Recomendación:**
Remplazar el select estático HTML por un componente de búsqueda asíncrona (como `flux:select` dinámico o AJAX) para buscar usuarios por nombre cuando sea necesario.

**Complejidad:** Media
**Prioridad:** Próximo sprint

---

## 7. Matriz de riesgos

| ID   | Severidad | Categoría | Hallazgo | Impacto | Complejidad | Prioridad      |
| ---- | --------- | --------- | -------- | ------- | ----------- | -------------- |
| C001 | P0        | Security  | Autorización faltante en rutas y UI de Compliance | Alto | Baja | Inmediata |
| C002 | P1        | Backend   | OOM en Job de Exportación de Auditoría | Alto | Media | Inmediata |
| C003 | P1        | Backend   | OOM en Job de Exportación de Datos Tenant | Alto | Alta | Próximo sprint |
| C004 | P2        | Frontend  | Descarga completa de tabla Users en Render | Medio | Media | Próximo sprint |

## 8. Ruta de trabajo

1. **C001 — Blindar Rutas y Componentes (Seguridad P0)**
   - Dependencias: ninguna.
   - Esfuerzo: Muy Bajo.
   - Riesgo: Alto.
   - Resultado: Impedir acceso ilegítimo al visor y a la emisión de exportaciones asíncronas.

2. **C002 — Refactorizar ExportAuditLogsJob (OOM P1)**
   - Dependencias: C001
   - Esfuerzo: Medio
   - Riesgo: Medio
   - Resultado: Estabilizar el sistema de colas, evitando caídas por Memory Limits.

3. **C003 — Rediseñar ExportTenantDataJob (OOM P1)**
   - Dependencias: C001
   - Esfuerzo: Alto (requiere reescribir Interfaces de Servicios de Exportación).
   - Riesgo: Medio
   - Resultado: Evitar bloqueos al generar portabilidad de datos para grandes cuentas.

4. **C004 — Optimizar Búsqueda de Usuarios en UI**
   - Dependencias: ninguna.
   - Esfuerzo: Medio.
   - Riesgo: Bajo.
   - Resultado: Reactividad óptima en la pantalla de Auditoría.

## 9. Quick Wins

- Agregar middlewares de gates (`can:audit:read`, `can:settings:manage`) a `routes/web.php` en lugar de parchear sólo el Livewire, cortando el acceso de forma perimetral.

## 10. Qué NO hacer

- **NO utilizar el Filesystem Público.** El controller `AuditDownloadController` usa `disk('private')` y Signed URLs de forma nativa. Esto es excelente. No retroceder a URLs públicas ofuscadas.
- **NO particionar `tenant_audit_logs` en múltiples tablas.** Aunque crezca, la estrategia Multi-tenant RLS de Postgres escala muy bien con índices apropiados (como el índice combinado `tenant_id, created_at` que debería auditarse en la migración).

## 11. Cobertura de pruebas

El módulo adolece de una falta grave de cobertura:
- **No existen tests para los controladores, vistas Livewire o Jobs de esta capa.**
- Sólo existe un test de integración general (`TenantIdentityEventAuditTest.php`) que comprueba que un Listener genera el log.
- Faltan **Tests de Autorización Negativa** (esenciales para haber evitado el bug C001).
- Faltan **Tests del Job con Volúmenes Altos** (para haber detectado el OOM).

## 12. Riesgos pendientes

La retención de la tabla `tenant_audit_logs`. Al no existir una tarea programada (`Cron` / `Command`) que purgue los logs viejos (por ejemplo, `> 365 días`), la base de datos principal crecerá de forma indefinida, impactando el costo de almacenamiento de RDS/CloudSQL.

## 13. Conclusión

El módulo `Tenant/Compliance` cumple las funciones de negocio esperadas, pero sus implementaciones no están preparadas para operar en producción bajo cargas reales. La exposición abierta de sus rutas Livewire (P0) debe corregirse inmediatamente, seguido de la reconstrucción urgente de sus Jobs (P1) para que operen mediante Streams/Cursores y garanticen la estabilidad de los Workers de Laravel.
