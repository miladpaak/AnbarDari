CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','manager','keeper','viewer') NOT NULL DEFAULT 'viewer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    symbol VARCHAR(30) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    location VARCHAR(255) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(60) NULL,
    address TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    phone VARCHAR(60) NULL,
    address TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(80) NOT NULL UNIQUE,
    barcode VARCHAR(120) NULL UNIQUE,
    name VARCHAR(180) NOT NULL,
    category_id INT UNSIGNED NULL,
    unit_id INT UNSIGNED NULL,
    min_stock DECIMAL(14,3) NOT NULL DEFAULT 0,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_items_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stocks (
    item_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id, warehouse_id),
    CONSTRAINT fk_stocks_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    CONSTRAINT fk_stocks_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_no VARCHAR(80) NOT NULL UNIQUE,
    type ENUM('in','out','transfer','adjustment') NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    from_warehouse_id INT UNSIGNED NULL,
    to_warehouse_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    notes TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_movements_type_date (type, created_at),
    INDEX idx_movements_item_date (item_id, created_at),
    CONSTRAINT fk_movements_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_movements_from FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    CONSTRAINT fk_movements_to FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    CONSTRAINT fk_movements_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_movements_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    item_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_action_date (action, created_at),
    INDEX idx_audit_item_date (item_id, created_at),
    INDEX idx_audit_user_date (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO units (name, symbol) VALUES ('عدد','عدد'), ('متر','m'), ('کیلوگرم','kg'), ('بسته','pkg');
INSERT IGNORE INTO categories (name, description) VALUES ('عمومی','دسته‌بندی پیش‌فرض');
INSERT IGNORE INTO warehouses (name, location) VALUES ('انبار اصلی','');
INSERT IGNORE INTO users (name, username, password_hash, role) VALUES
('مدیر سیستم', 'admin', '$2y$12$OBFIdPbrbQDo130KzODTHeE8fpjTcPUYmNt7sQx.sMEGXv9FGgCU2', 'admin');

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    bank_name VARCHAR(120) NULL,
    account_no VARCHAR(120) NULL,
    iban VARCHAR(120) NULL,
    opening_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    type ENUM('asset','liability','equity','income','expense') NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_no VARCHAR(80) NOT NULL UNIQUE,
    entry_date DATE NOT NULL,
    source_type VARCHAR(40) NOT NULL DEFAULT 'manual',
    source_id INT UNSIGNED NULL,
    description TEXT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_journal_date (entry_date),
    INDEX idx_journal_source (source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(16,2) NOT NULL DEFAULT 0,
    credit DECIMAL(16,2) NOT NULL DEFAULT 0,
    party_type ENUM('customer','supplier') NULL,
    party_id INT UNSIGNED NULL,
    bank_account_id INT UNSIGNED NULL,
    memo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_journal_lines_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_journal_lines_account FOREIGN KEY (account_id) REFERENCES accounting_accounts(id),
    CONSTRAINT fk_journal_lines_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    INDEX idx_journal_party (party_type, party_id),
    INDEX idx_journal_bank (bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounting_item_meta (
    item_id INT UNSIGNED PRIMARY KEY,
    last_purchase_price DECIMAL(16,2) NOT NULL DEFAULT 0,
    default_sale_price DECIMAL(16,2) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_accounting_item_meta_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(80) NOT NULL UNIQUE,
    invoice_type ENUM('sale','purchase','purchase_return') NOT NULL,
    invoice_date DATE NOT NULL,
    customer_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    bank_account_id INT UNSIGNED NULL,
    payment_status ENUM('cash','credit','partial') NOT NULL DEFAULT 'credit',
    subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
    discount DECIMAL(16,2) NOT NULL DEFAULT 0,
    tax DECIMAL(16,2) NOT NULL DEFAULT 0,
    shipping_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
    total DECIMAL(16,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    CONSTRAINT fk_invoices_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL,
    INDEX idx_invoice_type_date (invoice_type, invoice_date),
    INDEX idx_invoice_customer (customer_id),
    INDEX idx_invoice_supplier (supplier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(16,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(16,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(16,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_items_item FOREIGN KEY (item_id) REFERENCES items(id),
    INDEX idx_invoice_items_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO accounting_accounts (code, name, type, is_system, created_at) VALUES
('1000','صندوق/نقد','asset',1,NOW()),
('1010','بانک','asset',1,NOW()),
('1100','حساب‌های دریافتنی','asset',1,NOW()),
('1200','موجودی کالا','asset',1,NOW()),
('2000','حساب‌های پرداختنی','liability',1,NOW()),
('3000','سرمایه','equity',1,NOW()),
('4000','فروش','income',1,NOW()),
('4010','برگشت از فروش','income',1,NOW()),
('5000','بهای تمام‌شده کالای فروش‌رفته','expense',1,NOW()),
('5100','خرید کالا','expense',1,NOW()),
('5200','هزینه حمل','expense',1,NOW()),
('5300','سایر هزینه‌ها','expense',1,NOW());
