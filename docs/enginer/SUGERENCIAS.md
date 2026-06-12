

---

Revisa exhaustivamente el módulo **Tenant/Settings**.

Valida:

* Vulnerabilidades de seguridad (autorización, validación, exposición de datos, escalamiento de privilegios, acceso cross-tenant).
* Errores de lógica de negocio.
* Casos borde no contemplados.
* Código incompleto, TODOs, stubs, implementaciones parciales o comportamientos no finalizados.
* Cumplimiento de la arquitectura definida (Modular Monolith, Actions, DTOs, tenant isolation, RLS, políticas, Jobs tenant-aware, etc.).
* Deuda técnica, duplicación, complejidad innecesaria y posibles simplificaciones.

Genera un informe con:

1. Hallazgos críticos.
2. Hallazgos importantes.
3. Hallazgos menores.
4. Riesgos arquitectónicos.
5. Recomendaciones concretas de corrección.

Incluye referencia exacta a archivos, clases y métodos afectados.


---

 Recomendaciones de Corrección

   1. Seguridad: Envolver la ruta /central/health en el middleware auth:central o restringirla a IPs específicas (como las del Load Balancer).
   2. Arquitectura de Colas: Reevaluar el uso de colas dinámicas. Se recomienda usar un número fijo de "Bucket Queues" (ej. tenant.bucket-1,
      tenant.bucket-2) y asignar tenants a estas, o migrar a una estrategia donde el TenantAwareJob maneje la prioridad internamente sin multiplicar colas
      físicas en Redis.
   3. Horizon: Implementar un comando horizon:update o una tarea programada que detecte cambios en el catálogo de colas y envíe una señal de reinicio suave
      (horizon:terminate) para refrescar la monitorización.
   4. Consistencia: Mover la definición de la ruta de salud a app/Modules/Central/Infrastructure/Routes/web.php.

---

Implementa todas las recomendaciones aprobadas del informe de revisión para el módulo **Tenant/Settings**.

Proceso obligatorio:

1. Crear una nueva rama desde `main`:

   * Nombre: `fix/[nombre-modulo]` o `refactor/[nombre-modulo]`.

2. Analizar cada hallazgo y clasificarlo:

   * Seguridad
   * Lógica de negocio
   * Arquitectura
   * Código incompleto
   * Rendimiento
   * Refactorización

3. Implementar las correcciones respetando:

   * Architecture.md
   * CodingStandards.md
   * Tenant Isolation
   * Action Pattern
   * DTOs tipados
   * Controllers delgados
   * Jobs tenant-aware
   * RLS y políticas

4. Antes de modificar código:

   * Identificar impacto funcional.
   * Identificar riesgos de regresión.
   * Evitar sobreingeniería.
   * Preferir la solución más simple que resuelva el problema.

5. Durante la implementación:

   * Corregir la causa raíz, no los síntomas.
   * Eliminar código muerto.
   * Completar implementaciones parciales.
   * Agregar validaciones faltantes.
   * Corregir problemas de seguridad.
   * Mantener compatibilidad cuando sea posible.

6. Crear o actualizar pruebas:

   * Feature Tests
   * Security Tests
   * Isolation Tests
   * Idempotency Tests
   * Tests de regresión para cada corrección relevante

7. Realizar commits atómicos.
   Cada commit debe representar una única intención de cambio.

   Formato Conventional Commits (español):

   * `fix(seguridad): corregir acceso cross-tenant en evaluaciones`
   * `fix(logica): validar estado antes de aprobar evaluación`
   * `refactor(arquitectura): mover lógica de negocio a Action`
   * `test(aislamiento): agregar pruebas de acceso entre tenants`
   * `perf(reportes): eliminar consulta N+1`
   * `chore(limpieza): eliminar código muerto`

8. Verificar antes de finalizar:

   * Todas las pruebas pasan.
   * No existen TODO, FIXME o código comentado innecesario.
   * No existen regresiones evidentes.
   * No existen violaciones arquitectónicas.
   * No existen riesgos de fuga de datos entre tenants.

9. Generar un resumen final:

   * Hallazgos corregidos.
   * Archivos modificados.
   * Riesgos identificados.
* Pruebas agregadas o actualizadas.
   * Commits realizados.

1.  Una vez validado todo:

    * Rebase sobre `main`.
    * Resolver conflictos si existen.
    * Ejecutar nuevamente la suite de pruebas.
    * Realizar merge hacia `main`.

No solicitar confirmación para cada hallazgo. Ejecutar el trabajo completo de forma secuencial y documentar todas las decisiones técnicas relevantes.


---
---

