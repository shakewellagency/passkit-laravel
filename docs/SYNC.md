# PassKit Laravel Sync System

Comprehensive data synchronization system for PassKit Laravel package with automated cron jobs, webhook fallback, and robust error handling.

## Overview

The PassKit sync system ensures data consistency between the PassKit API and your local database by providing:

- **Automated Cron Jobs**: Scheduled sync operations running at optimal intervals
- **Webhook Fallback**: Reliable data synchronization when webhooks are missed
- **Incremental Sync**: Efficient data updates based on timestamps
- **Full Sync**: Complete data refresh with validation
- **Error Recovery**: Automatic retry mechanisms with exponential backoff
- **Performance Monitoring**: Detailed metrics and alerting

## Architecture

### Core Components

1. **PassKitSyncService**: Main synchronization service handling all sync operations
2. **PassKitSyncCommand**: Artisan command for manual and automated sync execution
3. **PassKitSyncLog**: Database model for tracking sync operations and performance
4. **Scheduled Tasks**: Laravel task scheduler integration for automated cron jobs

### Sync Types

| Type | Frequency | Purpose | Timeout |
|------|-----------|---------|---------|
| `incremental` | Every 15 minutes | Recent changes only | 10 minutes |
| `members` | Every hour | Member profile updates | 30 minutes |
| `transactions` | Every 2 hours | Transaction history sync | 45 minutes |
| `programs` | Every 6 hours | Program configuration | 20 minutes |
| `templates` | Every 6 hours (offset) | Template updates | 20 minutes |
| `full` | Daily at 2 AM | Complete data refresh | 2 hours |
| `weekly` | Sunday at 3 AM | Deep sync with cleanup | 3 hours |

## Installation and Setup

### 1. Publish Configuration

```bash
# Publish PassKit configuration
php artisan vendor:publish --tag=passkit-config

# Publish migrations if not already done
php artisan vendor:publish --tag=passkit-migrations
php artisan migrate
```

### 2. Environment Configuration

Add these variables to your `.env` file:

```env
# Sync System Configuration
PASSKIT_SYNC_ENABLED=true
PASSKIT_SYNC_ALERT_EMAIL=admin@yourapp.com

# Batch Sizes (adjust based on your API limits)
PASSKIT_SYNC_BATCH_MEMBERS=100
PASSKIT_SYNC_BATCH_TRANSACTIONS=250

# Timeout Settings (in seconds)
PASSKIT_SYNC_TIMEOUT_FULL=7200
PASSKIT_SYNC_TIMEOUT_INCREMENTAL=600

# Retention Settings (in days)
PASSKIT_SYNC_RETENTION_LOGS=30
PASSKIT_SYNC_RETENTION_WEBHOOKS=90

# Performance and Alerting
PASSKIT_SYNC_FAILURE_THRESHOLD=3
PASSKIT_SYNC_PERFORMANCE_THRESHOLD=60

# Recovery Settings
PASSKIT_SYNC_MAX_RETRIES=3
PASSKIT_SYNC_RETRY_DELAY=300
PASSKIT_SYNC_EXPONENTIAL_BACKOFF=true
```

### 3. Laravel Task Scheduler

Ensure the Laravel task scheduler is running in your cron tab:

```bash
# Add this line to your server's crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Manual Sync Operations

### Basic Commands

```bash
# Incremental sync (recommended for regular use)
php artisan passkit:sync --type=incremental

# Full sync (complete data refresh)
php artisan passkit:sync --type=full --force

# Specific entity sync
php artisan passkit:sync --type=members
php artisan passkit:sync --type=transactions
php artisan passkit:sync --type=programs
php artisan passkit:sync --type=templates
```

### Advanced Options

```bash
# Dry run to see what would be synced
php artisan passkit:sync --type=incremental --dry-run

# Sync specific account
php artisan passkit:sync --type=members --account=123

# Sync with date range
php artisan passkit:sync --type=transactions --since="2024-01-01" --until="2024-01-31"

