# LaraShift Roadmap — Core Infrastructure Focus

Este documento define el camino estratégico para **LaraShift**. Nos enfocamos exclusivamente en la infraestructura técnica crítica que los fundadores de SaaS necesitan delegar ("Hard SaaS"), evitando el desarrollo de componentes de bajo valor o comoditizados.

## 🏛️ Filosofía: "Hard SaaS"

Los compradores de boilerplates pagan por resolver los problemas complejos de escalabilidad, seguridad y facturación. No pagan por un CMS básico.

---

## 📅 Priority Matrix

### 🟢 Prioridad Alta: "Core Infrastructure"

*El motor del SaaS. Bloqueante para cualquier lanzamiento B2B.*

| Módulo      | Componente   | Estado       |
| :---------- | :----------- | :----------- |
| **Central** | Auth         | Implementado |
| **Central** | Features     | Implementado |
| **Central** | Quotas       | Implementado |
| **Central** | Billing      | Implementado |
| **Central** | Provisioning | Implementado |
| **Tenant**  | Identity     | Implementado |
| **Tenant**  | Audit        | Implementado |
| **Tenant**  | Settings     | Implementado |
| **Tenant**  | APIKeys      | Implementado |
| **Tenant**  | Webhooks     | Implementado |

### 🟡 Prioridad Media: "Growth & Retention"

*Funcionalidades necesarias para que el producto sea utilizable y mejore la retención del cliente.*

| Módulo     | Componente    | Estado       |
| :--------- | :------------ | :----------- |
| **Tenant** | Notifications | Implementado |
| **Tenant** | DataExport    | Implementado |
| **Tenant** | SMTP          | Implementado |
| **Tenant** | Domains       | Implementado |

### 🔴 Prioridad Baja: "Content & Marketing"

*Funcionalidades de valor añadido que no deben distraer del desarrollo del Core.*

| Módulo      | Componente      | Estado                   |
| :---------- | :-------------- | :----------------------- |
| **Central** | Landing Builder | Implementado (Congelado) |
| **Central** | CMS             | Pendiente                |
| **Central** | Marketing       | Implementado             |

---

## 🛠️ Próximos pasos (Fase Roadmap)

1. **Mantenimiento del Core**: Estabilización continua de `Payments` y `Provisioning` según los hallazgos de seguridad.
