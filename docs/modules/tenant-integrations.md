# Auditoría — Tenant/Integrations

> Fecha: 2026-08-28
> Estado: 🔴 Requiere intervención

## 1. Resumen ejecutivo

El módulo `Tenant/Integrations` maneja actualmente la integración de servicios de correo (SMTP) propios de cada inquilino. Aunque es un módulo de tamaño reducido, su diseño presenta una **falla arquitectónica crítica [P0] de filtración de estado cruzado entre Tenants si el proyecto opera bajo Laravel Octane**. Adicionalmente, hereda las fallas de la política de acceso de configuraciones (Settings) y expone pasivamente detalles de infraestructura de correo a usuarios no autorizados (P1).

## 2. Alcance

Se han auditado:
- Interfaz Livewire (`SmtpSettings.php`) y su vista.
- Actions (`UpdateTenantSmtp.php`).
- Servicios (`TenantMailerService.php`).
- Rutas (`web.php`).

## 3. Arquitectura actual

El módulo reutiliza el modelo `TenantSetting` (del módulo `Experience`) para almacenar las credenciales SMTP. Emplea un servicio especializado (`TenantMailerService`) que inyecta dinámicamente configuraciones en el contenedor de Laravel para emitir correos de prueba sin afectar la configuración general del entorno.

## 4. Dependencias

- `Tenant/Experience` (Lectura y escritura de `TenantSetting`, Políticas y DTOs SMTP).
- `Tenant/Compliance` (Generación de registros de auditoría al actualizar credenciales).
- `Illuminate\Support\Facades\Mail` (Gestor de correos de Laravel).

## 5. Health Score

| Área         | Estado             |
| ------------ | ------------------ |
| Arquitectura | 🟡                 |
| Backend      | 🔴                 |
| Database     | 🟢                 |
| Frontend     | 🟡                 |
| Security     | 🔴                 |
| Testing      | 🔴                 |
| Performance  | 🟢                 |
| Operabilidad | 🟡                 |

**Estado general:** 🔴 Requiere intervención

## 6. Hallazgos

### [P0] Filtración de estado SMTP entre Tenants (Vulnerabilidad Octane)

**Categoría:** Security / Architecture

**Ubicación:**
`app/Modules/Tenant/Integrations/Application/Services/TenantMailerService.php:38`

**Problema:**
El servicio altera dinámicamente el `Config` de Laravel y crea una instancia de mailer (`Mail::mailer('tenant_test')`). Tras ejecutar el callback, el servicio anula la configuración, pero **nunca purga la instancia del MailManager**.

**Evidencia:**
Laravel cachea los mailers ya resueltos en memoria (`app('mail.manager')->mailers`). Si la aplicación corre en Laravel Octane (FrankenPHP/Swoole/RoadRunner), la instancia `tenant_test` configurada por el Tenant A permanecerá viva. Cuando el Tenant B llame a `testConnection`, Octane devolverá el mailer en caché del Tenant A, enviando el correo a través de los servidores del Tenant A en lugar de los del Tenant B.

**Impacto:**
Fuga cruzada de datos (Cross-Tenant Leak) severa, uso indebido de infraestructura y fallos graves de integridad en el envío de notificaciones.

**Recomendación:**
Añadir `app('mail.manager')->purge('tenant_test');` o `forgetMailers()` explícitamente en el bloque `finally` para forzar a Laravel a olvidar la instancia inyectada.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Fuga de información de infraestructura SMTP en la UI

**Categoría:** Security / Frontend

**Ubicación:**
`app/Modules/Tenant/Integrations/Interface/Livewire/SmtpSettings.php:37`

**Problema:**
El método `mount()` del componente Livewire no aplica comprobaciones de autorización.

**Evidencia:**
Las funciones `save()` y `testConnection()` llaman correctamente a `$this->authorizeManagement()`, pero `mount()` hidrata las propiedades (`smtp_host`, `smtp_user`, etc.) directamente y renderiza la vista.

**Impacto:**
Cualquier usuario autenticado del Tenant, independientemente de sus roles (ej. un empleado de bajo rango), puede navegar a `/settings/smtp` (pues la ruta tampoco tiene middleware) y visualizar el host de correo, el puerto, el usuario y la dirección de origen. Aunque el password no se expone, revelar infraestructura interna es una vulnerabilidad de Information Disclosure.

**Recomendación:**
Agregar `$this->authorizeManagement();` en la primera línea de `mount()` o aplicar el middleware `can:settings:manage` directamente en `web.php`.

