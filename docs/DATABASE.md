# PassKit Laravel Database Schema

Complete database schema documentation for the PassKit Laravel package.

## Overview

The PassKit Laravel package provides a comprehensive database schema designed to store all PassKit-related data locally in MySQL. This ensures data persistence, enables advanced querying, analytics, and provides offline capabilities.

## Database Tables

### Core Entity Tables

#### 1. `pass_kit_programs`
Main programs table for loyalty programs, event campaigns, and coupon systems.

```sql
CREATE TABLE pass_kit_programs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    program_type ENUM('membership', 'event_ticket', 'coupon') DEFAULT 'membership',
    status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
    metadata JSON,
    account_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_account_status (account_id, status),
    INDEX idx_type_status (program_type, status)
);
```

**Purpose**: Stores PassKit programs with local association to accounts and comprehensive metadata.

#### 2. `pass_kit_tiers`
Membership tiers within programs (Bronze, Silver, Gold, etc.).

```sql
CREATE TABLE pass_kit_tiers (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_id VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    program_id BIGINT UNSIGNED NOT NULL,
    template_id VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES pass_kit_programs(id) ON DELETE CASCADE,
    INDEX idx_program (program_id)
);
```

**Purpose**: Defines membership levels with benefits and requirements.

#### 3. `card_templates`
Pass design templates and layout configurations.

```sql
CREATE TABLE card_templates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_template_id VARCHAR(255) UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    template_type ENUM('membership', 'event_ticket', 'coupon', 'boarding_pass', 'store_card') DEFAULT 'membership',
    account_id BIGINT UNSIGNED NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    template_data JSON,           -- PassKit template configuration
    field_definitions JSON,       -- Field mappings and definitions
    design_settings JSON,         -- Colors, fonts, layout
    pass_settings JSON,           -- Pass-specific settings
    timezone VARCHAR(255) DEFAULT 'America/Los_Angeles',
    protocol VARCHAR(255),
    locations JSON,               -- Geo-locations for relevance
    beacons JSON,                 -- iBeacon data
    expiry_date DATETIME,
    allow_sharing BOOLEAN DEFAULT TRUE,
    voided BOOLEAN DEFAULT FALSE,
    max_distance INT,             -- For location-based relevance
    last_used_at TIMESTAMP,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_account_active (account_id, is_active),
    INDEX idx_type_active (template_type, is_active),
    INDEX idx_passkit_template (passkit_template_id),
    INDEX idx_last_used (last_used_at)
);
```

**Purpose**: Comprehensive template management with design settings and usage tracking.

### Member & Transaction Tables

#### 4. `passkit_members`
Complete member profiles with points, preferences, and demographics.

```sql
CREATE TABLE passkit_members (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_id VARCHAR(255) UNIQUE NOT NULL,
    external_id VARCHAR(255) NOT NULL,
    user_id BIGINT UNSIGNED,
    account_id BIGINT UNSIGNED NOT NULL,
    program_id BIGINT UNSIGNED NOT NULL,
    tier_id VARCHAR(255),
    
    -- Member information
    email VARCHAR(255),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    phone VARCHAR(255),
    date_of_birth DATE,
    gender ENUM('M', 'F', 'O'),
    
    -- Address information
    address_line1 VARCHAR(255),
    address_line2 VARCHAR(255),
    city VARCHAR(255),
    state VARCHAR(255),
    postal_code VARCHAR(255),
    country CHAR(2),
    
    -- Points and rewards
    points_balance INT DEFAULT 0,
    lifetime_points INT DEFAULT 0,
    points_to_next_tier INT,
    tier_progress DECIMAL(5,2) DEFAULT 0,
    
    -- Member status and preferences
    status ENUM('active', 'inactive', 'suspended', 'expired') DEFAULT 'active',
    opt_in_status ENUM('opted_in', 'opted_out') DEFAULT 'opted_in',
    email_opt_in BOOLEAN DEFAULT TRUE,
    sms_opt_in BOOLEAN DEFAULT FALSE,
    push_opt_in BOOLEAN DEFAULT TRUE,
    
    -- Enrollment and activity
    enrolled_at DATETIME NOT NULL,
    enrollment_channel VARCHAR(255),
    last_activity_at DATETIME,
    tier_achieved_at DATETIME,
    
    -- Custom data and preferences
    preferences JSON,
    custom_fields JSON,
    tags JSON,
    passkit_data JSON,
    last_sync_at DATETIME,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES pass_kit_programs(id) ON DELETE CASCADE,
    INDEX idx_account_status (account_id, status),
    INDEX idx_program_status (program_id, status),
    INDEX idx_external_account (external_id, account_id),
    INDEX idx_email (email),
    INDEX idx_enrolled (enrolled_at),
    INDEX idx_activity (last_activity_at),
    INDEX idx_points (points_balance),
    INDEX idx_tier (tier_id)
);
```

