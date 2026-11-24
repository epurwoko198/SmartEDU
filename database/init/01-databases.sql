-- EDUSMART ERP database initialization (run automatically by the mysql container).
-- Creates the core SaaS database plus separate provisioning and runtime accounts.

CREATE DATABASE IF NOT EXISTS `edusmart_core`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Runtime account: minimum privileges on the core DB and all tenant databases.
CREATE USER IF NOT EXISTS 'edusmart_app'@'%' IDENTIFIED BY 'edusmart_app_pass';
GRANT SELECT, INSERT, UPDATE, DELETE ON `edusmart_core`.* TO 'edusmart_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `edusmart\_t\_%`.* TO 'edusmart_app'@'%';

-- Provisioning account: creates tenant databases and applies migrations.
CREATE USER IF NOT EXISTS 'edusmart_provisioner'@'%' IDENTIFIED BY 'edusmart_provisioner_pass';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON `edusmart_core`.* TO 'edusmart_provisioner'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES
    ON `edusmart\_t\_%`.* TO 'edusmart_provisioner'@'%';
GRANT CREATE ON *.* TO 'edusmart_provisioner'@'%';

FLUSH PRIVILEGES;
