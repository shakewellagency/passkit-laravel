# Shakewell Wallet REST API Documentation

## Overview

The Shakewell Wallet REST API provides comprehensive endpoints for integrating loyalty programs, member management, digital wallet passes, and analytics into your applications. All API endpoints return JSON responses and follow REST conventions.

## Base URL

```
https://your-domain.com/api/wallet
```

## Authentication

All API requests require authentication using Bearer tokens or API keys.

### Headers

```http
Authorization: Bearer YOUR_API_KEY
# OR
X-API-Key: YOUR_API_KEY
Content-Type: application/json
Accept: application/json
```

### Rate Limiting

- **Default Rate Limit**: 1,000 requests per hour
- **Rate Limit Headers**: Included in all responses
  - `X-RateLimit-Limit`: Maximum requests per hour
  - `X-RateLimit-Remaining`: Remaining requests in current window
  - `X-RateLimit-Reset`: Unix timestamp when window resets

## Response Format

### Success Response

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

### Error Response

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    // Validation errors (if applicable)
  },
  "error_code": "ERROR_CODE",
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

## Pagination

List endpoints support pagination with the following parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Paginated responses include metadata:

```json
{
  "success": true,
  "data": {
    "items": [...],
    "meta": {
      "current_page": 1,
      "total_pages": 5,
      "total_count": 125,
      "per_page": 15
    }
  }
}
```

## Endpoints

### Programs

#### List Programs

```http
GET /api/wallet/programs
```

**Parameters:**
- `account_id` (optional): Filter by account ID
- `status` (optional): Filter by status (active, inactive, draft)
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response:**
```json
{
  "success": true,
  "data": {
    "programs": [
      {
        "id": 1,
        "passkit_id": "program_123",
        "name": "VIP Loyalty Program",
        "description": "Exclusive rewards for VIP customers",
        "program_type": "loyalty",
        "status": "active",
        "account_id": 1,
        "settings": {
          "points_per_dollar": 1,
          "welcome_points": 100,
          "minimum_redemption": 500
        },
        "created_at": "2024-01-01T00:00:00.000Z",
        "updated_at": "2024-01-01T00:00:00.000Z"
      }
    ],
    "meta": {
      "current_page": 1,
      "total_pages": 1,
      "total_count": 1,
      "per_page": 15
    }
  }
}
```

#### Create Program

```http
POST /api/wallet/programs
```

**Request Body:**
```json
{
  "name": "VIP Loyalty Program",
  "description": "Exclusive rewards for VIP customers",
  "program_type": "loyalty",
  "account_id": 1,
  "status": "active",
  "settings": {
    "points_per_dollar": 1,
    "welcome_points": 100,
    "minimum_redemption": 500,
    "points_expiry_days": 365
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "program": {
      "id": 1,
      "passkit_id": "program_123",
      "name": "VIP Loyalty Program",
      "description": "Exclusive rewards for VIP customers",
      "program_type": "loyalty",
      "status": "active",
      "account_id": 1,
      "settings": {
        "points_per_dollar": 1,
        "welcome_points": 100,
        "minimum_redemption": 500
      },
      "created_at": "2024-01-01T00:00:00.000Z",
      "updated_at": "2024-01-01T00:00:00.000Z"
    },
    "message": "Loyalty program created successfully"
  }
}
```

#### Get Program

```http
GET /api/wallet/programs/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "program": {
      "id": 1,
      "passkit_id": "program_123",
      "name": "VIP Loyalty Program",
      "description": "Exclusive rewards for VIP customers",
      "program_type": "loyalty",
      "status": "active",
      "account_id": 1,
      "settings": {
        "points_per_dollar": 1,
        "welcome_points": 100,
        "minimum_redemption": 500
      },
      "tiers": [...],
      "members": [...],
      "created_at": "2024-01-01T00:00:00.000Z",
      "updated_at": "2024-01-01T00:00:00.000Z"
    },
    "statistics": {
      "total_members": 150,
      "active_members": 142,
      "total_transactions": 1250,
      "total_points_issued": 15000
    }
  }
}
```