**Purpose**: Complete member profiles with demographics, preferences, and loyalty tracking.

#### 5. `passkit_transactions`
Detailed transaction history for points, purchases, and redemptions.

```sql
CREATE TABLE passkit_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_transaction_id VARCHAR(255) UNIQUE,
    member_passkit_id VARCHAR(255) NOT NULL,
    member_id BIGINT UNSIGNED,
    account_id BIGINT UNSIGNED NOT NULL,
    
    -- Transaction details
    transaction_type ENUM('earn', 'burn', 'expire', 'adjustment', 'refund', 'bonus') DEFAULT 'earn',
    points_amount INT NOT NULL,
    points_balance_before INT NOT NULL,
    points_balance_after INT NOT NULL,
    
    -- Transaction metadata
    description VARCHAR(255),
    reference_id VARCHAR(255),
    source VARCHAR(255),
    category VARCHAR(255),
    
    -- Location and context
    location_id VARCHAR(255),
    location_name VARCHAR(255),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    
    -- Purchase details
    purchase_amount DECIMAL(10,2),
    currency CHAR(3),
    points_multiplier DECIMAL(5,2),
    
    -- Expiry information
    expires_at DATETIME,
    is_expired BOOLEAN DEFAULT FALSE,
    expired_at DATETIME,
    
    -- Status and processing
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'reversed') DEFAULT 'completed',
    failure_reason VARCHAR(255),
    processed_at DATETIME,
    
    -- API and webhook data
    webhook_data JSON,
    passkit_data JSON,
    
    -- Audit and reversal tracking
    created_by VARCHAR(255),
    reversed_by_transaction_id VARCHAR(255),
    reversal_transaction_id BIGINT UNSIGNED,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES passkit_members(id) ON DELETE CASCADE,
    INDEX idx_member_type (member_passkit_id, transaction_type),
    INDEX idx_account_date (account_id, created_at),
    INDEX idx_type_status (transaction_type, status),
    INDEX idx_reference (reference_id),
    INDEX idx_processed (processed_at),
    INDEX idx_expiry (expires_at, is_expired),
    INDEX idx_location (location_id),
    INDEX idx_reporting (created_at, points_amount)
);
```

**Purpose**: Complete transaction history with location, purchase context, and audit trails.

### Communication Tables

#### 6. `passkit_notifications`
Push notifications, emails, and SMS communications.

