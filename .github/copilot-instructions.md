# Sequifi Backend - AI Coding Agent Instructions

## Project Overview
**Sequifi** is a comprehensive HR/Payroll/Sales CRM platform built on Laravel 10.48+. It manages employee lifecycle, payroll processing, commission calculations, CRM operations, and integrations with third-party systems. The system handles complex domain logic across multiple databases with heavy async job processing.

---

## Critical Architecture Patterns

### 1. Multi-Database Architecture
- **MySQL**: Primary OLTP database (users, payroll, sales, HR data)
- **MongoDB**: Document-centric features (SequifiArena, analytics)
- **BigQuery**: Long-term analytics and sync targets
- **ClickHouse**: Activity logging and metrics aggregation
- **SQLite**: Domain-specific databases for performance isolation (created via `shell-scripts/install-sqlite-setup.sh`)

**When to use each**: Transactional/relational → MySQL; flexible documents → MongoDB; analytics → BigQuery/ClickHouse.

### 2. Service Layer with Specific Patterns
Services in `app/Services/` handle domain logic encapsulation. Key examples:
- `PayrollCalculationService`: Calculates totals using both active (UserCommission) and locked (UserCommissionLock) tables
- `QuickBooksService`: Third-party OAuth integration with encrypted credentials
- `SalesCalculationContext`: Context objects for complex calculations

**Pattern**: Services are dependency-injected into controllers, often accept context/configuration parameters, and return structured arrays or domain objects.

### 3. Queue Architecture (Redis-Based) - **Critical for Functionality**
- **Must be running**: `php artisan queue:work` or Horizon in production
- **Mandatory for**: Company initialization, dependent seeders, CRM sync, payroll processing
- **Job naming**: Follows pattern `[Domain][Action]Job.php` (e.g., `executePayrollJob.php`, `SyncUserToOnyxJob.php`)
- **Traits**: Jobs often use `EvereeTrait`, `PayFrequencyTrait`, `PushNotificationTrait` for cross-cutting concerns
- **Timeout**: Long-running jobs set `$timeout` property (e.g., 1800 seconds for payroll)

**Without queue workers, company setup and bulk operations will fail.**

### 4. Route Organization & Middleware
Routes are organized by feature module:
```php
Route::prefix('payroll')->middleware('auth:sanctum')->group(function () {
    include 'sequifi/payroll/auth.php';
});
```

**Core middleware**:
- `auth:sanctum`: Sanctum token-based API authentication
- `admin`: Checks admin role (see `AdminMiddleware`)
- `throttle.custom`: Rate limiting by token or IP
- `feature-flags.auth`: Super admin-only feature access
- `arena_static_token`: Arena/MongoDB static authentication

### 5. Activity Logging & Audit
- **Spatie Activity Log**: Integrated via `SpatieLogsActivity` trait in models
- Applied to User, PayrollStatus, custom audit models
- Captured in ClickHouse for historical queries
- **Don't create separate audit models for every entity** - use traits where appropriate

### 6. Feature Flags Dashboard
Located at `/feature-flags` (protected by `feature-flags.auth` middleware).
- Managed in `app/Providers/HorizonServiceProvider`
- Controlled by `FeatureRegistry` service
- Uses database-backed toggle system

---

## Essential Development Workflows

### Initial Setup (Mandatory)
```bash
composer install
cp .env.example .env
php artisan key:generate
mysql -u root -p -e "CREATE DATABASE sequifi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate:fresh --seed  # Includes company initialization data
php artisan queue:work             # Must run in separate terminal!
php artisan serve                  # OR: php artisan octane:start --server=swoole
```

**Without running migrations with seed AND queue worker, the system is non-functional.**

### Testing Pattern
```bash
php artisan test                          # All tests
php artisan test --testsuite=Feature      # Feature tests only
php artisan test tests/Feature/UserTest.php  # Specific file
```
Tests use `TestCase.php` base class (feature tests) and `CreatesApplication.php` for setup. Config via `phpunit.xml` or `.env.testing`.

### Queue Management
```bash
php artisan queue:work --queue=default,parlley  # Multiple queues
php artisan horizon                              # Dashboard at /horizon
php artisan queue:flush                          # Clear failed jobs
```

### Code Quality
```bash
composer format  # Apply formatting
php artisan ide-helper:generate  # Better IDE autocomplete
```

---