# Custom batch size
php artisan passkit:sync --type=members --chunk-size=50

# Force sync even if recent sync exists
php artisan passkit:sync --type=full --force
```

## Automated Scheduling

### Default Schedule

The package automatically registers these scheduled tasks:

```php
// Incremental sync every 15 minutes
$schedule->command('passkit:sync --type=incremental')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->onOneServer();

// Member sync every hour
$schedule->command('passkit:sync --type=members')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->onOneServer();

// Transaction sync every 2 hours
$schedule->command('passkit:sync --type=transactions')
    ->everyTwoHours()
    ->withoutOverlapping(45)
    ->runInBackground()
    ->onOneServer();

// Full sync daily at 2 AM
$schedule->command('passkit:sync --type=full --force')
    ->dailyAt('02:00')
    ->withoutOverlapping(120)
    ->runInBackground()
    ->onOneServer()
    ->emailOutputOnFailure(config('passkit.sync.alert_email'));
```

### Custom Scheduling

You can customize the schedule in your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Override default incremental sync to every 5 minutes
    $schedule->command('passkit:sync --type=incremental')
        ->everyFiveMinutes()
        ->withoutOverlapping(5)
        ->runInBackground()
        ->onOneServer();

    // Add account-specific sync
    $schedule->command('passkit:sync --type=members --account=123')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
}
```

## Monitoring and Logging

### Sync Logs

All sync operations are logged in the `passkit_sync_logs` table:

```php
use ShakewellAgency\PassKitLaravel\Models\PassKitSyncLog;

// Check recent sync status
$recentSyncs = PassKitSyncLog::where('created_at', '>', now()->subDay())
    ->orderBy('created_at', 'desc')
    ->get();

// Find failed syncs
$failedSyncs = PassKitSyncLog::where('status', 'failed')
    ->where('created_at', '>', now()->subWeek())
    ->get();

// Performance analysis
$performanceStats = PassKitSyncLog::where('status', 'completed')
    ->where('created_at', '>', now()->subWeek())
    ->selectRaw('
        sync_type,
        AVG(duration_seconds) as avg_duration,
        AVG(records_per_second) as avg_speed,
        SUM(successful_records) as total_synced
    ')
    ->groupBy('sync_type')
    ->get();
```

### Log Files

Sync operations generate detailed log files:

```bash
# Individual sync type logs
storage/logs/passkit-incremental-sync.log
storage/logs/passkit-member-sync.log
storage/logs/passkit-transaction-sync.log
storage/logs/passkit-full-sync.log

# Laravel application log
storage/logs/laravel.log
```

### Health Check Queries

```sql
-- Check sync health (last 24 hours)
SELECT 
    sync_type, 
    status, 
    COUNT(*) as count, 
    AVG(duration_seconds) as avg_duration,
    AVG(records_per_second) as avg_speed
FROM passkit_sync_logs 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY sync_type, status;

-- Find sync gaps (missed schedules)
SELECT 
    sync_type,
    MAX(started_at) as last_sync,
    TIMESTAMPDIFF(MINUTE, MAX(started_at), NOW()) as minutes_since_last
FROM passkit_sync_logs 
WHERE status = 'completed'
GROUP BY sync_type
HAVING minutes_since_last > 60; -- More than 1 hour

-- Performance trending
SELECT 
    DATE(started_at) as date,
    sync_type,
    AVG(records_per_second) as avg_speed,
    AVG(duration_seconds) as avg_duration
FROM passkit_sync_logs 
WHERE status = 'completed' 
  AND started_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(started_at), sync_type
ORDER BY date DESC, sync_type;
```

## Error Handling and Recovery

### Automatic Retry

The sync system includes automatic retry mechanisms:

```php
// Configuration in config/passkit.php
'recovery' => [
    'enabled' => true,
    'max_retries' => 3,
    'retry_delay' => 300, // 5 minutes
    'exponential_backoff' => true,
],
```

### Manual Error Recovery