```sql
CREATE TABLE passkit_notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_notification_id VARCHAR(255) UNIQUE,
    member_passkit_id VARCHAR(255) NOT NULL,
    member_id BIGINT UNSIGNED,
    account_id BIGINT UNSIGNED NOT NULL,
    
    -- Notification content
    title VARCHAR(255),
    message TEXT NOT NULL,
    notification_type ENUM('push', 'email', 'sms', 'in_app') DEFAULT 'push',
    category ENUM('points_earned', 'points_burned', 'tier_upgrade', 'promotion', 'expiry_warning', 'birthday', 'welcome', 'custom') DEFAULT 'custom',
    
    -- Delivery tracking
    status ENUM('pending', 'sent', 'delivered', 'failed', 'cancelled') DEFAULT 'pending',
    scheduled_at DATETIME,
    sent_at DATETIME,
    delivered_at DATETIME,
    failed_at DATETIME,
    failure_reason VARCHAR(255),
    
    -- Personalization and targeting
    personalization_data JSON,
    language CHAR(5) DEFAULT 'en',
    timezone VARCHAR(255),
    
    -- Campaign and context
    transaction_id BIGINT UNSIGNED,
    campaign_id VARCHAR(255),
    metadata JSON,
    
    -- Engagement tracking
    opened BOOLEAN DEFAULT FALSE,
    opened_at DATETIME,
    clicked BOOLEAN DEFAULT FALSE,
    clicked_at DATETIME,
    click_url VARCHAR(255),
    
    -- PassKit integration
    passkit_payload JSON,
    passkit_response JSON,
    
    -- Retry logic
    retry_count INT DEFAULT 0,
    next_retry_at DATETIME,
    max_retries INT DEFAULT 3,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES passkit_members(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES passkit_transactions(id) ON DELETE SET NULL,
    INDEX idx_member_status (member_passkit_id, status),
    INDEX idx_account_date (account_id, created_at),
    INDEX idx_type_status (notification_type, status),
    INDEX idx_category_sent (category, sent_at),
    INDEX idx_scheduled (scheduled_at, status),
    INDEX idx_campaign (campaign_id),
    INDEX idx_retry (status, next_retry_at),
    INDEX idx_engagement (sent_at, opened)
);
```

**Purpose**: Complete notification system with delivery tracking and engagement analytics.

#### 7. `passkit_webhooks`
Webhook event processing and audit trail.

```sql
CREATE TABLE passkit_webhooks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    webhook_id VARCHAR(255) UNIQUE NOT NULL,
    event_type VARCHAR(255) NOT NULL,
    event_id VARCHAR(255),
    
    -- Request details
    source_ip VARCHAR(255),
    user_agent VARCHAR(255),
    headers JSON,
    payload LONGTEXT NOT NULL,
    signature VARCHAR(255),
    
    -- Processing status
    status ENUM('pending', 'processing', 'processed', 'failed', 'ignored') DEFAULT 'pending',
    processed_at DATETIME,
    error_message TEXT,
    retry_count INT DEFAULT 0,
    next_retry_at DATETIME,
    
    -- Extracted data
    member_passkit_id VARCHAR(255),
    member_id BIGINT UNSIGNED,
    account_id BIGINT UNSIGNED,
    extracted_data JSON,
    
    -- Related entities
    transaction_id BIGINT UNSIGNED,
    notification_id BIGINT UNSIGNED,
    
    -- Validation and deduplication
    signature_valid BOOLEAN,
    duplicate_event BOOLEAN DEFAULT FALSE,
    idempotency_key VARCHAR(255),
    
    -- Processing metadata
    processor_version VARCHAR(255),
    processing_metadata JSON,
    processing_time_ms DECIMAL(10,3),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (member_id) REFERENCES passkit_members(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES passkit_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (notification_id) REFERENCES passkit_notifications(id) ON DELETE SET NULL,
    INDEX idx_event_status (event_type, status),
    INDEX idx_member (member_passkit_id),
    INDEX idx_account_date (account_id, created_at),
    INDEX idx_retry (status, next_retry_at),
    INDEX idx_event_id (event_id),
    INDEX idx_idempotency (idempotency_key),
    INDEX idx_processed (processed_at),
    INDEX idx_reporting (created_at, event_type)
);
```

**Purpose**: Webhook processing with deduplication, retry logic, and audit trails.

### Enhanced Wallet Pass Table

#### 8. `wallet_passes` (Enhanced)
Comprehensive wallet pass tracking with device information and analytics.

