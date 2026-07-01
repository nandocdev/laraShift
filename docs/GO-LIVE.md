# Go-Live Checklist & Runbook — LaraShift

> **Documento maestro de hardening final y puesta en producción.**
> Completar cada ítem antes del corte a producción.

---

## 1. Pre-Flight: Security Hardening Checklist

### 1.1 Tenant Isolation

- [x] **RLS habilitado en todas las tablas tenant-scoped**
  - Verificado: `tenant_audit_logs`, `tenant_settings`, `tenant_notifications`, `tenant_api_keys`, `tenant_invitations`, `tenant_webhooks`, `tenant_webhook_deliveries`, `tenant_encryption_keys`
  - Pendiente: verificar RLS en `tenant_feature_overrides`, `quota_snapshots`
- [x] **Cross-tenant access retorna 404, nunca 403**
  - Middleware `EnsureUserBelongsToTenant` implementado
  - `TenantScope` global scope en todos los modelos tenant-scoped
- [x] **Middleware order validado**: InitializeTenancy → ApplyTenantScopes → Authenticate → CheckSubscription
  - Verificar en `routes/tenant.php` y `routes/tenant_api.php`

### 1.2 Secrets & Encryption

- [x] **APP_KEY generada y rotada**: `php artisan key:generate`
- [x] **API keys almacenadas como hash**: HMAC-SHA256 con APP_KEY
- [x] **Encryption keys por tenant**: Rotación automática cada 90 días via `RotateTenantSecretsJob`
- [x] **Encryption keys almacenadas cifradas**: `encrypt()` de Laravel (AES-256-CBC)
- [ ] **`.env` excluido del repo**: Verificar `.gitignore`
- [ ] **`.env.example` sin secrets reales**: Verificar que no contenga API keys ni passwords
- [ ] **Variables de entorno sensibles**: Revisar `config/services.php`, `config/billing.php`, `config/payments.php`

### 1.3 HTTP Security Headers

- [x] **SecurityHeaders middleware implementado**:
  - `X-Frame-Options: DENY`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Content-Security-Policy` configurado
  - `Strict-Transport-Security` (solo no-local)
  - `Permissions-Policy` restringido
- [ ] **HTTPS everywhere**: Configurar en producción (Cloudflare/ELB termination + HSTS)
- [ ] **Cookie Secure + SameSite**: Verificar config/session.php (`secure` y `same_site`)

### 1.4 Authentication & Session

- [x] **2FA (TOTP)** para super-admins: `Central2FA` + `TwoFactorEnrollment`
- [x] **2FA para tenant users**: `UserMfa` + `EnforceTenantMfa` middleware
- [x] **Rate limiting**: `ApplyTenantRateLimits`, `GlobalRateLimiter`, Fortify throttles
- [x] **Password policies**: `PasswordPolicy` service con min length, mixed case, numbers
- [x] **Session management**: `RevokeOldestSessionAction`, concurrent session limits
- [ ] **Session timeout**: Verificar `config/session.php` `lifetime` (recomendado: 120 min)

### 1.5 Data Protection

- [x] **GDPR data export**: `ExportTenantDataJob` + `DataExport` Livewire
- [x] **Data retention policies**: `UpdateRetentionPolicyAction` por tipo de dato
- [x] **Audit log purge**: `PurgeExpiredAuditLogsAction` diario a las 02:00
- [x] **Encryption keys rotation**: `RotateTenantSecretsJob` diario a las 03:00

### 1.6 Infrastructure

- [x] **Queue Horizon**: Configurado con 5 buckets × 3 prioridades
- [x] **Health endpoint**: `GET /central/health` (DB, Redis, Queue)
- [x] **Health checks por tenant**: `RunTenantHealthChecksJob` cada 5 min
- [x] **Monitoring dashboard**: `Central/Monitoring` con alertas críticas
- [ ] **Database backup**: Configurar backup diario de PostgreSQL (RDS snapshot / pg_dump)
- [ ] **Redis persistence**: Verificar config `save` en redis.conf o AOF habilitado

---

## 2. Go-Live Runbook

### 2.1 Pre-Release (T-48h)

- [ ] **Notificar stakeholders** del corte programado
- [ ] **Ejecutar full test suite**: `php artisan test` — mínimo 425 tests pasando
- [ ] **Ejecutar lint**: `composer lint:check` — 0 errores
- [ ] **Revisar dependencias**: `composer audit` para vulnerabilidades conocidas
- [ ] **Verificar migraciones**: `php artisan migrate --pretend` en staging
- [ ] **Verificar Horizon**: `php artisan horizon:status` en staging
- [ ] **Verificar health checks**: `curl https://staging.domain.com/central/health`

