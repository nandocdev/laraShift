# Scope Platform

Este directorio contiene los módulos, clases y primitivas transversales de la plataforma técnica de LaraShift. 

## Reglas del Scope

1. **Independencia Absoluta**: Ningún componente dentro de `Platform` debe importar o depender de clases de los scopes `Central` o `Tenant`.
2. **Reutilización y Primitivas**: Platform proporciona las herramientas e infraestructura que consumen tanto el SaaS central (`Central`) como el producto de los clientes (`Tenant`).
3. **Migración**: Todo nuevo desarrollo transversal o de infraestructura técnica debe realizarse bajo este scope. Los módulos existentes bajo `Shared` se migrarán de forma ordenada hacia `Platform` en fases posteriores.

## Estructura de Módulos Sugerida

A medida que se extraigan o implementen componentes, los módulos se ubicarán en esta estructura:

- `Platform/Foundation`: Núcleo técnico básico y adaptadores del framework (Laravel overrides, helpers generales).
- `Platform/Contracts`: Interfaces para desacoplar las implementaciones de los módulos (ej. `BillingProvider`, `TenancyResolver`).
- `Platform/Events`: Bus de eventos común, payloads e integration events.
- `Platform/Tenancy`: Lógica de aislamiento multi-tenant, bootstrappers de PostgreSQL RLS y Eloquent scopes.
- `Platform/Security`: Cifrado, hashing de API keys, MFA y políticas generales de acceso.
- `Platform/Observability`: Logs estructurados, métricas operativas y trazabilidad de jobs en colas.
- `Platform/Data`: Primitivas reutilizables (Money, Casts de base de datos, serializadores, DTOs compartidos).
- `Platform/UI`: Layouts y componentes Blade/Livewire compartidos.