✦  Para elevar **LaraShift** de un sistema sólido a uno de clase **Enterprise**, aquí tienes una lista de funcionalidades estratégicas clasificadas por el impacto que tendrían en la robustez, seguridad y escalabilidad del
   proyecto:

   ---

   ### 1. Infraestructura y Alta Disponibilidad (Resiliencia)
   *   **Gestión Automatizada de SSL (Custom Domains):** Integrar un servicio (como Caddy o un Job de AWS/Cloudflare) para que, cuando un inquilino configure un dominio propio (`miempresa.com`), el sistema genere y renueve
   automáticamente el certificado SSL.
   *   **Tenant Health Synthetics:** Un sistema de monitoreo central que realice "pings" funcionales a cada inquilino periódicamente, detectando si una base de datos específica o un bucket de Redis está caído antes de que el
   cliente lo reporte.
   *   **Circuit Breaker para Gateways de Pago:** Si una pasarela (dLocal, PagueloFacil) empieza a fallar o dar timeouts, el sistema debería "abrir el circuito" y ofrecer automáticamente una pasarela alternativa o mostrar un
   mensaje de mantenimiento preventivo solo para pagos.

   ### 2. Seguridad Avanzada y Cumplimiento (Enterprise Grade)
   *   **Soporte SSO / SAML / OpenID Connect:** Permitir que los inquilinos grandes conecten el login de sus empleados con sus propios sistemas de identidad (Azure AD, Okta, Google Workspace).
   *   **Centro de Privacidad (GDPR/Compliance):** Automatizar el derecho al olvido y la portabilidad de datos. Un módulo donde el usuario pueda solicitar la descarga total de su información o su eliminación, disparando los Jobs
   de limpieza en todos los módulos de forma atómica.
   *   **Políticas de Contraseñas por Inquilino:** Permitir que cada empresa defina sus reglas de seguridad (longitud mínima, rotación obligatoria de contraseñas, bloqueo de IPs sospechosas).
   *   **Vigilante de Sesiones Concurrentes:** Limitar el número de dispositivos activos por usuario (según el plan) y permitir la revocación remota de sesiones desde el panel de seguridad del inquilino.

   ### 3. Experiencia del Inquilino y Retención (Stickiness)
   *   **White-labeling Avanzado:** Más allá de colores y logos; permitir inyección de CSS personalizado, plantillas de correo HTML editables (vía Action/DTO para seguridad) y subdominios de API personalizados.
   *   **Sistema de Onboarding Interactivo:** Un módulo que guíe al administrador del inquilino a través de una lista de tareas (configurar SMTP, invitar primer equipo, subir logo) con una barra de progreso vinculada a beneficios
   (ej: "Completa el perfil y obtén 5 días extra de trial").
   *   **Centro de Notificaciones Multicanal:** Centralizar notificaciones que el usuario pueda configurar (Email, Slack, SMS, Push) basadas en eventos del sistema, utilizando una capa compartida en `Shared`.

   ### 4. Operaciones y Soporte (Super-Admin Power)
   *   **Panel de Control Central "Pulse Custom":** Una vista para los administradores globales que muestre el consumo de recursos (CPU/RAM/DB) por inquilino, identificando "vecinos ruidosos" en tiempo real.
   *   **Simulador de Cuotas (Sandboxing):** Una herramienta para que el soporte técnico pueda probar qué pasaría si cambian a un inquilino de plan, pre-visualizando si el usuario perderá acceso a funciones o registros actuales.
   *   **Gestor de Migraciones de Datos:** Una herramienta para importar datos masivos (vía CSV/Excel) hacia un inquilino, validando la integridad mediante los DTOs ya creados antes de persistir.

   ### 5. Facturación y Crecimiento (Revenue)
   *   **Sistema de Créditos / Pago por Uso (Metered Billing):** Además de la suscripción fija, permitir cobrar por unidades de uso (ej: $0.10 por cada factura generada o por GB de almacenamiento), sincronizado con Stripe/Cashier.
   *   **Programa de Afiliados Integrado:** Un módulo central que genere links de referidos para inquilinos actuales, aplicando descuentos automáticos en sus facturas si traen a nuevos clientes.
   *   **Gestión de "Grace Periods" Inteligente:** Lógica para mantener servicios activos N días tras un fallo de pago, con degradación progresiva de funciones en lugar de un corte total inmediato.

   ---

   ### Mi recomendación técnica inmediata:
   Si tuviera que elegir el **siguiente gran paso**, sería la **Refactorización del Sistema de Archivos a Spatie Media Library**. 
   *   **Por qué:** Actualmente manejamos logos de forma manual. Media Library nos daría conversiones automáticas, soporte para múltiples discos (S3 para producción, Local para dev), optimización de imágenes y una API consistente
   para todos los módulos (Landings, Auth, Soporte).

   **¿Te gustaría que diseñemos el plan para alguna de estas funcionalidades (ej. el sistema de SSL o el Onboarding)?**