### 2.2 Release (T-0)

```bash
# 1. Poner mantenimiento
php artisan down --secret="larashift-maintenance"

# 2. Backup DB
pg_dump -Fc lara_shift > backups/pre-deploy-$(date +%Y%m%d_%H%M%S).dump

# 3. Desplegar código
git pull origin main
composer install --no-dev --optimize-autoloader

# 4. Migrar
php artisan migrate --force

# 5. Cachear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Scheduler
php artisan schedule:run

# 7. Horizon
php artisan horizon:terminate
# (supervisor restartará automáticamente)

# 8. Sacar de mantenimiento
php artisan up
```

### 2.3 Smoke Tests Post-Deploy

```bash
# Health endpoint
curl https://app.larashift.com/central/health

# Create tenant flow
curl -X POST https://app.larashift.com/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Smoke Test","email":"smoke@test.com","slug":"smoke","plan_id":"free"}'

# Login central
curl https://app.larashift.com/central/login

# Feature resolution
curl https://smoke.larashift.com/api/members \
  -H "Authorization: Bearer tnt_..."

# Audit log
curl https://smoke.larashift.com/audit
```

### 2.4 Rollback Plan

```bash
# Si el deploy falla dentro de los primeros 15 minutos:

# 1. Volver al commit anterior
git reset --hard <previous-stable-tag>

# 2. Re-ejecutar migraciones en reversa si es necesario
php artisan migrate:rollback --step=1 --force

# 3. Re-cachear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Re-iniciar Horizon
php artisan horizon:terminate

# 5. Sacar de mantenimiento
php artisan up
```

### 2.5 Post-Release (T+24h)

- [ ] **Monitorear Horizon**: `php artisan horizon:status`, revisar failed jobs
- [ ] **Monitorear health checks**: Revisar `Central/Monitoring` dashboard
- [ ] **Monitorear logs**: Revisar `Central/Monitoring/logs` por errores inusuales
- [ ] **Verificar provisioning**: Crear tenant de prueba, verificar onboarding completo
- [ ] **Verificar billing**: Probar flujo de checkout con dLocal/Stripe en modo test
- [ ] **Verificar webhooks**: Probar webhook outbound desde tenant de prueba

---

## 3. On-Call Rotation

| Día | Ingeniero Primario | Ingeniero Secundario |
|---|---|---|
| Semana 1 | TBD | TBD |
| Semana 2 | TBD | TBD |

### Escalation Path

1. **Incidente menor** (1 tenant afectado): Ingeniero primario → resolver en 4h
2. **Incidente mayor** (múltiples tenants): Ingeniero primario + secundario → resolver en 1h
3. **Crítico** (plataforma caída): Todo el equipo → resolver inmediatamente

### Alertas Configuradas

| Alerta | Canal | SLA |
|---|---|---|
| Health check failure | Slack + Email | 5 min |
| Billing failure | Slack | 15 min |
| Provisioning failure | Slack + Email | 30 min |
| Resource exhaustion | Slack | 1 h |
| Security event (CRITICAL) | PagerDuty | 5 min |

---

*Documento generado para uso interno del equipo de ingeniería. Actualizar on-call rotation semanalmente.*