## Data Model Complexity & Conventions

### Large Model Library (200+ Models)
Models are organized by domain:
- **Payroll**: `Payroll`, `PayrollHistory`, `PayrollLock`, `UserCommission`, `UserCommissionLock`, etc.
- **Sales**: `SalesMaster`, `SaleProductMaster`, `SaleTiersMaster`, `CommissionCalculator`
- **HR**: `User`, `EmployeePersonalDetail`, `OnboardingEmployees`, `UserOverrides`
- **Integrations**: `QuickBooksService`-related, `ExternalApiToken`, integration transaction logs

### Lock Tables Pattern
Many critical entities have `-Lock` variants (`UserCommissionLock`, `PayrollAdjustmentLock`, etc.) for finalized/immutable records. This dual-table pattern prevents accidental edits and maintains audit trails.

### Traits for Cross-Cutting Concerns
- `SpatieLogsActivity`: Activity logging
- `PayFrequencyTrait`: Frequency-based calculations (daily/weekly/bi-weekly)
- `EvereeTrait`: Everee (third-party payroll) integration utilities
- `EmailNotificationTrait`, `PushNotificationTrait`: Async notifications

---

## Integration Points & External Systems

### Major Integrations (see `.env` configs)
1. **QuickBooks**: OAuth2, journal entry creation for payroll (`QuickBooksService`)
2. **BigQuery**: Analytics sync via `AddUpdateUserOnBigQueryJob`
3. **ClickHouse**: Activity logging via `ClickHouseSyncMonitorService`
4. **AWS S3/SES**: File storage and email delivery
5. **Field Routes**: CRM data sync via `FieldRoutesApiService`, custom field mapping
6. **HubSpot**: Lead/contact management, activity logging
7. **Pusher**: Real-time WebSocket broadcasting
8. **Sentry**: Error tracking and monitoring

**Pattern**: Each integration has a dedicated service (`*Service.php`) and often transaction log models for tracking API calls.

---

## Common Pitfalls & Best Practices

1. **Always test with queue worker running**: Many features (company setup, bulk operations) require async processing.
2. **Use PayFrequencyTrait for frequency-based logic**: Don't hardcode daily/weekly/bi-weekly calculations.
3. **Respect Lock tables**: Don't write to locked tables directly; use them for read-only audit queries.
4. **Middleware stacking**: Know the authentication guard (`auth:sanctum` vs `auth:sanctum,web` vs `feature-flags`)
5. **Environment-specific settings**: Use `.env.testing` for tests, `.env` for dev; **never hardcode credentials**.
6. **Model relationships over N+1 querying**: Use eager loading (`with()`) for related models.
7. **Service injection**: Controllers receive services via constructor injection, not static calls.

---

## File Structure Reference

- **Routes**: `/routes/api.php` (main API) + `/routes/sequifi/*/auth.php` (module routes)
- **Controllers**: `/app/Http/Controllers/API/` (organized by domain)
- **Services**: `/app/Services/` (business logic encapsulation)
- **Models**: `/app/Models/` (200+ files, organized by domain)
- **Jobs**: `/app/Jobs/` (40+ background jobs)
- **Middleware**: `/app/Http/Middleware/`
- **Migrations**: `/database/migrations/` + `/database/migrations_archived/`
- **Tests**: `/tests/Feature/` and `/tests/Unit/`

---

## Environment & Deployment Notes

- **Production**: Use Horizon via supervisor for queue management (see `deploy/supervisor/*`)
- **Logging**: View via `/log-viewer` HTTP endpoint or `storage/logs/laravel.log`
- **Redis**: Critical for cache/sessions/queues; ensure it's running (`redis-cli ping` should return PONG)
- **API docs**: Generated at `/api/documentation` via L5 Swagger (`php artisan generate:swagger-documentation`)
- **Optimization**: Before deploy, run `php artisan config:cache && php artisan route:cache`

---

## Quick Tips for Productivity

- **Use Tinker**: `php artisan tinker` for quick database/logic testing
- **IDE helper**: Run `php artisan ide-helper:generate` for full autocomplete support
- **Monitor jobs**: Use Horizon dashboard at `/horizon` to debug stuck jobs
- **Check domain**: If unsure which database/service owns a feature, search for model name in migrations or service files
- **Spatie permissions**: User roles/permissions managed via Spatie, check `ModelHasRoles` and `Permissions` models