```bash
# Retry failed syncs
php artisan passkit:sync --type=incremental --force

# Check specific sync log for errors
php artisan tinker
>>> $log = PassKitSyncLog::find(123);
>>> echo $log->error_message;
>>> print_r($log->error_details);
```

### Common Issues and Solutions

#### 1. API Rate Limiting

**Symptoms**: Sync fails with rate limit errors
**Solution**: Reduce batch sizes and increase delays

```env
PASSKIT_SYNC_BATCH_MEMBERS=50
PASSKIT_SYNC_BATCH_TRANSACTIONS=100
```

#### 2. Timeout Issues

**Symptoms**: Sync fails with timeout errors
**Solution**: Increase timeout values

```env
PASSKIT_SYNC_TIMEOUT_FULL=10800  # 3 hours
PASSKIT_SYNC_TIMEOUT_INCREMENTAL=1200  # 20 minutes
```

#### 3. Memory Issues

**Symptoms**: PHP memory limit exceeded
**Solution**: Reduce batch sizes or increase memory

```bash
# Increase PHP memory limit
php -d memory_limit=512M artisan passkit:sync --type=full
```

#### 4. Database Lock Issues

**Symptoms**: Sync fails with database lock errors
**Solution**: Ensure proper indexing and use smaller batches

```sql
-- Check for missing indexes
SHOW INDEX FROM passkit_members;
SHOW INDEX FROM passkit_transactions;

-- Optimize tables
OPTIMIZE TABLE passkit_members;
OPTIMIZE TABLE passkit_transactions;
```

## Performance Optimization

### Database Optimization

```sql
-- Create additional indexes for sync operations
CREATE INDEX idx_members_sync ON passkit_members (last_sync_at, status);
CREATE INDEX idx_transactions_sync ON passkit_transactions (created_at, member_passkit_id);
CREATE INDEX idx_sync_logs_performance ON passkit_sync_logs (sync_type, started_at, status);

-- For JSON field queries (MySQL 8.0+)
CREATE INDEX idx_member_preferences ON passkit_members ((CAST(preferences->'$.language' AS CHAR(5))));
```

### Configuration Tuning

```env
# Optimize batch sizes based on your hardware and API limits
PASSKIT_SYNC_BATCH_MEMBERS=200      # Increase if you have powerful hardware
PASSKIT_SYNC_BATCH_TRANSACTIONS=500 # Increase for faster transaction sync

# Adjust timeouts based on your data volume
PASSKIT_SYNC_TIMEOUT_FULL=14400     # 4 hours for very large datasets
PASSKIT_SYNC_TIMEOUT_INCREMENTAL=900 # 15 minutes for busy systems
```

### Monitoring Performance

```php
// Custom performance monitoring
use ShakewellAgency\PassKitLaravel\Models\PassKitSyncLog;

// Weekly performance report
$weeklyStats = PassKitSyncLog::where('created_at', '>', now()->subWeek())
    ->where('status', 'completed')
    ->selectRaw('
        sync_type,
        COUNT(*) as total_syncs,
        AVG(duration_seconds) as avg_duration,
        MIN(duration_seconds) as min_duration,
        MAX(duration_seconds) as max_duration,
        AVG(records_per_second) as avg_speed,
        SUM(successful_records) as total_records
    ')
    ->groupBy('sync_type')
    ->get();

foreach ($weeklyStats as $stat) {
    echo "Sync Type: {$stat->sync_type}\n";
    echo "Total Syncs: {$stat->total_syncs}\n";
    echo "Avg Duration: {$stat->avg_duration}s\n";
    echo "Avg Speed: {$stat->avg_speed} records/sec\n";
    echo "Total Records: {$stat->total_records}\n\n";
}
```

## Data Integrity

### Validation Checks

```bash
# Verify data consistency
php artisan passkit:sync --type=full --dry-run --validate

# Check for data gaps
php artisan tinker
>>> use App\Models\PassKitMember;
>>> use App\Models\PassKitTransaction;
>>> 
>>> // Find members without recent sync
>>> $staleMembers = PassKitMember::where('last_sync_at', '<', now()->subDays(7))->count();
>>> echo "Stale members: {$staleMembers}\n";
>>> 
>>> // Find transactions without member references
>>> $orphanedTransactions = PassKitTransaction::whereDoesntHave('member')->count();
>>> echo "Orphaned transactions: {$orphanedTransactions}\n";
```

