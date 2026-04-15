<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Main audit log table
        Schema::create('passkit_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('account_id')->unsigned()->index();
            
            // Event details
            $table->string('event_type', 100)->index(); // create, update, delete, sync, export, etc.
            $table->string('entity_type', 100)->index(); // member, transaction, program, etc.
            $table->string('entity_id', 255)->nullable()->index();
            $table->string('passkit_id', 255)->nullable()->index();
            
            // User and system context
            $table->bigInteger('user_id')->unsigned()->nullable()->index();
            $table->string('user_type', 100)->nullable(); // admin, api, system, webhook
            $table->string('source', 100)->index(); // web, api, cron, webhook, command
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            
            // Operation details
            $table->string('operation', 100)->index(); // The specific operation performed
            $table->text('description')->nullable(); // Human-readable description
            $table->string('status', 50)->index(); // success, failed, warning, pending
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            
            // Data tracking
            $table->json('old_values')->nullable(); // Previous values (for updates)
            $table->json('new_values')->nullable(); // New values
            $table->json('metadata')->nullable(); // Additional context data
            $table->json('request_data')->nullable(); // Original request data
            $table->json('response_data')->nullable(); // API response data
            
            // Performance and context
            $table->decimal('execution_time_ms', 10, 3)->nullable(); // Execution time in milliseconds
            $table->string('correlation_id', 100)->nullable()->index(); // For tracing related operations
            $table->string('session_id', 100)->nullable()->index(); // User session tracking
            $table->string('batch_id', 100)->nullable()->index(); // For batch operations
            
            // API integration tracking
            $table->string('passkit_operation', 100)->nullable(); // Specific PassKit API call
            $table->integer('passkit_response_code')->nullable();
            $table->decimal('passkit_response_time_ms', 10, 3)->nullable();
            $table->json('passkit_request_headers')->nullable();
            $table->json('passkit_response_headers')->nullable();
            
            // Data validation and integrity
            $table->boolean('data_validated')->default(false);
            $table->json('validation_errors')->nullable();
            $table->string('checksum', 64)->nullable(); // Data integrity verification
            $table->boolean('sensitive_data_masked')->default(false);
            
            // Workflow and business context
            $table->string('workflow_step', 100)->nullable(); // Current step in workflow
            $table->string('business_context', 200)->nullable(); // Business operation context
            $table->decimal('business_impact_score', 5, 2)->nullable(); // 0-100 impact score
            $table->json('tags')->nullable(); // Searchable tags
            
            // Security and compliance
            $table->string('security_level', 50)->default('normal'); // low, normal, high, critical
            $table->boolean('requires_approval')->default(false);
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('gdpr_relevant')->default(false);
            $table->boolean('pci_relevant')->default(false);
            
            // Retention and archival
            $table->timestamp('expires_at')->nullable(); // When this log can be purged
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->string('archive_reason', 200)->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['account_id', 'event_type', 'created_at']);
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['source', 'operation', 'created_at']);
            $table->index(['correlation_id', 'batch_id']);
            $table->index(['business_context', 'created_at']);
            $table->index(['security_level', 'requires_approval']);
            $table->index(['archived', 'expires_at']);
        });

        // Security event logs (subset of audit logs for security-specific events)
        Schema::create('passkit_security_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('audit_log_id')->unsigned()->index();
            $table->bigInteger('account_id')->unsigned()->index();
            
            // Security event details
            $table->string('security_event', 100)->index(); // login_failed, unauthorized_access, data_breach, etc.
            $table->string('threat_level', 50)->index(); // low, medium, high, critical
            $table->string('source_ip', 45)->index();
            $table->json('geolocation')->nullable();
            $table->string('user_agent', 500)->nullable();
            
            // Attack patterns and detection
            $table->string('attack_pattern', 100)->nullable(); // sql_injection, xss, brute_force, etc.
            $table->boolean('automated_detection')->default(false);
            $table->string('detection_rule', 200)->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable(); // 0-100 confidence in threat
            
            // Response and mitigation
            $table->string('response_action', 100)->nullable(); // blocked, warned, logged, escalated
            $table->boolean('automatic_response')->default(false);
            $table->text('mitigation_steps')->nullable();
            $table->boolean('requires_investigation')->default(false);
            
            // Investigation tracking
            $table->bigInteger('assigned_to')->unsigned()->nullable();
            $table->string('investigation_status', 50)->default('pending'); // pending, in_progress, resolved, false_positive
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('investigation_completed_at')->nullable();
            $table->text('investigation_notes')->nullable();
            
            // Compliance and reporting
            $table->boolean('regulatory_reportable')->default(false);
            $table->json('regulatory_requirements')->nullable(); // GDPR, PCI-DSS, etc.
            $table->timestamp('reported_at')->nullable();
            $table->string('report_reference', 100)->nullable();
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('audit_log_id')->references('id')->on('passkit_audit_logs')->onDelete('cascade');
            
            // Security-specific indexes
            $table->index(['security_event', 'threat_level', 'created_at']);
            $table->index(['source_ip', 'created_at']);
            $table->index(['attack_pattern', 'confidence_score']);
            $table->index(['investigation_status', 'assigned_to']);
            $table->index(['regulatory_reportable', 'reported_at']);
        });

        // Performance and metrics tracking
        Schema::create('passkit_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('audit_log_id')->unsigned()->index();
            $table->bigInteger('account_id')->unsigned()->index();
            
            // Performance metrics
            $table->string('operation_type', 100)->index();
            $table->decimal('duration_ms', 10, 3)->index(); // Total operation time
            $table->decimal('api_call_time_ms', 10, 3)->nullable(); // PassKit API call time
            $table->decimal('database_time_ms', 10, 3)->nullable(); // Database operation time
            $table->decimal('validation_time_ms', 10, 3)->nullable(); // Validation time
            
            // Resource usage
            $table->bigInteger('memory_usage_bytes')->nullable();
            $table->bigInteger('peak_memory_usage_bytes')->nullable();
            $table->integer('cpu_usage_percent')->nullable();
            $table->integer('database_queries_count')->nullable();
            
            // Throughput and volume
            $table->integer('records_processed')->nullable();
            $table->decimal('records_per_second', 10, 2)->nullable();
            $table->bigInteger('data_size_bytes')->nullable();
            $table->decimal('data_transfer_rate_mbps', 10, 3)->nullable();
            
            // Quality metrics
            $table->decimal('success_rate_percent', 5, 2)->nullable();
            $table->integer('retry_count')->default(0);
            $table->boolean('cache_hit')->nullable();
            $table->decimal('cache_hit_rate_percent', 5, 2)->nullable();
            
            // Performance benchmarks
            $table->boolean('performance_threshold_exceeded')->default(false);
            $table->string('performance_grade', 10)->nullable(); // A, B, C, D, F
            $table->decimal('performance_score', 5, 2)->nullable(); // 0-100
            $table->json('performance_details')->nullable();
            
            // System resource context
            $table->decimal('system_load_average', 5, 2)->nullable();
            $table->integer('concurrent_operations')->nullable();
            $table->string('server_instance', 100)->nullable();
            $table->string('database_instance', 100)->nullable();
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('audit_log_id')->references('id')->on('passkit_audit_logs')->onDelete('cascade');
            
            // Performance-specific indexes
            $table->index(['operation_type', 'duration_ms']);
            $table->index(['performance_threshold_exceeded', 'created_at']);
            $table->index(['performance_grade', 'performance_score']);
            $table->index(['records_per_second', 'created_at']);
        });

        // Data change tracking for sensitive operations
        Schema::create('passkit_data_changes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('audit_log_id')->unsigned()->index();
            $table->bigInteger('account_id')->unsigned()->index();
            
            // Change details
            $table->string('table_name', 100)->index();
            $table->string('column_name', 100)->index();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('change_type', 50)->index(); // insert, update, delete
            
            // Data classification
            $table->string('data_classification', 50)->index(); // public, internal, confidential, restricted
            $table->boolean('pii_data')->default(false); // Personally Identifiable Information
            $table->boolean('financial_data')->default(false);
            $table->boolean('sensitive_data')->default(false);
            
            // Change impact assessment
            $table->string('impact_level', 50)->index(); // low, medium, high, critical
            $table->boolean('requires_notification')->default(false);
            $table->json('affected_systems')->nullable();
            $table->json('downstream_impacts')->nullable();
            
            // Approval workflow
            $table->boolean('requires_approval')->default(false);
            $table->bigInteger('approved_by')->unsigned()->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Rollback capability
            $table->boolean('rollback_available')->default(true);
            $table->timestamp('rollback_expires_at')->nullable();
            $table->json('rollback_instructions')->nullable();
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('audit_log_id')->references('id')->on('passkit_audit_logs')->onDelete('cascade');
            
            // Change tracking indexes
            $table->index(['table_name', 'column_name', 'created_at']);
            $table->index(['data_classification', 'sensitive_data']);
            $table->index(['impact_level', 'requires_notification']);
            $table->index(['requires_approval', 'approved_at']);
            $table->index(['rollback_available', 'rollback_expires_at']);
        });

        // Compliance and regulatory audit trail
        Schema::create('passkit_compliance_logs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('audit_log_id')->unsigned()->index();
            $table->bigInteger('account_id')->unsigned()->index();
            
            // Regulatory framework
            $table->string('regulation', 100)->index(); // GDPR, PCI-DSS, SOX, HIPAA, etc.
            $table->string('compliance_requirement', 200)->index();
            $table->string('control_objective', 200)->nullable();
            
            // Compliance status
            $table->string('compliance_status', 50)->index(); // compliant, non_compliant, partial, unknown
            $table->decimal('compliance_score', 5, 2)->nullable(); // 0-100
            $table->text('compliance_notes')->nullable();
            $table->json('compliance_evidence')->nullable();
            
            // Risk assessment
            $table->string('risk_level', 50)->index(); // low, medium, high, critical
            $table->text('risk_description')->nullable();
            $table->json('risk_factors')->nullable();
            $table->text('mitigation_measures')->nullable();
            
            // Audit trail
            $table->string('auditor', 200)->nullable();
            $table->timestamp('audit_date')->nullable();
            $table->string('audit_reference', 100)->nullable();
            $table->text('audit_findings')->nullable();
            
            // Remediation tracking
            $table->boolean('requires_remediation')->default(false);
            $table->text('remediation_plan')->nullable();
            $table->timestamp('remediation_deadline')->nullable();
            $table->bigInteger('assigned_to')->unsigned()->nullable();
            $table->string('remediation_status', 50)->default('pending'); // pending, in_progress, completed
            
            $table->timestamps();
            
            // Foreign key
            $table->foreign('audit_log_id')->references('id')->on('passkit_audit_logs')->onDelete('cascade');
            
            // Compliance-specific indexes
            $table->index(['regulation', 'compliance_status']);
            $table->index(['risk_level', 'requires_remediation']);
            $table->index(['remediation_status', 'remediation_deadline']);
            $table->index(['audit_date', 'auditor']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passkit_compliance_logs');
        Schema::dropIfExists('passkit_data_changes');
        Schema::dropIfExists('passkit_performance_logs');
        Schema::dropIfExists('passkit_security_logs');
        Schema::dropIfExists('passkit_audit_logs');
    }
};