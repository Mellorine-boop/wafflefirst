USE waffle_sip_pos;

DROP TABLE IF EXISTS product_ingredients;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS ingredients;
DROP TABLE IF EXISTS suppliers;

-- Add discount_type only when the column is not already present.
SET @has_discount_type = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='discount_type');
-- Execute the discount_type addition only when it is missing.
SET @sql = IF(@has_discount_type=0, "ALTER TABLE sales ADD COLUMN discount_type ENUM('None','Senior/PWD','Membership') NOT NULL DEFAULT 'None' AFTER cashier_id", 'SELECT 1');
-- Run the generated statement safely.
PREPARE stmt FROM @sql;
-- Execute the generated statement.
EXECUTE stmt;
-- Release the prepared statement.
DEALLOCATE PREPARE stmt;

-- Add discount_amount only when the column is not already present.
SET @has_discount_amount = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='discount_amount');
-- Execute the discount_amount addition only when it is missing.
SET @sql = IF(@has_discount_amount=0, "ALTER TABLE sales ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type", 'SELECT 1');
-- Run the generated statement safely.
PREPARE stmt FROM @sql;
-- Execute the generated statement.
EXECUTE stmt;
-- Release the prepared statement.
DEALLOCATE PREPARE stmt;

-- Add discount_reference only when the column is not already present.
SET @has_discount_reference = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sales' AND column_name='discount_reference');
-- Execute the discount_reference addition only when it is missing.
SET @sql = IF(@has_discount_reference=0, "ALTER TABLE sales ADD COLUMN discount_reference VARCHAR(100) NULL AFTER discount_amount", 'SELECT 1');
-- Run the generated statement safely.
PREPARE stmt FROM @sql;
-- Execute the generated statement.
EXECUTE stmt;
-- Release the prepared statement.
DEALLOCATE PREPARE stmt;

.
INSERT INTO users(username,password_hash,full_name,role)
SELECT 'cashier3','$2y$10$zYEjOPJ0BMSwc4gnhoV8Du9ETlrPDFodAQyx8cOhYA9TekPWBgh26','numbatri','cashier'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='cashier3');

-- Update the three cashier display names without changing their usernames, passwords, or roles.
UPDATE users SET full_name='numbawan' WHERE username='cashier1' AND role='cashier';
-- Update Cashier 2's display name.
UPDATE users SET full_name='numbatu' WHERE username='cashier2' AND role='cashier';
-- Update Cashier 3's display name.
UPDATE users SET full_name='numbetri' WHERE username='cashier3' AND role='cashier';


-- Create the live master audit log when it does not yet exist.
CREATE TABLE IF NOT EXISTS sale_audit_log (
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

-- Backfill the original completed event for every existing sale when it has not already been recorded.
INSERT INTO sale_audit_log(sale_id,event_status,event_time)
SELECT s.sale_id,'Completed',s.sale_date
FROM sales s
WHERE NOT EXISTS (
    SELECT 1 FROM sale_audit_log a
    WHERE a.sale_id=s.sale_id AND a.event_status='Completed'
);

-- Backfill a void event for every already-voided sale when it has not already been recorded.
INSERT INTO sale_audit_log(sale_id,event_status,event_time,voided_at,void_reason)
SELECT s.sale_id,'Voided',COALESCE(s.voided_at,s.sale_date),s.voided_at,s.void_reason
FROM sales s
WHERE s.status='Voided'
  AND NOT EXISTS (
    SELECT 1 FROM sale_audit_log a
    WHERE a.sale_id=s.sale_id AND a.event_status='Voided'
  );