**Complejidad:** Baja
**Prioridad:** Inmediata

---

### [P1] Herencia de Política Rota (Broken Access Control)

**Categoría:** Security

**Ubicación:**
`app/Modules/Tenant/Experience/Application/Actions/EnsureUserCanManageTenantSettings.php` (Usado en Integrations)

**Problema:**
Como el módulo depende del Action de Experience para validar permisos, arrastra su mismo bug estructural.

**Evidencia:**
El Action llama a `Gate::authorize('update', $settings);`, cuya Policy requiere falsamente el permiso literal `'manage settings'` en lugar del real `'settings:manage'`.

**Impacto:**
Nadie excepto el rol estático `admin` podrá probar o actualizar credenciales SMTP. Los Custom Roles fallarán con 403 Forbidden.

**Recomendación:**
Este fallo se resolverá automáticamente al corregir la Policy en el módulo `Tenant/Experience`, pero se documenta aquí como riesgo activo transversal.

**Complejidad:** Baja
**Prioridad:** (Delegado a Experience)

---

## 7. Matriz de riesgos

| ID   | Severidad | Categoría | Hallazgo | Impacto | Complejidad | Prioridad      |
| ---- | --------- | --------- | -------- | ------- | ----------- | -------------- |
| I001 | P0        | Security  | Fuga de mailer en caché (Octane Cross-Tenant Leak) | Alto | Baja | Inmediata |
| I002 | P1        | Security  | Information Disclosure SMTP en render (Livewire) | Medio | Baja | Inmediata |
| I003 | P1        | Security  | Autorización bloqueada para Custom Roles (Herencia)| Medio | Baja | Próximo sprint |

## 8. Ruta de trabajo

1. **I001 — Purgar caché de MailManager (Estabilidad Octane)**
   - Dependencias: ninguna.
   - Esfuerzo: Muy Bajo.
   - Riesgo: Bajo.
   - Resultado: Garantizar aislamiento del transporte de correo entre clientes (RLS en memoria).

2. **I002 — Proteger Ruta y Mount en Livewire (Privacidad)**
   - Dependencias: ninguna.
   - Esfuerzo: Muy Bajo.
   - Riesgo: Bajo.
   - Resultado: Ocultar detalles técnicos a miembros no autorizados de las organizaciones.

3. **I003 — Corrección de `TenantSettingPolicy` (Delegado)**
   - Dependencias: Módulo Experience.
   - Esfuerzo: N/A.
   - Riesgo: N/A.

## 9. Quick Wins

- Escribir `app('mail.manager')->purge('tenant_test');` en el bloque `finally` de `TenantMailerService`.
- Incluir la validación de Gate al inicio del `mount()` en el componente `SmtpSettings`.

## 10. Qué NO hacer

- **NO utilizar variables de entorno (getenv) o funciones de configuración global `.env`** dinámicas para SMTP en este servicio, pues romperían el estado global de PHP de forma catastrófica en Swoole/Octane. El uso del alias virtual `tenant_test` con el Manager de Laravel es el camino correcto, siempre que se purgue de memoria al finalizar.
- **NO despachar la prueba de conexión a colas (Jobs).** La naturaleza del botón "Test Connection" exige feedback sincrónico para el usuario que está configurando las credenciales. El delay actual es aceptable.

## 11. Cobertura de pruebas

- Existen tests para SMTP settings en `tests/Feature/TenantSettingsTest.php` y `tests/Feature/Tenant/Integrations/TenantSmtpActionsTest.php` (vistos en exploraciones previas de directorios). Sin embargo, el test del Action comprueba seguramente que un Action actualiza la DB.
- **Test Faltante Crítico:** Falta un test diseñado para Octane / Aislamiento (similar a `CrossTenantLeakTest` pero para singletons de Laravel como el MailManager) para prevenir regresiones. 

## 12. Riesgos pendientes

El framework Laravel a veces retrasa la reconexión de sockets de transporte SMTP compartidos. Si la purga del MailManager no cierra explícitamente la conexión del socket de Symfony Mailer, podría ocurrir un "Connection Reuse" en capas más bajas. Es recomendable que al purgar el servicio de mail también se instruya a Symfony a desconectar el transporte.

## 13. Conclusión

El módulo `Integrations` es funcional y simple, pero introduce un peligroso patrón de fuga de datos en memoria (Octane Leak) que viola los preceptos básicos de aislamiento multitenant. Esto y la falta de protección de lectura en la UI son parches urgentes de unas pocas líneas de código. Al corregirse, será un módulo sumamente sólido.
