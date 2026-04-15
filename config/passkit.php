<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PassKit API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for PassKit gRPC API integration
    |
    */

    'testing_mode' => env('PASSKIT_TESTING_MODE', false),

    'api_url' => env('PASSKIT_API_URL', 'https://grpc.pub2.passkit.io'),

    'certificate_path' => env('PASSKIT_CERTIFICATE_PATH', storage_path('app/passkit/certificate.pem')),

    'api' => [
        'host' => env('PASSKIT_API_HOST', 'grpc.pub2.passkit.io'),
        'port' => env('PASSKIT_API_PORT', 443),
        'timeout' => env('PASSKIT_API_TIMEOUT', 30000),
    ],

    'credentials' => [
        'certificate_path' => env('PASSKIT_CERTIFICATE_PATH', storage_path('app/passkit/certificate.pem')),
        'key_path' => env('PASSKIT_KEY_PATH', storage_path('app/passkit/private-key.pem')),
        'ca_file' => env('PASSKIT_CA_FILE', storage_path('app/passkit/ca-certificate.pem')),
    ],

    'templates' => [
        'default_membership' => [
            'name' => 'Default Membership Template',
            'description' => 'Default template for membership passes',
            'timezone' => 'America/Los_Angeles',
        ],
        'default_event_ticket' => [
            'name' => 'Default Event Ticket Template',
            'description' => 'Default template for event tickets',
            'timezone' => 'America/Los_Angeles',
        ],
        'default_coupon' => [
            'name' => 'Default Coupon Template',
            'description' => 'Default template for coupons',
            'timezone' => 'America/Los_Angeles',
        ],
    ],

    'notifications' => [
        'enabled' => env('PASSKIT_NOTIFICATIONS_ENABLED', true),
        'webhook_url' => env('PASSKIT_WEBHOOK_URL'),
        'webhook_secret' => env('PASSKIT_WEBHOOK_SECRET'),
    ],

    'qr_codes' => [
        'enabled' => env('PASSKIT_QR_CODES_ENABLED', true),
        'size' => env('PASSKIT_QR_CODE_SIZE', 300),
        'format' => env('PASSKIT_QR_CODE_FORMAT', 'png'),
    ],

    'cache' => [
        'enabled' => env('PASSKIT_CACHE_ENABLED', true),
        'ttl' => env('PASSKIT_CACHE_TTL', 3600), // 1 hour
        'prefix' => 'passkit:',
    ],

    'logging' => [
        'enabled' => env('PASSKIT_LOGGING_ENABLED', true),
        'level' => env('PASSKIT_LOG_LEVEL', 'info'),
        'channel' => env('PASSKIT_LOG_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated data synchronization between PassKit API
    | and local database. Used by cron jobs and scheduled tasks.
    |
    */

    'sync' => [
        'enabled' => env('PASSKIT_SYNC_ENABLED', true),
        
        'schedules' => [
            'incremental' => env('PASSKIT_SYNC_INCREMENTAL', '*/15 * * * *'), // Every 15 minutes
            'members' => env('PASSKIT_SYNC_MEMBERS', '0 * * * *'), // Every hour
            'transactions' => env('PASSKIT_SYNC_TRANSACTIONS', '0 */2 * * *'), // Every 2 hours
            'programs' => env('PASSKIT_SYNC_PROGRAMS', '0 */6 * * *'), // Every 6 hours
            'templates' => env('PASSKIT_SYNC_TEMPLATES', '30 */6 * * *'), // Every 6 hours (offset)
            'full' => env('PASSKIT_SYNC_FULL', '0 2 * * *'), // Daily at 2 AM
            'weekly' => env('PASSKIT_SYNC_WEEKLY', '0 3 * * 0'), // Sunday at 3 AM
        ],

        'batch_sizes' => [
            'members' => env('PASSKIT_SYNC_BATCH_MEMBERS', 100),
            'transactions' => env('PASSKIT_SYNC_BATCH_TRANSACTIONS', 250),
            'notifications' => env('PASSKIT_SYNC_BATCH_NOTIFICATIONS', 500),
            'webhooks' => env('PASSKIT_SYNC_BATCH_WEBHOOKS', 1000),
        ],

        'timeouts' => [
            'incremental' => env('PASSKIT_SYNC_TIMEOUT_INCREMENTAL', 600), // 10 minutes
            'members' => env('PASSKIT_SYNC_TIMEOUT_MEMBERS', 1800), // 30 minutes
            'transactions' => env('PASSKIT_SYNC_TIMEOUT_TRANSACTIONS', 2700), // 45 minutes
            'programs' => env('PASSKIT_SYNC_TIMEOUT_PROGRAMS', 1200), // 20 minutes
            'templates' => env('PASSKIT_SYNC_TIMEOUT_TEMPLATES', 1200), // 20 minutes
            'full' => env('PASSKIT_SYNC_TIMEOUT_FULL', 7200), // 2 hours
            'weekly' => env('PASSKIT_SYNC_TIMEOUT_WEEKLY', 10800), // 3 hours
        ],

        'retention' => [
            'sync_logs' => env('PASSKIT_SYNC_RETENTION_LOGS', 30), // Days to keep sync logs
            'webhook_logs' => env('PASSKIT_SYNC_RETENTION_WEBHOOKS', 90), // Days to keep webhook logs
            'notification_logs' => env('PASSKIT_SYNC_RETENTION_NOTIFICATIONS', 180), // Days to keep notification logs
        ],

        'alerting' => [
            'enabled' => env('PASSKIT_SYNC_ALERTING_ENABLED', true),
            'alert_email' => env('PASSKIT_SYNC_ALERT_EMAIL', env('MAIL_FROM_ADDRESS')),
            'failure_threshold' => env('PASSKIT_SYNC_FAILURE_THRESHOLD', 3), // Failed syncs before alert
            'performance_threshold' => env('PASSKIT_SYNC_PERFORMANCE_THRESHOLD', 60), // Seconds per 1000 records
        ],

        'recovery' => [
            'enabled' => env('PASSKIT_SYNC_RECOVERY_ENABLED', true),
            'max_retries' => env('PASSKIT_SYNC_MAX_RETRIES', 3),
            'retry_delay' => env('PASSKIT_SYNC_RETRY_DELAY', 300), // 5 minutes
            'exponential_backoff' => env('PASSKIT_SYNC_EXPONENTIAL_BACKOFF', true),
        ],

        'features' => [
            'dry_run_first' => env('PASSKIT_SYNC_DRY_RUN_FIRST', false),
            'validate_before_sync' => env('PASSKIT_SYNC_VALIDATE_BEFORE', true),
            'cleanup_old_data' => env('PASSKIT_SYNC_CLEANUP_OLD_DATA', true),
            'performance_monitoring' => env('PASSKIT_SYNC_PERFORMANCE_MONITORING', true),
            'detailed_logging' => env('PASSKIT_SYNC_DETAILED_LOGGING', false),
        ],
    ],
];