#### Update Program

```http
PUT /api/wallet/programs/{id}
```

**Request Body:** Same as create program

#### Delete Program

```http
DELETE /api/wallet/programs/{id}
```

### Members

#### List Members

```http
GET /api/wallet/members
```

**Parameters:**
- `account_id` (optional): Filter by account ID
- `program_id` (optional): Filter by program ID
- `status` (optional): Filter by status (active, inactive, suspended)
- `email` (optional): Search by email
- `external_id` (optional): Filter by external ID
- `page` (optional): Page number
- `per_page` (optional): Items per page

#### Create Member

```http
POST /api/wallet/members
```

**Request Body:**
```json
{
  "external_id": "customer_123",
  "program_id": 1,
  "tier_id": "bronze",
  "account_id": 1,
  "user_id": 1,
  "email": "customer@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+1234567890",
  "address_line1": "123 Main St",
  "city": "New York",
  "state": "NY",
  "postal_code": "10001",
  "country": "USA",
  "points_balance": 100,
  "preferences": {
    "language": "en",
    "notifications": true,
    "marketing": true
  },
  "custom_fields": {
    "birthday": "1990-01-01",
    "favorite_store": "NYC Store"
  },
  "tags": ["vip", "new-customer"]
}
```

#### Get Member

```http
GET /api/wallet/members/{id}
```

#### Update Member

```http
PUT /api/wallet/members/{id}
```

#### Update Member Points

```http
POST /api/wallet/members/{id}/points
```

**Request Body:**
```json
{
  "points": 50,
  "description": "Purchase reward",
  "reference_id": "order_456"
}
```

### Transactions

#### List Transactions

```http
GET /api/wallet/transactions
```

**Parameters:**
- `member_id` (optional): Filter by member ID
- `transaction_type` (optional): Filter by type (earn, burn, expire, transfer)
- `status` (optional): Filter by status (pending, completed, failed, cancelled)
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page

#### Create Transaction

```http
POST /api/wallet/transactions
```

**Request Body:**
```json
{
  "member_id": 1,
  "member_passkit_id": "member_123",
  "account_id": 1,
  "transaction_type": "earn",
  "points_amount": 50,
  "description": "Purchase reward",
  "reference_id": "order_456",
  "purchase_amount": 25.00,
  "purchase_currency": "USD",
  "points_multiplier": 2,
  "expires_at": "2025-01-01T00:00:00.000Z",
  "process_immediately": true
}
```

### Wallet Passes

#### List Wallet Passes

```http
GET /api/wallet/passes
```

**Parameters:**
- `user_id` (optional): Filter by user ID
- `account_id` (optional): Filter by account ID
- `status` (optional): Filter by status (active, expired, voided)
- `is_installed` (optional): Filter by installation status (true/false)
- `page` (optional): Page number
- `per_page` (optional): Items per page

#### Create Wallet Pass

```http
POST /api/wallet/passes
```

**Request Body:**
```json
{
  "member_id": "member_123",
  "user_id": 1,
  "account_id": 1,
  "pass_type": "loyalty",
  "pass_data": {
    "tier": "Gold",
    "special_offers": true
  }
}
```

### Synchronization

#### Get Sync Status

```http
GET /api/wallet/sync/status
```

**Parameters:**
- `account_id` (optional): Filter by account ID

**Response:**
```json
{
  "success": true,
  "data": {
    "sync_status": {
      "pending_members": 0,
      "pending_wallet_passes": 0,
      "last_sync_at": "2024-01-15T10:00:00.000Z",
      "sync_healthy": true
    }
  }
}
```

#### Trigger Sync

```http
POST /api/wallet/sync/trigger
```

**Request Body:**
```json
{
  "account_id": 1,
  "entity_type": "all",
  "force": false
}
```

### Analytics

#### Get Analytics

```http
GET /api/wallet/analytics
```

**Parameters:**
- `account_id` (required): Account ID
- `date_from` (optional): Start date (YYYY-MM-DD)
- `date_to` (optional): End date (YYYY-MM-DD)
- `metrics` (optional): Array of metrics to include (members, transactions, wallet_passes, points)

