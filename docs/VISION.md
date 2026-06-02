# Plinth — Product Vision

## 1. Propósito

Plinth existe para acelerar la construcción de aplicaciones SaaS multi-tenant modernas sin sacrificar seguridad, aislamiento ni mantenibilidad.

No es un starter kit genérico.

Es un **boilerplate SaaS enterprise-ready** diseñado para servir como fundación estable para productos B2B construidos sobre Laravel.

Plinth elimina la necesidad de reconstruir continuamente infraestructura repetitiva:

- multi-tenancy
- billing
- IAM
- cuotas
- auditoría
- branding
- lifecycle del tenant
- observabilidad
- aislamiento operacional

El objetivo es que los equipos construyan **producto**, no infraestructura base.

---

# 2. Problema que Resuelve

La mayoría de proyectos SaaS Laravel comienzan con:

- autenticación
- CRUD
- panel administrativo
- lógica mezclada
- tenancy improvisado

Esto genera:

- deuda técnica temprana
- fuga de datos entre tenants
- billing acoplado
- permisos inconsistentes
- crecimiento difícil
- refactors costosos

Los equipos terminan reescribiendo infraestructura crítica cuando el producto ya está en producción.

Plinth evita ese ciclo.

---

# 3. Visión del Producto

Plinth aspira a convertirse en una fundación SaaS:

- segura
- mantenible
- extensible
- operacionalmente simple

Debe permitir lanzar múltiples productos SaaS sobre la misma filosofía arquitectónica sin rediseñar la plataforma desde cero.

El foco no es velocidad desordenada.

El foco es:

**velocidad sostenible**.

---

# 4. Filosofía Arquitectónica

Plinth adopta una postura deliberada:

## Modular Monolith First

La arquitectura preferida es:

- Modular Monolith
- Single Database
- Tenant Isolation by Design

No se adopta microservices por moda.

Los módulos son bounded contexts claros, no repositorios separados.

El monolito modular permite:

- menor complejidad operacional
- menor costo de infraestructura
- debugging más simple
- despliegues más predecibles
- ownership más claro

Los microservicios solo serían evaluados ante límites reales y demostrables.

---

## Single Database First

Plinth favorece:

Single PostgreSQL Database.

Aislamiento:

- tenant_id
- RLS
- tenant-aware application layer

Razones:

- menor costo
- menor complejidad
- analytics globales simples
- operaciones centralizadas

Multi-DB no es objetivo primario.

---

# 5. Principios de Diseño

Toda decisión debe respetar:

## Seguridad Primero

La fuga horizontal de datos es fallo crítico.

Tenant isolation tiene prioridad sobre:

- DX
- rapidez
- conveniencia

---

## Pragmatismo

Plinth rechaza:

- arquitectura ceremonial
- patrones innecesarios
- abstracciones especulativas

Cada componente debe justificar su existencia.

---

## Producción Antes que Demo

Las decisiones deben asumir:

- tráfico real
- errores reales
- billing real
- clientes reales
- soporte real

No se optimiza para screenshots.

---

## Simplicidad Operacional

La plataforma debe ser operable por equipos pequeños.

Evitar:

- infra excesiva
- despliegues frágiles
- dependencias innecesarias

La mejor complejidad es la que no existe.

---

# 6. Qué Es Plinth

Plinth es:

- SaaS boilerplate
- plataforma multi-tenant
- fundación reusable
- arquitectura opinionated
- sistema enterprise-oriented
- acelerador de productos B2B

Incluye dominios centrales como:

- provisioning
- subscriptions
- IAM
- quotas
- audit
- branding
- integrations
- tenant lifecycle

---

# 7. Qué NO Es Plinth

Plinth NO es:

- CMS
- low-code builder
- admin panel generator
- template visual
- CRUD generator
- microservice framework
- marketplace de plugins

Tampoco busca soportar todas las arquitecturas posibles.

Tiene opiniones fuertes.

Las opiniones reducen complejidad.

---

# 8. Usuario Objetivo

Plinth está diseñado para:

## Equipos Técnicos

- desarrolladores Laravel
- startups B2B
- agencias SaaS
- equipos internos

Que necesiten:

- multiempresa
- seguridad
- escalabilidad razonable
- entrega rápida

---

## Productos Multi-tenant

Casos típicos:

- ERP
- CRM
- HRM
- vertical SaaS
- sistemas empresariales
- backoffice multiempresa

---

# 9. North Star

La medida de éxito de Plinth no es cuántas features tiene.

La medida es:

**qué tan rápido puede lanzarse y mantenerse un SaaS serio sin comprometer aislamiento ni calidad arquitectónica.**

Plinth debe permitir:

> construir una vez la infraestructura correcta y reutilizarla muchas veces.

---

# 10. Principio Final

Si una decisión mejora velocidad pero compromete:

- tenant isolation
- seguridad
- mantenibilidad
- simplicidad operacional

la decisión es incorrecta.

Plinth prioriza software durable sobre velocidad artificial.
