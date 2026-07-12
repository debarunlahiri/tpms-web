<?php
/**
 * TPMS - Database Setup
 * Run this file once to initialize the database
 */

require_once 'config/database.php';

try {
    // Connect without database to create it
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute SQL file
    $sql = file_get_contents(__DIR__ . '/database/crm.sql');
    $pdo->exec($sql);
    
    // Run migrations for existing installations
    $pdo->exec("USE " . DB_NAME);
    
    // Create roles table if not exists (handled by schema, but ensure default roles)
    $rolesExist = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($rolesExist == 0) {
        $pdo->exec("INSERT INTO roles (name, display_name, description, permissions, is_system) VALUES
('admin', 'Administrator', 'Full system access', '[\"dashboard\",\"leads\",\"contacts\",\"deals\",\"tasks\",\"projects\",\"reports\",\"settings\",\"users\",\"roles\",\"media\",\"storage\",\"invoices\",\"view_all\",\"assign_records\"]', 1),
('manager', 'Manager', 'Manage team and view reports', '[\"dashboard\",\"leads\",\"contacts\",\"deals\",\"tasks\",\"projects\",\"reports\",\"users\",\"media\",\"invoices\",\"view_all\",\"assign_records\"]', 1),
            ('sales_rep', 'Sales Representative', 'Manage own leads, contacts and deals', '[\"dashboard\",\"leads\",\"contacts\",\"deals\",\"tasks\",\"projects\",\"media\"]', 1),
            ('employee', 'Employee', 'Internal team member access', '[\"dashboard\",\"tasks\",\"contacts\",\"projects\",\"media\"]', 1),
            ('freelancer', 'Freelancer', 'External contractor access', '[\"dashboard\",\"tasks\",\"projects\",\"media\"]', 1),
            ('client', 'Client', 'Limited access to own records', '[\"dashboard\",\"client_access\"]', 1)");
    }
    
    // Migrate users.role from ENUM to VARCHAR if needed
    try {
        $pdo->exec("ALTER TABLE users MODIFY role VARCHAR(50) DEFAULT 'sales_rep'");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role) REFERENCES roles(name) ON DELETE RESTRICT");
    } catch (Exception $e) {
        // Constraints may already exist
    }
    
    // Add user_id to contacts if not exists
    try {
        $pdo->exec("ALTER TABLE contacts ADD COLUMN user_id INT DEFAULT NULL AFTER assigned_to");
        $pdo->exec("ALTER TABLE contacts ADD CONSTRAINT fk_contacts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column may already exist
    }
    
    // Add project_id to tasks if not exists
    try {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN project_id INT DEFAULT NULL AFTER id");
        $pdo->exec("ALTER TABLE tasks ADD CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL");
    } catch (Exception $e) {
        // Column may already exist
    }
    
    // Add ip_address and user_agent to activities if not exists
    try {
        $pdo->exec("ALTER TABLE activities ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER related_id");
        $pdo->exec("ALTER TABLE activities ADD COLUMN user_agent TEXT DEFAULT NULL AFTER ip_address");
    } catch (Exception $e) {
        // Columns may already exist
    }
    
    // Ensure default currency is Rupee
    try {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('currency', '₹'), ('currency_code', 'INR'), ('currency_conversion_rate', '1') ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    } catch (Exception $e) {
        // Settings may already exist
    }
    
    // Migrate admin and manager roles to include invoices permission
    try {
        foreach (['admin', 'manager'] as $roleName) {
            $stmt = $pdo->prepare("SELECT permissions FROM roles WHERE name = ? AND is_system = 1");
            $stmt->execute([$roleName]);
            $rolePerms = $stmt->fetchColumn();
            if ($rolePerms) {
                $perms = json_decode($rolePerms, true) ?: [];
                $updated = false;
                foreach (['view_all', 'assign_records', 'invoices'] as $perm) {
                    if (!in_array($perm, $perms)) {
                        $perms[] = $perm;
                        $updated = true;
                    }
                }
                if ($updated) {
                    $newPerms = json_encode(array_values($perms));
                    $upd = $pdo->prepare("UPDATE roles SET permissions = ? WHERE name = ? AND is_system = 1");
                    $upd->execute([$newPerms, $roleName]);
                }
            }
        }
    } catch (Exception $e) {
        // Ignore migration errors
    }
    
    // Ensure invoice tables exist
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(100) NOT NULL UNIQUE,
            contact_id INT DEFAULT NULL,
            deal_id INT DEFAULT NULL,
            issue_date DATE NOT NULL,
            due_date DATE DEFAULT NULL,
            status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
            subtotal DECIMAL(15,2) DEFAULT 0,
            tax_rate DECIMAL(5,2) DEFAULT 0,
            tax_amount DECIMAL(15,2) DEFAULT 0,
            gst_rate DECIMAL(5,2) DEFAULT 0,
            gst_amount DECIMAL(15,2) DEFAULT 0,
            discount DECIMAL(15,2) DEFAULT 0,
            total DECIMAL(15,2) DEFAULT 0,
            notes TEXT DEFAULT NULL,
            terms TEXT DEFAULT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
            FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            description TEXT NOT NULL,
            quantity DECIMAL(10,2) DEFAULT 1,
            unit_price DECIMAL(15,2) DEFAULT 0,
            amount DECIMAL(15,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // Tables may already exist
    }
    
    // Ensure invoice settings exist
    try {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES
            ('company_address', ''),
            ('company_phone', ''),
            ('company_gstin', ''),
            ('invoice_prefix', 'INV-'),
            ('invoice_next_number', '1001'),
            ('invoice_tax_rate', '0'),
            ('invoice_gst_rate', '18'),
            ('invoice_terms', '')
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    } catch (Exception $e) {
        // Settings may already exist
    }
    
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 10px; background: #f0fdf4; border: 1px solid #86efac; color: #166534;'>
            <h1 style='margin-top: 0;'><i class='fas fa-check-circle'></i> Setup Complete</h1>
            <p>The database has been initialized successfully.</p>
            <p><strong>Default Login:</strong></p>
            <ul>
                <li>Email: <code>admin@crm.com</code></li>
                <li>Password: <code>admin123</code></li>
            </ul>
            <p><a href='index.php' style='display: inline-block; margin-top: 15px; padding: 10px 20px; background: #2563eb; color: white; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>
          </div>";
} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 30px; border-radius: 10px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;'>
            <h1 style='margin-top: 0;'>Setup Error</h1>
            <p>" . $e->getMessage() . "</p>
          </div>";
}
