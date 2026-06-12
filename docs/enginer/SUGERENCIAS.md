# Central

## Auth

  🛠️ Puntos de Mejora (Refactorización Proactiva)
  Aunque la ingeniería es excelente, para llegar a la perfección sugiero:
   * Configuración del Límite: El límite de sesiones int $limit = 3 en RevokeOldestSessionAction debería leerse de un archivo de configuración
     (config('auth.central.session_limit')) para evitar "magic numbers" en el código.
   * Tipado de Retorno: En LoginCentralUserAction::execute, el retorno es un string ('success', 'requires_2fa'). Para una ingeniería más robusta, un Enum de
     PHP 8.3 sería ideal para evitar errores de typo en comparaciones futuras.

## Provisioning

  🛠️ Puntos de Mejora (Refactorización Proactiva)
  Aunque la ingeniería es excelente, para llegar a la perfección sugiero:
   * Validación de Datos: En CreateCentralUserAction, agregar validación de datos antes de crear el usuario para asegurar que los datos sean correctos y evitar errores en tiempo de ejecución.
   * Manejo de Excepciones: Implementar manejo de excepciones más robusto en caso de errores durante la creación del usuario, como problemas de conexión a la base de datos o violaciones de integridad.

## Billing

🛠️ Recomendaciones del Master para Robustez Total

   1. Transacciones Atómicas: Envolver cada fulfillment y registro de pago en DB::transaction().
   2. Sistema de Reconciliación (Anti-Drift): Implementar un comando programado (billing:reconcile) que compare una vez al día el estado de todas las
      suscripciones locales contra sus respectivos gateways.
   3. Throttling de Sincronización: Modificar SyncTenantInvoicesJob para que solo se ejecute si la última sincronización fue hace más de X minutos (usando
      un flag en cache o una columna last_synced_at en el tenant).
   4. Uso de Librería de Dinero: Migrar los montos a Money objects para manejar precisiones y divisas de forma profesional.
   5. Soft Deletes para Planes: En lugar de impedir el borrado, usar SoftDeletes para que los registros históricos sigan siendo válidos pero el plan ya no
      esté disponible para nuevos clientes.