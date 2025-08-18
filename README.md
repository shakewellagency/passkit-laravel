# PassKit Laravel Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shakewellagency/passkit-laravel.svg?style=flat-square)](https://packagist.org/packages/shakewellagency/passkit-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/shakewellagency/passkit-laravel.svg?style=flat-square)](https://packagist.org/packages/shakewellagency/passkit-laravel)
[![License](https://img.shields.io/packagist/l/shakewellagency/passkit-laravel.svg?style=flat-square)](https://packagist.org/packages/shakewellagency/passkit-laravel)

A comprehensive Laravel package for PassKit API integration with local database storage, automated sync, and complete CRUD management.

## Features

✨ **Complete PassKit Integration**
- Full gRPC API integration with PassKit
- Support for membership programs, event tickets, and coupons
- Real-time pass creation and management
- Apple Wallet and Google Pay compatibility

🗄️ **Local Database Storage**
- Complete MySQL schema with 10+ optimized tables
- Full data persistence for offline operations
- Advanced analytics and reporting capabilities
- Comprehensive member and transaction tracking

🔄 **Automated Sync System**
- Intelligent cron jobs with webhook fallback
- Incremental and full sync capabilities
- Automatic error recovery with exponential backoff
- Performance monitoring and alerting

🎯 **CRUD Management Service**
- Complete service container architecture
- RESTful API endpoints included
- Laravel Eloquent models with relationships
- Advanced querying and filtering

📊 **Analytics & Monitoring**
- Pre-calculated analytics tables
- Sync performance monitoring
- Comprehensive logging system
- Email alerts for failures

## Installation

Install the package via Composer:

```bash
composer require shakewellagency/passkit-laravel
```

Publish the configuration and migrations:

```bash
# Publish configuration
php artisan vendor:publish --tag=passkit-config

# Publish migrations
php artisan vendor:publish --tag=passkit-migrations

# Run migrations
php artisan migrate
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# PassKit API Configuration
PASSKIT_API_HOST=grpc.pub2.passkit.io
PASSKIT_API_PORT=443
PASSKIT_TESTING_MODE=false

# Certificate paths
PASSKIT_CERTIFICATE_PATH=/path/to/certificate.pem
PASSKIT_KEY_PATH=/path/to/private-key.pem
PASSKIT_CA_FILE=/path/to/ca-certificate.pem

# Optional settings
PASSKIT_NOTIFICATIONS_ENABLED=true
PASSKIT_QR_CODES_ENABLED=true
PASSKIT_CACHE_ENABLED=true
```

### Setup Command

Use the setup command to configure certificates:

```bash
php artisan passkit:setup --cert=/path/to/cert.pem --key=/path/to/key.pem
```

## Quick Start

### Basic Usage

```php
use ShakewellAgency\PassKitLaravel\Facades\PassKit;

// Test connection
$connected = PassKit::testConnection();

// Create a membership program
$program = PassKit::createMembershipProgram([
    'name' => 'VIP Membership',
    'description' => 'Exclusive VIP benefits'
]);

// Enroll a member
$member = PassKit::enrollMember('tier_id', [
    'externalId' => 'user_123',
    'email' => 'user@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'points' => 100
]);
```

### Using CRUD Manager

```php
use ShakewellAgency\PassKitLaravel\Services\PassKitCrudManager;

$crudManager = app(PassKitCrudManager::class);

// Create program
$result = $crudManager->createProgram('membership', [
    'name' => 'Loyalty Program',
    'description' => 'Customer loyalty rewards'
], $accountId);

// Get system statistics
$stats = $crudManager->getSystemStats();

// Health check
$health = $crudManager->healthCheck();
```

## API Endpoints

The package provides RESTful API endpoints:

```
GET    /api/passkit/health                    # Health check
GET    /api/passkit/programs                  # List programs
POST   /api/passkit/programs                  # Create program
GET    /api/passkit/programs/{id}             # Get program
PUT    /api/passkit/programs/{id}             # Update program
DELETE /api/passkit/programs/{id}             # Delete program

POST   /api/passkit/members                   # Create member
GET    /api/passkit/members/{id}              # Get member
PUT    /api/passkit/members/{id}/points       # Update points
GET    /api/passkit/members/{id}/installation # Get install URLs

GET    /api/passkit/stats                     # System statistics
```

## Models

### PassKitProgram
Manages loyalty programs, event tickets, and coupon campaigns.

### PassKitTier  
Defines membership tiers within programs.

### WalletPass
Represents individual wallet passes for users.

### CardTemplate
Manages pass design templates and layouts.

## Commands

### Setup Command
```bash
php artisan passkit:setup [options]
```

### Test Command
```bash
php artisan passkit:test [--feature=all]
```

## Testing

Run the test suite:

```bash
php artisan passkit:test
```

Test specific features:

```bash
php artisan passkit:test --feature=connection
php artisan passkit:test --feature=programs
php artisan passkit:test --feature=members
```

## Configuration Options

### Templates
Configure default templates in `config/passkit.php`:

```php
'templates' => [
    'default_membership' => [
        'name' => 'Default Membership Template',
        'description' => 'Default template for membership passes',
        'timezone' => 'America/Los_Angeles',
    ],
],
```

### Notifications
Enable push notifications:

```php
'notifications' => [
    'enabled' => true,
    'webhook_url' => env('PASSKIT_WEBHOOK_URL'),
    'webhook_secret' => env('PASSKIT_WEBHOOK_SECRET'),
],
```

### QR Codes
Configure QR code generation:

```php
'qr_codes' => [
    'enabled' => true,
    'size' => 300,
    'format' => 'png',
],
```

## Security

- Uses certificate-based gRPC authentication
- Secure webhook validation
- Input validation and sanitization
- Comprehensive error handling and logging

## Support

- **Issues**: [GitHub Issues](https://github.com/shakewell-agency/passkit-laravel/issues)
- **Documentation**: [Full Documentation](https://github.com/shakewell-agency/passkit-laravel/wiki)
- **Community**: [Discussions](https://github.com/shakewell-agency/passkit-laravel/discussions)

## Requirements

- PHP 8.1+
- Laravel 10.0+ or 11.0+
- gRPC PHP extension
- PassKit API credentials

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Credits

Developed by [Shakewell Agency](https://shakewell.agency)

---

## Changelog

### v1.0.0
- Initial release
- Complete PassKit gRPC integration
- CRUD management system
- RESTful API endpoints
- Comprehensive testing suite
- Production-ready security