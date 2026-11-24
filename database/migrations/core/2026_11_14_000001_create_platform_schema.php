<?php
declare(strict_types=1);

/**
 * Core platform schema: tenants, plans, subscriptions, licenses, invoices,
 * platform users, audit trail and login throttling.
 *
 * @return list<string>
 */
return [
    'CREATE TABLE IF NOT EXISTS plans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(40) NOT NULL,
        name VARCHAR(100) NOT NULL,
        price_monthly DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        features TEXT NULL,
        max_students INT UNSIGNED NOT NULL DEFAULT 0,
        max_users INT UNSIGNED NOT NULL DEFAULT 0,
        ai_quota INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_plans_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS tenants (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(63) NOT NULL,
        db_name VARCHAR(64) NOT NULL,
        level VARCHAR(30) NOT NULL DEFAULT "SMP",
        status VARCHAR(20) NOT NULL DEFAULT "active",
        domain VARCHAR(190) NULL,
        email VARCHAR(190) NULL,
        phone VARCHAR(40) NULL,
        address TEXT NULL,
        timezone VARCHAR(64) NOT NULL DEFAULT "Asia/Jakarta",
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_tenants_slug (slug),
        UNIQUE KEY uniq_tenants_db_name (db_name),
        UNIQUE KEY uniq_tenants_domain (domain),
        KEY idx_tenants_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS subscriptions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        plan_id INT UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "active",
        billing_cycle VARCHAR(20) NOT NULL DEFAULT "monthly",
        started_at DATETIME NOT NULL,
        current_period_end DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_subscriptions_tenant (tenant_id),
        CONSTRAINT fk_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES plans (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS licenses (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        plan_id INT UNSIGNED NOT NULL,
        license_key_hash VARCHAR(255) NULL,
        status VARCHAR(20) NOT NULL DEFAULT "trial",
        starts_at DATETIME NOT NULL,
        ends_at DATETIME NULL,
        grace_days SMALLINT UNSIGNED NOT NULL DEFAULT 14,
        features TEXT NULL,
        max_students INT UNSIGNED NOT NULL DEFAULT 0,
        max_users INT UNSIGNED NOT NULL DEFAULT 0,
        ai_quota INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_licenses_tenant (tenant_id),
        CONSTRAINT fk_licenses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_licenses_plan FOREIGN KEY (plan_id) REFERENCES plans (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS invoices (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tenant_id INT UNSIGNED NOT NULL,
        subscription_id INT UNSIGNED NULL,
        number VARCHAR(60) NOT NULL,
        amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        due_at DATETIME NULL,
        paid_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_invoices_number (number),
        KEY idx_invoices_tenant (tenant_id),
        CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE,
        CONSTRAINT fk_invoices_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS platform_users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT "super_admin",
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_platform_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS audit_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_type VARCHAR(30) NOT NULL DEFAULT "system",
        actor_id INT UNSIGNED NULL,
        tenant_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        entity VARCHAR(60) NULL,
        entity_id INT UNSIGNED NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        metadata JSON NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_audit_events_tenant (tenant_id, created_at),
        KEY idx_audit_events_action (action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_login_attempts_lookup (email, ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