```sql
CREATE TABLE wallet_passes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    passkit_id VARCHAR(255) UNIQUE NOT NULL,
    member_passkit_id VARCHAR(255),
    user_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED,
    program_id BIGINT UNSIGNED,
    
    -- Pass content and data
    pass_data JSON NOT NULL,
    field_values JSON,
    barcode_data JSON,
    
    -- Installation and distribution
    status ENUM('active', 'inactive', 'suspended', 'expired', 'voided') DEFAULT 'active',
    is_installed BOOLEAN DEFAULT FALSE,
    installed_at TIMESTAMP,
    install_urls JSON,
    qr_codes JSON,
    
    -- Device information
    device_library_identifier VARCHAR(255),
    push_token VARCHAR(255),
    device_type ENUM('ios', 'android', 'web'),
    device_model VARCHAR(255),
    os_version VARCHAR(255),
    app_version VARCHAR(255),
    
    -- Pass lifecycle
    issued_at DATETIME NOT NULL,
    first_install_at DATETIME,
    last_updated_at DATETIME,
    expires_at DATETIME,
    voided_at DATETIME,
    voided_reason VARCHAR(255),
    
    -- Location and relevance
    relevant_locations JSON,
    relevant_beacons JSON,
    relevant_date DATETIME,
    
    -- Usage tracking
    update_count INT DEFAULT 0,
    last_viewed_at DATETIME,
    view_count INT DEFAULT 0,
    usage_analytics JSON,
    
    -- Sharing and distribution
    sharable BOOLEAN DEFAULT TRUE,
    share_count INT DEFAULT 0,
    share_analytics JSON,
    
    -- Synchronization
    last_sync_at DATETIME,
    sync_metadata JSON,
    sync_pending BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (template_id) REFERENCES card_templates(id) ON DELETE SET NULL,
    FOREIGN KEY (program_id) REFERENCES pass_kit_programs(id) ON DELETE SET NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_account_status (account_id, status),
    INDEX idx_program_status (program_id, status),
    INDEX idx_member (member_passkit_id),
    INDEX idx_installed (is_installed, status),
    INDEX idx_device (device_library_identifier),
    INDEX idx_issued (issued_at),
    INDEX idx_expiry (expires_at, status),
    INDEX idx_sync (last_sync_at, sync_pending),
    INDEX idx_device_type (device_type, os_version)
);
```

**Purpose**: Complete wallet pass lifecycle management with device tracking and analytics.

### Analytics & Monitoring Tables

#### 9. `passkit_analytics`
Pre-calculated analytics and reporting data.

```sql
CREATE TABLE passkit_analytics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    metric_type VARCHAR(255) NOT NULL,
    metric_name VARCHAR(255) NOT NULL,
    
    -- Dimensional data
    program_id BIGINT UNSIGNED,
    tier_id VARCHAR(255),
    location_id VARCHAR(255),
    channel VARCHAR(255),
    device_type VARCHAR(255),
    country CHAR(2),
    region VARCHAR(255),
    city VARCHAR(255),
    
    -- Metric values
    count_value BIGINT DEFAULT 0,
    sum_value DECIMAL(15,2) DEFAULT 0,
    avg_value DECIMAL(15,4),
    min_value DECIMAL(15,2),
    max_value DECIMAL(15,2),
    
    -- Additional context
    dimensions JSON,
    metadata JSON,
    
    -- Data freshness
    calculated_at DATETIME NOT NULL,
    data_updated_at DATETIME,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES pass_kit_programs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_analytics (account_id, date, metric_type, metric_name, program_id, tier_id, location_id, channel),
    INDEX idx_account_date_type (account_id, date, metric_type),
    INDEX idx_program_date (program_id, date),
    INDEX idx_metric_date (metric_type, metric_name, date),
    INDEX idx_date_count (date, count_value),
    INDEX idx_location_date (location_id, date),
    INDEX idx_channel_date (channel, date),
    INDEX idx_calculated (calculated_at)
);
```

**Purpose**: Pre-calculated metrics for fast reporting and dashboards.

#### 10. `passkit_sync_logs`
Synchronization monitoring and audit trails.

