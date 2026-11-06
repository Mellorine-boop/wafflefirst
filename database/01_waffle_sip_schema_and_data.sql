DROP DATABASE IF EXISTS waffle_sip_pos;
CREATE DATABASE waffle_sip_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE waffle_sip_pos;

-- STAFF ACCOUNTS: one user can handle many sales.
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin','owner','cashier') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- PRODUCTS: menu items managed by admin and sold by cashier.
CREATE TABLE products (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    category ENUM('Waffle','Tea','Coffee Blend','Other Beverage') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description VARCHAR(100) NOT NULL DEFAULT '',
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_product_price CHECK (price > 0)
) ENGINE=InnoDB;

-- SALES: one cashier can process many sales.
CREATE TABLE sales (
    sale_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('Completed','Voided') NOT NULL DEFAULT 'Completed',
    cashier_id INT NOT NULL,
    discount_type ENUM('None','Senior/PWD','Membership') NOT NULL DEFAULT 'None',
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_reference VARCHAR(100) NULL,
    voided_by INT NULL,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    FOREIGN KEY(cashier_id) REFERENCES users(user_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY(voided_by) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT chk_sale_total CHECK(total_amount >= 0),
    CONSTRAINT chk_sale_discount CHECK(discount_amount >= 0),
    INDEX idx_sale_date(sale_date),
    INDEX idx_sale_status(status)
) ENGINE=InnoDB;

-- M:N SALE PRODUCT relationship.
CREATE TABLE sale_items (
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY(sale_id, product_id),
    FOREIGN KEY(sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY(product_id) REFERENCES products(product_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_sale_item_qty CHECK(quantity > 0),
    CONSTRAINT chk_sale_item_price CHECK(unit_price >= 0)
) ENGINE=InnoDB;

-- PAYMENT: exactly one payment record is allowed for one sale.
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL UNIQUE,
    method ENUM('Cash','Online Payment/Bank transfer','Card') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reference VARCHAR(50) NOT NULL UNIQUE,
    paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_payment_amount CHECK(amount >= 0)
) ENGINE=InnoDB;

-- LIVE MASTER AUDIT LOG: one sale can have multiple audit events, such as Completed followed later by Voided.
CREATE TABLE sale_audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    event_status ENUM('Completed','Voided') NOT NULL,
    event_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    voided_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    FOREIGN KEY(sale_id) REFERENCES sales(sale_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_audit_sale(sale_id),
    INDEX idx_audit_event_time(event_time),
    INDEX idx_audit_status(event_status)
) ENGINE=InnoDB;

INSERT INTO users(username,password_hash,full_name,role) VALUES
('admin','$2y$10$UDyV62lv37D85FEsmwhiDumV6O4T0.E3oAZCJH/xCxaKCj6ROhPXS','System Administrator','admin'),
('owner','$2y$10$doYfh4HUKEFa6HA.NROh4OWUpxll5wpL/zFCoWqoBRHKJ8IimoBX6','Shop Owner','owner'),
('cashier1','$2y$10$zYEjOPJ0BMSwc4gnhoV8Du9ETlrPDFodAQyx8cOhYA9TekPWBgh26','numbawan','cashier'),
('cashier2','$2y$10$zYEjOPJ0BMSwc4gnhoV8Du9ETlrPDFodAQyx8cOhYA9TekPWBgh26','numbatu','cashier'),
('cashier3','$2y$10$zYEjOPJ0BMSwc4gnhoV8Du9ETlrPDFodAQyx8cOhYA9TekPWBgh26','numbetri','cashier');

INSERT INTO products(name,category,price,description) VALUES
('Plain','Waffle',65.00,'Classic waffle'),
('Yema','Waffle',75.00,'Sweet yema'),
('Peanut Butter','Waffle',80.00,'Nutty spread'),
('Caramel','Waffle',80.00,'Caramel drizzle'),
('Ube','Waffle',85.00,'Ube cream'),
('Choco','Waffle',80.00,'Chocolate drizzle'),
('White Choco','Waffle',85.00,'White chocolate'),
('Cheesy','Waffle',90.00,'Melted cheese'),
('Cheesy Ham','Waffle',105.00,'Cheese and ham'),
('Creamy Egg','Waffle',95.00,'Creamy egg'),
('Cream Cheese Dream','Waffle',110.00,'Cream cheese'),
('Cucumber Juice','Other Beverage',70.00,'Fresh cucumber'),
('Hot Tea','Tea',65.00,'Warm brewed tea'),
('Hot Tea with Milk','Tea',75.00,'Tea with milk'),
('Cold Tea','Tea',70.00,'Chilled brewed tea'),
('ChocoBrew','Coffee Blend',95.00,'Chocolate coffee'),
('Sea Salt Caramel Latte','Coffee Blend',110.00,'Sea salt caramel'),
('Velvet Vanilla Caramel','Coffee Blend',115.00,'Vanilla caramel'),
('French Vanilla Latte','Coffee Blend',105.00,'French vanilla');
