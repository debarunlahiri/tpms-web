-- TPMS Database Schema
-- Database: tpms

CREATE DATABASE IF NOT EXISTS tpms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tpms;

-- Roles table (customizable permissions)
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    permissions JSON NOT NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'sales_rep',
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role) REFERENCES roles(name) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_remember_user (user_id),
    INDEX idx_remember_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(24) NOT NULL UNIQUE,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(30) DEFAULT 'info',
    url VARCHAR(500) DEFAULT NULL,
    read_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notification_user_read (user_id, read_at),
    INDEX idx_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contacts / Customers table
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    company VARCHAR(150) DEFAULT NULL,
    job_title VARCHAR(100) DEFAULT NULL,
    industry VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    source ENUM('website','referral','social','email','call','other') DEFAULT 'other',
    status ENUM('active','inactive','prospect') DEFAULT 'prospect',
    assigned_to INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leads table
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contact_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('new','contacted','qualified','proposal','negotiation','won','lost') DEFAULT 'new',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    source ENUM('website','referral','social','email','call','other') DEFAULT 'other',
    estimated_value DECIMAL(15,2) DEFAULT 0.00,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deals / Opportunities table
CREATE TABLE IF NOT EXISTS deals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    contact_id INT DEFAULT NULL,
    lead_id INT DEFAULT NULL,
    stage ENUM('prospecting','qualification','proposal','negotiation','closed_won','closed_lost') DEFAULT 'prospecting',
    value DECIMAL(15,2) DEFAULT 0.00,
    probability INT DEFAULT 0,
    expected_close_date DATE DEFAULT NULL,
    actual_close_date DATE DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Projects table
CREATE TABLE IF NOT EXISTS projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    status ENUM('planning','in_progress','on_hold','completed','cancelled') DEFAULT 'planning',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    budget DECIMAL(15,2) DEFAULT 0.00,
    contact_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tasks / Activities table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    type ENUM('call','email','meeting','follow_up','demo','other') DEFAULT 'other',
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    priority ENUM('low','medium','high') DEFAULT 'medium',
    due_date DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    related_to ENUM('contact','lead','deal') DEFAULT NULL,
    related_id INT DEFAULT NULL,
    assigned_to INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Media / File uploads table
CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size BIGINT DEFAULT 0,
    mime_type VARCHAR(100) DEFAULT NULL,
    related_to ENUM('contact','lead','deal','task','project','user','general') DEFAULT 'general',
    related_id INT DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log / Notes
CREATE TABLE IF NOT EXISTS activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    related_to ENUM('contact','lead','deal','task','project','user','role','media','setting','login','logout') DEFAULT NULL,
    related_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoices table
CREATE TABLE IF NOT EXISTS invoices (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice items table
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(15,2) DEFAULT 0,
    amount DECIMAL(15,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles with permissions
INSERT INTO roles (name, display_name, description, permissions, is_system) VALUES
('admin', 'Administrator', 'Full system access', '["dashboard","leads","contacts","deals","tasks","projects","reports","settings","users","roles","media","storage","invoices","view_all","assign_records"]', 1),
('manager', 'Manager', 'Manage team and view reports', '["dashboard","leads","contacts","deals","tasks","projects","reports","users","media","invoices","view_all","assign_records"]', 1),
('sales_rep', 'Sales Representative', 'Manage own leads, contacts and deals', '["dashboard","leads","contacts","deals","tasks","projects","media"]', 1),
('employee', 'Employee', 'Internal team member access', '["dashboard","tasks","contacts","projects","media"]', 1),
('freelancer', 'Freelancer', 'External contractor access', '["dashboard","tasks","projects","media"]', 1),
('client', 'Client', 'Limited access to own records', '["dashboard","client_access"]', 1)
ON DUPLICATE KEY UPDATE id=id;

-- Insert default admin user (password: admin123)
INSERT INTO users (name, email, password, role) VALUES 
('System Admin', 'admin@crm.com', '$2y$10$fg1oZjCzNXWGAFK1DmHNp.bJ6/aA1kdMsXlvQkEyTEXLKPkZBsMSa', 'admin')
ON DUPLICATE KEY UPDATE id=id;

-- Insert sample settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'TPMS'),
('company_email', 'info@tpms.com'),
('company_address', ''),
('company_phone', ''),
('company_gstin', ''),
('currency', '₹'),
('currency_code', 'INR'),
('currency_conversion_rate', '1'),
('timezone', 'UTC'),
('date_format', 'Y-m-d'),
('invoice_prefix', 'INV-'),
('invoice_next_number', '1001'),
('invoice_tax_rate', '0'),
('invoice_gst_rate', '18'),
('invoice_terms', '')
ON DUPLICATE KEY UPDATE setting_key=setting_key;
