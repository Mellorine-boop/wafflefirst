
USE waffle_sip_pos;

-- Add the product description field if an older database does not have it.
SET @has_description := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='products' AND column_name='description');
SET @sql := IF(@has_description=0, 'ALTER TABLE products ADD COLUMN description VARCHAR(100) NOT NULL DEFAULT '' AFTER price', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Expand the product category values before adding the new menu.
ALTER TABLE products MODIFY category ENUM('Waffle','Tea','Coffee Blend','Other Beverage') NOT NULL;

-- Expand the payment enum before converting existing GCash records.
ALTER TABLE payments MODIFY method ENUM('Cash','GCash','Online Payment/Bank transfer','Card') NOT NULL;
UPDATE payments SET method='Online Payment/Bank transfer' WHERE method='GCash';
ALTER TABLE payments MODIFY method ENUM('Cash','Online Payment/Bank transfer','Card') NOT NULL;

-- Hide the old demo products from the cashier without deleting historical product rows.
UPDATE products SET is_available=0 WHERE name NOT IN (
    'Plain','Yema','Peanut Butter','Caramel','Ube','Choco','White Choco','Cheesy','Cheesy Ham','Creamy Egg','Cream Cheese Dream',
    'Cucumber Juice','Hot Tea','Hot Tea with Milk','Cold Tea','ChocoBrew','Sea Salt Caramel Latte','Velvet Vanilla Caramel','French Vanilla Latte'
);

-- Add the requested products only when they do not already exist.
INSERT INTO products(name,category,price,description,is_available) VALUES
('Plain','Waffle',65.00,'Classic waffle',1),
('Yema','Waffle',75.00,'Sweet yema',1),
('Peanut Butter','Waffle',80.00,'Nutty spread',1),
('Caramel','Waffle',80.00,'Caramel drizzle',1),
('Ube','Waffle',85.00,'Ube cream',1),
('Choco','Waffle',80.00,'Chocolate drizzle',1),
('White Choco','Waffle',85.00,'White chocolate',1),
('Cheesy','Waffle',90.00,'Melted cheese',1),
('Cheesy Ham','Waffle',105.00,'Cheese and ham',1),
('Creamy Egg','Waffle',95.00,'Creamy egg',1),
('Cream Cheese Dream','Waffle',110.00,'Cream cheese',1),
('Cucumber Juice','Other Beverage',70.00,'Fresh cucumber',1),
('Hot Tea','Tea',65.00,'Warm brewed tea',1),
('Hot Tea with Milk','Tea',75.00,'Tea with milk',1),
('Cold Tea','Tea',70.00,'Chilled brewed tea',1),
('ChocoBrew','Coffee Blend',95.00,'Chocolate coffee',1),
('Sea Salt Caramel Latte','Coffee Blend',110.00,'Sea salt caramel',1),
('Velvet Vanilla Caramel','Coffee Blend',115.00,'Vanilla caramel',1),
('French Vanilla Latte','Coffee Blend',105.00,'French vanilla',1)
ON DUPLICATE KEY UPDATE category=VALUES(category), price=VALUES(price), description=VALUES(description), is_available=1;
