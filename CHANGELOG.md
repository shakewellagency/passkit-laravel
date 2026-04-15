# Changelog

All notable changes to the PassKit Laravel package will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-04-15

### BREAKING CHANGES
- **Dropped support for Laravel 10 and 11.** Both are past their security-fix
  windows (Laravel 10 ended 2025-02-04, Laravel 11 ended 2026-03-12). Supported
  versions are now Laravel 12.x and 13.x.
- **Raised minimum PHP to 8.2.** PHP 8.1 reached end of security support in
  December 2025.
- `PassKitSyncService` constructor now takes a `PassKitCrudManager` as its
  second argument (auto-wired by the service provider).
- `PassKitCrudManager` has been rewritten as a local-database CRUD interface.
  Method signatures have changed (e.g. `createProgram(array $data)` now returns
  a `PassKitProgram` rather than an array; `listMembers(int $accountId, array
  $options)` returns an Eloquent `Collection` or `LengthAwarePaginator`).
  Callers that round-tripped through the gRPC API via this manager should move
  to `PassKitService` directly.
- `PassKitAuditService` has been rewritten against the local `passkit_audit_logs`
  schema. Method names and signatures (`log`, `logSecurityEvent`,
  `logPerformanceEvent`, `logChange`, etc.) have changed — see
  `src/Services/PassKitAuditService.php`.
- `PassKitSetupCommand` now exposes `--testing`, `--interactive`,
  `--publish-config`, `--migrate`, `--show-config`, and `--force` flags.
- `PassKitSyncCommand` now exposes `--account`, `--member-id`, `--pass-id`,
  `--members-only`, `--transactions-only`, `--passes-only`, `--since`,
  `--batch-size`, `--dry-run`, `--force`, `--status`, `--show-conflicts`,
  `--schedule`, and `--export-report`.

### Added
- Laravel 13 compatibility across `illuminate/*` constraints.
- `sync_pending` column on `passkit_members` to track records pending re-sync.
- `is_active`, `current_tier`, and related accessors on wallet-pass, member,
  and program models; `activate()`, `deactivate()`, `suspend()`, `recordView()`,
  `recordShare()`, `setSyncPending()`, `clearSyncPending()`,
  `incrementUpdateCount()` methods on `WalletPass`.
- `PassKitMember`: `scopeByTier`, `scopeEnrolledAfter`, `scopePointsBetween`,
  `full_name`, `is_active`, `days_since_enrollment` accessors,
  `getTotalEarnedPoints()`, `getTotalSpentPoints()`.
- `PassKitTransaction`: `scopeCreatedAfter`, `scopePending`, `scopeFailed`,
  `scopePointsBetween`, `scopeExpired`, `is_earning`/`is_spending`/`is_completed`/
  `is_reversed` accessors, `complete()`, `fail()`, `cancel()`, `reverse()`,
  `expire()`, `getPointsChange()`, `getTransactionSummary()`.

### Fixed
- Invalid cron expression for the templates sync schedule that caused
  `schedule:work` / `schedule:run` to fail in consuming applications
  (carried over from 1.0.2).
- Duplicate column definitions in `add_passkit_fields_to_wallet_passes_table`
  and `add_passkit_fields_to_card_templates_table` migrations that prevented
  migrations from running on a fresh database.
- Named migration class in `create_passkit_audit_system` replaced with an
  anonymous class so migrations can be re-run under strict Laravel 12 classmap
  handling.
- Model table names now explicitly set on `PassKitAuditLog`,
  `PassKitSecurityLog`, `PassKitPerformanceLog`, `PassKitComplianceLog`,
  `PassKitDataChange` so they resolve to the `passkit_*` tables rather than
  Laravel's default `pass_kit_*` naming convention.
- Incorrect `status => 'array'` cast on `PassKitProgram` (database column is
  an enum string).
- `PassKitProgram::tiers()` now uses the default `program_id`/`id` foreign
  keys instead of the stale `passkit_id` local key.

### Changed
- Widened dev dependencies to current stable versions: `phpunit/phpunit` ^11.0|^12.0,
  `pestphp/pest` ^3.0, `pestphp/pest-plugin-laravel` ^3.0, `mockery/mockery` ^1.6,
  `orchestra/testbench` ^10.0|^11.0.