```sql
CREATE TABLE passkit_sync_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    
    -- Sync operation details
    sync_type ENUM('full', 'incremental', 'member', 'transaction', 'notification', 'webhook') DEFAULT 'incremental',
    sync_direction ENUM('import', 'export', 'bidirectional') DEFAULT 'import',
    status ENUM('started', 'in_progress', 'completed', 'failed', 'cancelled') DEFAULT 'started',
    
    -- Sync scope
    program_id BIGINT UNSIGNED,
    entity_type VARCHAR(255),
    entity_id VARCHAR(255),
    
    -- Progress tracking
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    successful_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    skipped_records INT DEFAULT 0,
    
    -- Timing information
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    duration_seconds INT,
    records_per_second DECIMAL(10,2),
    
    -- Error handling
    error_message TEXT,
    error_details JSON,
    failed_record_ids JSON,
    
    -- Sync configuration
    sync_from_date DATETIME,
    sync_to_date DATETIME,
    sync_filters JSON,
    sync_options JSON,
    
    -- API performance
    api_requests_made INT DEFAULT 0,
    api_requests_failed INT DEFAULT 0,
    avg_api_response_time_ms DECIMAL(10,3),
    
    -- Change tracking
    changes_summary JSON,
    detailed_log LONGTEXT,
    
    -- Automation
    triggered_by VARCHAR(255),
    trigger_source VARCHAR(255),
    next_sync_at DATETIME,
    sync_frequency VARCHAR(255),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (program_id) REFERENCES pass_kit_programs(id) ON DELETE SET NULL,
    INDEX idx_account_started (account_id, started_at),
    INDEX idx_type_status (sync_type, status),
    INDEX idx_program_started (program_id, started_at),
    INDEX idx_status_completed (status, completed_at),
    INDEX idx_trigger_started (trigger_source, started_at),
    INDEX idx_next_sync (next_sync_at),
    INDEX idx_entity (entity_type, entity_id)
);
```

**Purpose**: Comprehensive sync monitoring with performance metrics and scheduling.

## Installation and Usage

### Running Migrations

```bash
# Publish and run migrations
php artisan vendor:publish --tag=passkit-migrations
php artisan migrate
```

### Seeding Sample Data

```bash
# Create sample data for testing
php artisan db:seed --class="ShakewellAgency\PassKitLaravel\Database\Seeders\PassKitSeeder"
```

### Database Optimization

#### Recommended MySQL Configuration

```sql
-- For JSON column performance
SET GLOBAL innodb_default_row_format = 'DYNAMIC';

-- For better JSON indexing (MySQL 8.0+)
CREATE INDEX idx_member_preferences ON passkit_members ((CAST(preferences->'$.language' AS CHAR(5))));
CREATE INDEX idx_pass_points ON wallet_passes ((CAST(pass_data->'$.points' AS UNSIGNED)));
```

#### Index Recommendations

```sql
-- For reporting queries
CREATE INDEX idx_transactions_reporting ON passkit_transactions (account_id, created_at, transaction_type, points_amount);
CREATE INDEX idx_notifications_engagement ON passkit_notifications (account_id, sent_at, opened, clicked);

-- For member analytics
CREATE INDEX idx_members_analytics ON passkit_members (account_id, program_id, status, enrolled_at, points_balance);
```

## Schema Benefits

### 1. **Complete Data Persistence**
- All PassKit data stored locally
- No dependency on external API for queries
- Offline capability for read operations

### 2. **Advanced Analytics**
- Pre-calculated metrics in `passkit_analytics`
- Comprehensive transaction history
- Member behavior tracking
- Engagement analytics

### 3. **Audit Trails**
- Complete webhook processing logs
- Transaction reversal tracking
- Sync operation monitoring
- Change history preservation

### 4. **Performance Optimization**
- Strategic indexing for common queries
- JSON field optimization
- Efficient foreign key relationships
- Partitioning-ready design

### 5. **Scalability Features**
- Designed for millions of members
- Efficient batch operations
- Background sync capabilities
- Analytics data aggregation

## Maintenance

### Regular Cleanup

```sql
-- Clean up old webhook logs (keep 90 days)
DELETE FROM passkit_webhooks WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Clean up old sync logs (keep 30 days)
DELETE FROM passkit_sync_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Archive old notifications (keep 180 days)
DELETE FROM passkit_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
```

### Monitoring Queries

```sql
-- Check sync health
SELECT sync_type, status, COUNT(*) as count, AVG(duration_seconds) as avg_duration
FROM passkit_sync_logs 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY sync_type, status;

-- Member growth
SELECT DATE(enrolled_at) as date, COUNT(*) as new_members
FROM passkit_members 
WHERE enrolled_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(enrolled_at)
ORDER BY date;

-- Transaction volume
SELECT DATE(created_at) as date, 
       transaction_type,
       COUNT(*) as count,
       SUM(points_amount) as total_points
FROM passkit_transactions 
WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), transaction_type
ORDER BY date, transaction_type;
```