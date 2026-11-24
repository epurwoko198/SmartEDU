<?php
declare(strict_types=1);

/**
 * Tenant (school) schema. Every school gets its own isolated database running
 * these migrations.
 *
 * @return list<string>
 */
return [
    'CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS roles (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(60) NOT NULL,
        name VARCHAR(100) NOT NULL,
        description VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_roles_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS permissions (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(80) NOT NULL,
        name VARCHAR(120) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_permissions_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT UNSIGNED NOT NULL,
        permission_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
        CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS user_roles (
        user_id INT UNSIGNED NOT NULL,
        role_id INT UNSIGNED NOT NULL,
        PRIMARY KEY (user_id, role_id),
        CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
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

    'CREATE TABLE IF NOT EXISTS school_profile (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(150) NOT NULL,
        npsn VARCHAR(20) NULL,
        level VARCHAR(30) NOT NULL DEFAULT "SMP",
        address TEXT NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(190) NULL,
        headmaster VARCHAR(120) NULL,
        logo_path VARCHAR(255) NULL,
        timezone VARCHAR(64) NOT NULL DEFAULT "Asia/Jakarta",
        currency VARCHAR(10) NOT NULL DEFAULT "IDR",
        letter_number_format VARCHAR(120) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS academic_years (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(60) NOT NULL,
        starts_on DATE NOT NULL,
        ends_on DATE NOT NULL,
        semester VARCHAR(20) NOT NULL DEFAULT "Ganjil",
        is_active TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_academic_years_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS classes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(60) NOT NULL,
        level VARCHAR(20) NOT NULL,
        academic_year_id INT UNSIGNED NULL,
        homeroom_user_id INT UNSIGNED NULL,
        capacity SMALLINT UNSIGNED NOT NULL DEFAULT 32,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_classes_name_year (name, academic_year_id),
        CONSTRAINT fk_classes_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
        CONSTRAINT fk_classes_homeroom FOREIGN KEY (homeroom_user_id) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS students (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nis VARCHAR(30) NULL,
        nisn VARCHAR(20) NULL,
        full_name VARCHAR(150) NOT NULL,
        gender VARCHAR(10) NOT NULL DEFAULT "L",
        birth_date DATE NULL,
        class_id INT UNSIGNED NULL,
        guardian_name VARCHAR(150) NULL,
        guardian_phone VARCHAR(40) NULL,
        status VARCHAR(20) NOT NULL DEFAULT "active",
        admission_year SMALLINT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_students_nis (nis),
        UNIQUE KEY uniq_students_nisn (nisn),
        KEY idx_students_class (class_id),
        KEY idx_students_status (status),
        CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS guardians (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id INT UNSIGNED NOT NULL,
        name VARCHAR(150) NOT NULL,
        relation VARCHAR(30) NOT NULL DEFAULT "wali",
        phone VARCHAR(40) NULL,
        email VARCHAR(190) NULL,
        occupation VARCHAR(80) NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_guardians_student (student_id),
        CONSTRAINT fk_guardians_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS employees (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        nip VARCHAR(30) NULL,
        full_name VARCHAR(150) NOT NULL,
        position VARCHAR(80) NOT NULL,
        employment_type VARCHAR(30) NOT NULL DEFAULT "tetap",
        email VARCHAR(190) NULL,
        phone VARCHAR(40) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_employees_nip (nip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS subjects (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        level VARCHAR(20) NULL,
        credits SMALLINT UNSIGNED NOT NULL DEFAULT 2,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_subjects_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS applicants (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        registration_no VARCHAR(40) NOT NULL,
        full_name VARCHAR(150) NOT NULL,
        gender VARCHAR(10) NOT NULL DEFAULT "L",
        birth_date DATE NULL,
        school_origin VARCHAR(150) NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(190) NULL,
        jalur VARCHAR(40) NOT NULL DEFAULT "reguler",
        wave VARCHAR(20) NOT NULL DEFAULT "gelombang-1",
        status VARCHAR(20) NOT NULL DEFAULT "new",
        score DECIMAL(6, 2) NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_applicants_registration (registration_no),
        KEY idx_applicants_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS fee_components (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(40) NOT NULL,
        name VARCHAR(100) NOT NULL,
        amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        period VARCHAR(20) NOT NULL DEFAULT "bulanan",
        is_recurring TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_fee_components_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS invoices (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id INT UNSIGNED NOT NULL,
        number VARCHAR(60) NOT NULL,
        amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        status VARCHAR(20) NOT NULL DEFAULT "unpaid",
        due_date DATE NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_invoices_number (number),
        KEY idx_invoices_student (student_id),
        KEY idx_invoices_status (status),
        CONSTRAINT fk_tenant_invoices_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id INT UNSIGNED NOT NULL,
        paid_at DATETIME NOT NULL,
        amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        method VARCHAR(30) NOT NULL DEFAULT "transfer",
        reference VARCHAR(120) NULL,
        received_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_payments_invoice (invoice_id),
        CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
        CONSTRAINT fk_payments_user FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS letters (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        type VARCHAR(20) NOT NULL DEFAULT "incoming",
        number VARCHAR(80) NOT NULL,
        subject VARCHAR(190) NOT NULL,
        sender VARCHAR(150) NULL,
        recipient VARCHAR(150) NULL,
        letter_date DATE NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT "draft",
        file_path VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_letters_type (type),
        KEY idx_letters_status (status),
        CONSTRAINT fk_letters_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',

    'CREATE TABLE IF NOT EXISTS audit_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        actor_type VARCHAR(30) NOT NULL DEFAULT "system",
        actor_id INT UNSIGNED NULL,
        action VARCHAR(100) NOT NULL,
        entity VARCHAR(60) NULL,
        entity_id INT UNSIGNED NULL,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        metadata JSON NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_tenant_audit_action (action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
];
