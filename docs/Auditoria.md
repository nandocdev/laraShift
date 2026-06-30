Analiza el siguiente módulo: Landing
Path: app/Modules/Central/Landing

Pasos a seguir:

1. VALIDACIÓN DE PRECONDICIONES
   - Confirmar stack UI real contra 06_CurrentState.md (Settled ≠ root prompt actual)
   - Si hay conflicto de documentación, detener y reportarlo antes de auditar UI

2. OWNERSHIP / BOUNDED CONTEXT
   - ¿Central o Tenant? ¿Correcto según 03_UseCases.md?
   - ¿Responsabilidad única o mezcla concerns de otro contexto?

3. ESTRUCTURA INTERNA
   - Actions/ DTOs/ Models/ Policies/ Livewire/ Events/ Jobs/ Tests/
   - ¿Lógica de negocio fuera de Actions (en Controller o Livewire)?

4. AISLAMIENTO DE TENANT
   - tenant_id + RLS en todas las tablas scoped
   - Scope Eloquent como capa secundaria, no única defensa
   - Cross-tenant → 404, nunca 403
   - ¿Algún tenant_id confiado desde request/sesión sin revalidar?

5. MIDDLEWARE / ACCESO
   - Orden: Tenancy → Scopes/RLS → Auth → Subscription
   - ¿Rutas que bypassean la cadena?

6. QUEUE SAFETY (si aplica)
   - tenant_id en constructor del Job
   - tenancy()->initialize() antes de lógica
   - Cleanup de estado al terminar (Graceful Handover)

7. CALIDAD DE CAPA
   - Actions: final readonly, single responsibility, sin Request/sesión
   - DTOs tipados (spatie/laravel-data) vs arrays → arrays = rechazo
   - Controllers: solo authorize → validate → action → response

8. EXCEPCIONES
   - Excepciones de dominio vs Exception() genérico

9. TESTS
   - Isolation (2 tenants → 404), Quota, Idempotency, Security

10. CLASIFICACIÓN DE HALLAZGOS
   - 🔴 Blocking / 🟡 Medium / ⚪ Cosmetic
   - Cada hallazgo: qué, dónde, por qué viola arquitectura, fix sugerido

11. PLAN DE MITIGACIÓN Y CORRECCIÓN

   Generar un plan de remediación basado en los hallazgos encontrados.

   Objetivo:
   Convertir la auditoría en acciones concretas para recuperar alineación con la arquitectura LaraShift.

   Para cada hallazgo:

   - Crear una estrategia de corrección:
     - Acción requerida
     - Prioridad
     - Riesgo mitigado
     - Impacto esperado
     - Dependencias
     - Complejidad estimada

   - Clasificar la remediación:

     🔥 HOTFIX
       Para vulnerabilidades críticas:
       - aislamiento tenant
       - fuga de información
       - bypass de middleware
       - RLS inexistente
       - acceso no autorizado

     🛠 REFACTOR
       Para deuda arquitectónica:
       - mover lógica a Actions
       - reemplazar arrays por DTOs
       - separar responsabilidades
       - corregir ownership del módulo

     📌 MEJORA
       Para calidad:
       - cobertura de tests
       - naming
       - documentación
       - optimización no crítica


   El plan debe incluir:

   ## Orden recomendado de ejecución

   Priorizar siempre:

   1. Seguridad
      - Tenant Isolation
      - RLS
      - Authorization
      - Middleware
      - Cross tenant risks

   2. Integridad arquitectónica
      - Bounded Context
      - Ownership
      - Dependencias inválidas
      - Violaciones de Actions/DTOs

   3. Estabilidad operacional
      - Queues
      - Idempotency
      - Transactions
      - Observability

   4. Calidad interna
      - Refactors
      - Tests adicionales
      - Limpieza técnica


   ## Para cada corrección propuesta indicar:

   Archivo(s) afectados:

   Ejemplo:

```
app/Modules/Central/Analytics/Actions/GenerateReportAction.php
```

Cambio:

```
Mover lógica de agregación desde Livewire Component hacia Action.
```

Razón:

```
Viola separación UI/Dominio.
Livewire no debe contener reglas de negocio.
```

Resultado esperado:

```
Componentes UI únicamente manejan estado.
Action reutilizable y testeable.
```


## Riesgos de la corrección

Identificar:

- posibles regresiones
- migraciones necesarias
- cambios de datos requeridos
- impacto en otros módulos


## Plan incremental

No proponer reescrituras completas.

Dividir en:

Fase 1:
Bloqueos críticos

Fase 2:
Correcciones estructurales

Fase 3:
Optimización y limpieza


## Validación posterior

Definir pruebas necesarias después de aplicar cambios:

Obligatorio:

- Isolation tests (Tenant A ≠ Tenant B)
- Authorization tests
- Feature tests del módulo
- Queue tests si aplica
- Regression tests

La corrección no se considera completa hasta validar que:

- no existe cross-tenant access
- el módulo respeta su bounded context
- las reglas de arquitectura siguen vigentes
- los tests cubren los riesgos corregidos

11. IMPLEMENTACIÓN DEL PLAN DE CORRECCIÓN

Ejecutar el plan de mitigación generado, aplicando las correcciones en el orden de prioridad definido.
Implementar cambios production-ready, actualizar tests afectados y validar que los hallazgos queden resueltos sin romper la arquitectura existente.