**Response:**
```json
{
  "success": true,
  "data": {
    "analytics": {
      "members": {
        "total": 1500,
        "active": 1450,
        "new_this_period": 25
      },
      "transactions": {
        "total": 15000,
        "this_period": 250,
        "earn_transactions": 12000,
        "burn_transactions": 3000
      },
      "wallet_passes": {
        "total": 1200,
        "installed": 980,
        "active": 1180
      },
      "points": {
        "total_issued": 150000,
        "total_redeemed": 45000,
        "outstanding_balance": 105000
      }
    },
    "period": {
      "from": "2024-01-01",
      "to": "2024-01-31"
    }
  }
}
```

## Error Codes

| Code | Description |
|------|-------------|
| `UNAUTHORIZED` | Invalid or missing API key |
| `RATE_LIMIT_EXCEEDED` | Too many requests |
| `VALIDATION_ERROR` | Request validation failed |
| `NOT_FOUND` | Resource not found |
| `FORBIDDEN` | Insufficient permissions |
| `INTERNAL_ERROR` | Server error |

## Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `429` - Too Many Requests
- `500` - Internal Server Error

## Examples

### Creating a Complete Loyalty Program

1. **Create Program:**
```bash
curl -X POST https://your-domain.com/api/wallet/programs \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Coffee Rewards",
    "description": "Earn points for every coffee purchase",
    "program_type": "loyalty",
    "account_id": 1,
    "settings": {
      "points_per_dollar": 1,
      "welcome_points": 50,
      "minimum_redemption": 100
    }
  }'
```

2. **Enroll Member:**
```bash
curl -X POST https://your-domain.com/api/wallet/members \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "external_id": "customer_001",
    "program_id": 1,
    "tier_id": "bronze",
    "account_id": 1,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "points_balance": 50
  }'
```

3. **Award Points:**
```bash
curl -X POST https://your-domain.com/api/wallet/members/1/points \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "points": 25,
    "description": "Coffee purchase reward",
    "reference_id": "order_123"
  }'
```

4. **Create Wallet Pass:**
```bash
curl -X POST https://your-domain.com/api/wallet/passes \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "member_id": "member_123",
    "user_id": 1,
    "account_id": 1,
    "pass_type": "loyalty"
  }'
```

## SDK

We provide official SDKs for popular programming languages:

- **PHP**: `composer require shakewell-agency/wallet-sdk-php`
- **JavaScript/Node.js**: `npm install @shakewell-agency/wallet-sdk-js`
- **Python**: `pip install shakewell-wallet-sdk`
- **Ruby**: `gem install shakewell_wallet_sdk`

### PHP SDK Example

```php
use ShakewellAgency\WalletSDK\Client;

$client = new Client('YOUR_API_KEY', 'https://your-domain.com');

// Create a member
$member = $client->members()->create([
    'external_id' => 'customer_123',
    'program_id' => 1,
    'tier_id' => 'bronze',
    'account_id' => 1,
    'email' => 'customer@example.com',
    'first_name' => 'John',
    'last_name' => 'Doe'
]);

// Award points
$client->members()->updatePoints($member['id'], 50, 'Purchase reward');

// Get analytics
$analytics = $client->analytics()->get(1, [
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31'
]);
```

## Testing

### Test Environment

Use the test endpoints for development and testing:

```
https://your-domain.com/api/wallet/test/connection
```

### Sample Data

Create sample data for testing:

```bash
curl -X POST https://your-domain.com/api/wallet/test/sample-data \
  -H "Authorization: Bearer YOUR_API_KEY"
```

## Support

For API support, please contact:
- **Email**: api-support@shakewell.agency
- **Documentation**: https://docs.shakewellwallet.com
- **Status Page**: https://status.shakewellwallet.com

## Changelog

### v1.0.0 (2024-01-15)
- Initial API release
- Full CRUD operations for programs, members, transactions, and wallet passes
- Analytics endpoints
- Sync management
- Rate limiting and authentication
- Comprehensive documentation