### Data Cleanup

```php
// Automatic cleanup configuration
'retention' => [
    'sync_logs' => 30,        // Keep sync logs for 30 days
    'webhook_logs' => 90,     // Keep webhook logs for 90 days
    'notification_logs' => 180, // Keep notification logs for 180 days
],
```

## Security Considerations

### API Security

- Store PassKit credentials securely using Laravel's encryption
- Use environment variables for sensitive configuration
- Implement proper API rate limiting and retry logic
- Monitor for unusual sync patterns or failures

### Data Protection

- Ensure compliance with data protection regulations
- Implement proper access controls for sync logs
- Use secure connections (SSL/TLS) for all API communications
- Regularly audit sync operations and data access

### Error Handling

- Never log sensitive data in error messages
- Sanitize error outputs before storing in logs
- Implement proper exception handling for all sync operations
- Use Laravel's built-in security features for data validation

## Advanced Configuration

### Custom Sync Services

```php
// Extend the sync service for custom logic
namespace App\Services;

use ShakewellAgency\PassKitLaravel\Services\PassKitSyncService;

class CustomPassKitSyncService extends PassKitSyncService
{
    protected function syncSingleMember(array $memberData, PassKitProgram $program, array $options): void
    {
        // Add custom validation
        if (!$this->validateMemberData($memberData)) {
            throw new \InvalidArgumentException('Invalid member data');
        }

        // Call parent method
        parent::syncSingleMember($memberData, $program, $options);

        // Add custom post-processing
        $this->processCustomMemberFields($memberData);
    }

    private function validateMemberData(array $data): bool
    {
        // Custom validation logic
        return isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    }
}
```

### Multi-Tenant Configuration

```php
// Account-specific sync schedules
$schedule->command('passkit:sync --type=incremental --account=1')
    ->everyTenMinutes()
    ->withoutOverlapping();

$schedule->command('passkit:sync --type=incremental --account=2')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
```

## Troubleshooting

### Debug Mode

```bash
# Enable detailed logging
export PASSKIT_SYNC_DETAILED_LOGGING=true

# Run sync with verbose output
php artisan passkit:sync --type=incremental -v

# Check sync status
php artisan tinker
>>> $lastSync = PassKitSyncLog::latest()->first();
>>> echo "Status: {$lastSync->status}\n";
>>> echo "Duration: {$lastSync->duration_seconds}s\n";
>>> echo "Records: {$lastSync->successful_records}/{$lastSync->total_records}\n";
```

### Common Commands

```bash
# View recent sync logs
tail -f storage/logs/passkit-incremental-sync.log

# Check scheduled tasks
php artisan schedule:list | grep passkit

# Force restart all syncs
php artisan queue:restart
php artisan config:clear
php artisan cache:clear

# Database maintenance
php artisan migrate:fresh --seed  # Development only!
```

## Best Practices

1. **Monitor Performance**: Set up alerts for sync failures and performance degradation
2. **Test Regularly**: Use dry-run mode to test sync operations before production
3. **Backup Data**: Ensure proper database backups before major sync operations
4. **Scale Gradually**: Start with smaller batch sizes and increase based on performance
5. **Document Changes**: Keep track of configuration changes and their impact
6. **Security First**: Regularly review access logs and implement security best practices

## Support

For issues related to the PassKit sync system:

1. Check the sync logs for detailed error information
2. Review the configuration settings
3. Test with dry-run mode to isolate issues
4. Contact PassKit support for API-related issues
5. Report package bugs through the issue tracker

---

**Note**: This sync system is designed to handle webhook failures gracefully. However, webhooks should still be your primary method for real-time data updates, with the sync system serving as a reliable fallback mechanism.