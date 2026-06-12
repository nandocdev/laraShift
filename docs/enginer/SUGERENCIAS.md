

---

Revisa exhaustivamente el módulo **Central/Support**.

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

Implementa todas las recomendaciones aprobadas del informe de revisión para el módulo **Central/Support**.

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