- `.gitignore` now excludes vendor, test artifacts, IDE directories, and AI-tool
  working directories (`.serena/`, `.claude/`, `.claude-flow/`, `.swarm/`,
  `.aider*`, `.cursor/`, `.roo/`, `.windsurf/`).

### Known
- The package's existing test suite still has rot in the `Feature`, `Integration`,
  and `Unit/Database` layers (controllers, API routes, and a `MigrationsTest`
  that relies on the Laravel 10-era `getDoctrineSchemaManager`). 266 of 368
  tests pass on this release; the remainder is tracked for a follow-up test
  rehabilitation PR.

## [1.0.2] - 2026-04-15

### Fixed
- Invalid cron expression for the templates sync schedule. `->everySixHours()->at('30')`
  produced `0 30 * * *` (invalid — the cron hour field max is 23), which caused
  `schedule:work` / `schedule:run` to fail in consuming applications. Replaced with
  explicit `->cron('30 */6 * * *')` so the job correctly runs every 6 hours offset
  by 30 minutes.
- Syntax error in `PassKitSyncCommand` string interpolation.

### Changed
- Widened `google/protobuf` constraint to allow `^4.0` alongside `^3.24`.

## [1.0.1] - 2025-08-18 (tagged, no GitHub release)

### Added
- Laravel 12 support across `illuminate/*` constraints.
- `orchestra/testbench` ^10.0 for Laravel 12 testing.

### Changed
- Retained backward compatibility with Laravel 10 and 11.

## [1.0.0] - 2024-08-17

### Added
- 🎉 Initial release of PassKit Laravel package
- 🔐 Complete PassKit gRPC API integration with certificate-based authentication
- 🏗️ Comprehensive CRUD management system for all PassKit entities
- 📱 Support for Apple Wallet and Google Pay pass creation
- 🎫 Multiple pass types: membership cards, event tickets, coupons
- 🔄 Real-time push notifications and webhook handling
- 📊 System statistics and health monitoring
- 🎨 Dynamic template management and customization
- 📱 Automatic QR code generation for pass installation
- 🛡️ Production-ready security with input validation and error handling
- 📋 RESTful API endpoints for all operations
- 🧪 Comprehensive test suite with health checks
- ⚙️ Laravel Artisan commands for setup and testing
- 📚 Complete documentation with examples
- 🎯 Laravel service provider with auto-discovery
- 🔧 Configurable caching, logging, and notification systems

### Features
- **PassKitService**: Core service for gRPC API communication
- **PassKitCrudManager**: High-level CRUD operations manager
- **Models**: PassKitProgram, PassKitTier, CardTemplate, WalletPass
- **API Controllers**: RESTful endpoints for frontend integration
- **Console Commands**: Setup and testing commands
- **Facades**: Easy-to-use Laravel facades
- **Migrations**: Database schema for PassKit entities
- **Configuration**: Comprehensive config file with environment variables

### API Endpoints
- Programs: CRUD operations for loyalty programs
- Tiers: Membership tier management
- Members: Member enrollment and management
- Templates: Pass template creation and customization
- Wallet Passes: Pass installation and tracking
- System: Health checks and statistics

### Developer Experience
- Laravel auto-discovery for service provider
- Artisan commands for quick setup and testing
- Comprehensive error handling and logging
- Input validation with detailed error messages
- Configurable caching for performance optimization
- Health check endpoints for monitoring

### Documentation
- Complete README with installation and usage examples
- API documentation with endpoint descriptions
- Configuration guide with all available options
- Testing guide with command examples
- Security documentation with best practices

### Security
- Certificate-based gRPC authentication
- Webhook signature validation
- Input sanitization and validation
- Secure credential storage
- Comprehensive error handling without information disclosure

### Performance
- Configurable caching system
- Efficient database queries with eager loading
- Batch operations support
- Connection pooling for gRPC clients
- Optimized JSON responses

### Compatibility
- PHP 8.1+
- Laravel 10.0+ and 11.0+
- PassKit gRPC API v1
- MySQL/PostgreSQL database support
- gRPC PHP extension required

---

For upgrade instructions and breaking changes, see [UPGRADE.md](UPGRADE.md).
For detailed API documentation, see [API.md](docs/API.md).