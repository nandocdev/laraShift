# SLA, RTO & RPO por Tenant Tier

## Definiciones

| Término | Descripción |
|---------|-------------|
| **SLA** | Service Level Agreement — disponibilidad del servicio |
| **RTO** | Recovery Time Objective — tiempo máximo para restaurar servicio |
| **RPO** | Recovery Point Objective — pérdida máxima de datos aceptable |

## Por Tier

| Métrica | Trial | SMB (Free/Pro) | Enterprise |
|---------|-------|----------------|------------|
| **SLA** | Sin garantía | 99.5% | 99.9% |
| **RTO** | 48 horas | 4 horas | 1 hora |
| **RPO** | 24 horas | 1 hora | 5 minutos |
| **Retención** | 30 días | 90 días | 365 días |
| **Soporte** | Email | Email + Chat | 24/7 Priority |

## Ciclo de Vida del Tenant

```text
provisioning → active → suspended → archived → purged
                    ↑         |           |
                    └─────────┘           |
                    reactivate            |
                                          ↓
                                    retención legal
                                          |
                                          ↓
                                    purge (force delete)
```

## Retention por Plan

| Plan | Retención tras archivado | Purge automático |
|------|--------------------------|------------------|
| Free | 90 días | Sí |
| Pro | 90 días | Sí |
| Enterprise | 365 días | Sí (con aprobación manual) |
| Trial | 30 días | Sí |

## Restauración

Los tenants archivados pueden restaurarse si:
1. El tenant no ha sido purgado
2. Los datos están dentro del período de retención
3. El plan vinculado sigue existiendo y está activo

La restauración revierte: `archived_at → null`, `status → active`, `read_only → false`.
