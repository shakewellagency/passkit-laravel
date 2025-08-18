# PassKit Laravel API Documentation

Complete API reference for the PassKit Laravel package.

## Table of Contents

- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Programs API](#programs-api)
- [Tiers API](#tiers-api)
- [Members API](#members-api)
- [Templates API](#templates-api)
- [Wallet Passes API](#wallet-passes-api)
- [System API](#system-api)

## Authentication

All API endpoints are protected by Laravel's default authentication middleware. Ensure your requests include proper authentication headers.

## Error Handling

All API responses follow a consistent error format:

```json
{
    "error": "Error message describing what went wrong",
    "errors": {
        "field_name": ["Validation error message"]
    }
}
```

HTTP Status Codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error
- `503` - Service Unavailable

## Programs API

### List Programs

**GET** `/api/passkit/programs`

Query Parameters:
- `account_id` (required) - Account ID to filter programs
- `type` (optional) - Program type filter (`membership`, `event_ticket`, `coupon`)

**Response:**
```json
{
    "programs": [
        {
            "id": 1,
            "passkit_id": "5nQZs5zikc5U1f7pFxnkah",
            "name": "VIP Membership",
            "description": "Exclusive VIP benefits",
            "program_type": "membership",
            "status": "active",
            "metadata": {},
            "account_id": 1,
            "created_at": "2024-08-17T10:00:00.000000Z",
            "updated_at": "2024-08-17T10:00:00.000000Z"
        }
    ]
}
```

### Create Program

**POST** `/api/passkit/programs`

**Request Body:**
```json
{
    "type": "membership",
    "account_id": 1,
    "name": "VIP Membership",
    "description": "Exclusive VIP benefits"
}
```

**Response:**
```json
{
    "success": true,
    "program": {
        "id": 1,
        "passkit_id": "5nQZs5zikc5U1f7pFxnkah",
        "name": "VIP Membership",
        "description": "Exclusive VIP benefits",
        "program_type": "membership",
        "status": "active",
        "metadata": {},
        "account_id": 1
    },
    "passkit_result": {
        "id": "5nQZs5zikc5U1f7pFxnkah",
        "success": true
    }
}
```

### Get Program

**GET** `/api/passkit/programs/{id}`

**Response:**
```json
{
    "program": {
        "id": 1,
        "passkit_id": "5nQZs5zikc5U1f7pFxnkah",
        "name": "VIP Membership",
        "description": "Exclusive VIP benefits",
        "program_type": "membership",
        "status": "active",
        "metadata": {},
        "account_id": 1,
        "tiers": [
            {
                "id": 1,
                "passkit_id": "purple_power",
                "name": "Purple Power",
                "description": "Premium tier"
            }
        ]
    }
}
```

### Update Program

**PUT** `/api/passkit/programs/{id}`

**Request Body:**
```json
{
    "name": "Updated VIP Membership",
    "description": "Updated description",
    "status": "active",
    "metadata": {
        "updated": true
    }
}
```

**Response:**
```json
{
    "success": true,
    "program": {
        "id": 1,
        "name": "Updated VIP Membership",
        "description": "Updated description"
    }
}
```

### Delete Program

**DELETE** `/api/passkit/programs/{id}`

**Response:**
```json
{
    "success": true
}
```

## Members API

### Create Member

**POST** `/api/passkit/members`

**Request Body:**
```json
{
    "tier_id": "purple_power",
    "user_id": 1,
    "account_id": 1,
    "external_id": "user_123",
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "points": 100
}
```

**Response:**
```json
{
    "success": true,
    "member_id": "6c8RWhgk1osmk9HoAtbHRh",
    "wallet_pass": {
        "id": 1,
        "passkit_id": "6c8RWhgk1osmk9HoAtbHRh",
        "user_id": 1,
        "account_id": 1,
        "pass_data": {
            "points": 100,
            "external_id": "user_123",
            "email": "user@example.com"
        },
        "status": "active",
        "is_installed": false
    },
    "install_urls": {
        "apple": "https://wallet.passkit.com/...",
        "google": "https://pay.google.com/..."
    },
    "qr_codes": {
        "apple": "data:image/png;base64,...",
        "google": "data:image/png;base64,..."
    }
}
```

### Get Member

**GET** `/api/passkit/members/{id}`

**Response:**
```json
{
    "member": {
        "id": "6c8RWhgk1osmk9HoAtbHRh",
        "external_id": "user_123",
        "email": "user@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "points": 100,
        "tier": {
            "id": "purple_power",
            "name": "Purple Power"
        },
        "status": "active",
        "created_at": "2024-08-17T10:00:00Z"
    }
}
```

### Update Member Points

**PUT** `/api/passkit/members/{id}/points`

**Request Body:**
```json
{
    "points": 50,
    "description": "Purchase bonus"
}
```

**Response:**
```json
{
    "success": true,
    "member_id": "6c8RWhgk1osmk9HoAtbHRh",
    "points_added": 50,
    "wallet_pass": {
        "id": 1,
        "pass_data": {
            "points": 150
        }
    }
}
```

### Get Installation Package

**GET** `/api/passkit/members/{id}/installation`

**Response:**
```json
{
    "package": {
        "member_id": "6c8RWhgk1osmk9HoAtbHRh",
        "install_urls": {
            "apple": "https://wallet.passkit.com/...",
            "google": "https://pay.google.com/..."
        },
        "qr_codes": {
            "apple": "data:image/png;base64,...",
            "google": "data:image/png;base64,..."
        },
        "expiry": "2024-08-24T10:00:00Z"
    }
}
```

### Send Notification

**POST** `/api/passkit/members/{id}/notification`

**Request Body:**
```json
{
    "message": "You earned 50 bonus points!",
    "points": 50
}
```

**Response:**
```json
{
    "success": true
}
```

### Delete Member

**DELETE** `/api/passkit/members/{id}`

**Response:**
```json
{
    "success": true
}
```

## Wallet Passes API

### Get Wallet Passes by User

**GET** `/api/passkit/wallet-passes/user/{userId}`

**Response:**
```json
{
    "wallet_passes": [
        {
            "id": 1,
            "passkit_id": "6c8RWhgk1osmk9HoAtbHRh",
            "user_id": 1,
            "account_id": 1,
            "pass_data": {
                "points": 150,
                "external_id": "user_123"
            },
            "status": "active",
            "is_installed": true,
            "installed_at": "2024-08-17T11:00:00Z"
        }
    ]
}
```

### Get Wallet Passes by Account

**GET** `/api/passkit/wallet-passes/account/{accountId}`

**Response:**
```json
{
    "wallet_passes": [
        {
            "id": 1,
            "passkit_id": "6c8RWhgk1osmk9HoAtbHRh",
            "user_id": 1,
            "account_id": 1,
            "pass_data": {
                "points": 150
            },
            "status": "active",
            "is_installed": true,
            "user": {
                "id": 1,
                "name": "John Doe",
                "email": "user@example.com"
            }
        }
    ]
}
```

## System API

### Health Check

**GET** `/api/passkit/health`

**Response:**
```json
{
    "passkit_api": true,
    "database": true,
    "overall": true
}
```

**Error Response (503):**
```json
{
    "passkit_api": false,
    "database": true,
    "overall": false,
    "error": "gRPC connection failed"
}
```

### System Statistics

**GET** `/api/passkit/stats`

**Response:**
```json
{
    "stats": {
        "programs": {
            "total": 5,
            "by_type": {
                "membership": 3,
                "event_ticket": 1,
                "coupon": 1
            }
        },
        "tiers": 8,
        "templates": 12,
        "wallet_passes": {
            "total": 1250,
            "active": 1200,
            "installed": 950
        },
        "total_points": 125000
    }
}
```

## Rate Limiting

API endpoints are subject to Laravel's default rate limiting:
- 60 requests per minute for authenticated users
- 6 requests per minute for guest users

## Pagination

List endpoints support pagination using Laravel's standard pagination parameters:
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 15, max: 100)

**Paginated Response:**
```json
{
    "data": [...],
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 75
}
```

## Webhooks

Configure webhook URL in your environment:
```env
PASSKIT_WEBHOOK_URL=https://your-app.com/api/passkit/webhook
PASSKIT_WEBHOOK_SECRET=your-webhook-secret
```

**Webhook Payload:**
```json
{
    "event": "member.points.updated",
    "member_id": "6c8RWhgk1osmk9HoAtbHRh",
    "data": {
        "points": 150,
        "points_added": 50,
        "description": "Purchase bonus"
    },
    "timestamp": "2024-08-17T10:00:00Z"
}
```

## SDK Examples

### PHP Laravel

```php
use ShakewellAgency\PassKitLaravel\Facades\PassKit;

// Create member with pass
$member = PassKit::enrollMember('purple_power', [
    'externalId' => 'user_123',
    'email' => 'user@example.com',
    'firstName' => 'John',
    'lastName' => 'Doe',
    'points' => 100
]);

// Update points
PassKit::updateMemberPoints($member['id'], 50, 'Purchase bonus');
```

### JavaScript/AJAX

```javascript
// Create member
const response = await fetch('/api/passkit/members', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    body: JSON.stringify({
        tier_id: 'purple_power',
        user_id: 1,
        account_id: 1,
        external_id: 'user_123',
        email: 'user@example.com',
        points: 100
    })
});

const member = await response.json();
```