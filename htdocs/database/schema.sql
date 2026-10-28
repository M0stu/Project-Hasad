-- Hasad 1.0 | MySQL 8 / MariaDB 10.6+ | InnoDB | utf8mb4
-- Import into the database created in InfinityFree's control panel. No CREATE DATABASE privileges needed.
SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(80) PRIMARY KEY, setting_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(160) NOT NULL, phone VARCHAR(20) NULL UNIQUE, email VARCHAR(190) NULL UNIQUE,
 password_hash VARCHAR(255) NULL, role ENUM('member','support','admin','super_admin') NOT NULL DEFAULT 'member',
 account_type ENUM('seller','buyer') NOT NULL DEFAULT 'seller', active TINYINT NOT NULL DEFAULT 1,
 verified_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS rate_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, bucket VARCHAR(64) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(bucket,created_at), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS otp_challenges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token CHAR(64) NOT NULL UNIQUE,
 phone VARCHAR(20) NOT NULL, code_hash VARCHAR(255) NULL, attempts INT NOT NULL DEFAULT 0,
 status ENUM('pending','used','expired') NOT NULL DEFAULT 'pending', expires_at DATETIME NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(phone,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS parties (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind ENUM('seller','buyer','transporter','vendor') NOT NULL,
 name VARCHAR(160) NOT NULL, phone VARCHAR(20) NOT NULL, organization VARCHAR(190) NOT NULL DEFAULT '',
 governorate VARCHAR(80) NOT NULL DEFAULT '', address VARCHAR(500) NOT NULL DEFAULT '',
 verified TINYINT NOT NULL DEFAULT 0, user_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id), INDEX(phone), INDEX(kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hubs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_ar VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
 governorate VARCHAR(80) NOT NULL, address VARCHAR(500) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS materials (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name_ar VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
 slug VARCHAR(80) NOT NULL UNIQUE, description_ar TEXT NOT NULL, description_en TEXT NOT NULL,
 icon VARCHAR(24) NOT NULL DEFAULT 'wheat', active TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS supply_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_code VARCHAR(30) NOT NULL UNIQUE,
 user_id BIGINT UNSIGNED NULL, contact_name VARCHAR(160) NOT NULL, contact_phone VARCHAR(20) NOT NULL,
 material_id BIGINT UNSIGNED NOT NULL, quantity_g BIGINT NOT NULL, governorate VARCHAR(80) NOT NULL,
 location VARCHAR(500) NOT NULL, handover ENUM('pickup','dropoff') NOT NULL, preferred_date DATE NULL,
 notes TEXT NOT NULL, contact_verified TINYINT NOT NULL DEFAULT 0,
 status ENUM('new','scheduled','inspected','rejected','cancelled') NOT NULL DEFAULT 'new',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(material_id) REFERENCES materials(id),
 INDEX(status,created_at), INDEX(contact_phone), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS batches (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE,
 request_id BIGINT UNSIGNED NULL UNIQUE, supplier_id BIGINT UNSIGNED NOT NULL,
 hub_id BIGINT UNSIGNED NOT NULL, material_id BIGINT UNSIGNED NOT NULL, grade ENUM('A','B','C') NOT NULL,
 quantity_g BIGINT NOT NULL, reserved_g BIGINT NOT NULL DEFAULT 0,
 price_per_tonne BIGINT NOT NULL, moisture_bp INT NOT NULL DEFAULT 0, inspection_notes TEXT NOT NULL,
 status ENUM('inspected','published','quarantined','depleted') NOT NULL DEFAULT 'inspected',
 inspected_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(request_id) REFERENCES supply_requests(id), FOREIGN KEY(supplier_id) REFERENCES parties(id),
 FOREIGN KEY(hub_id) REFERENCES hubs(id), FOREIGN KEY(material_id) REFERENCES materials(id),
 FOREIGN KEY(inspected_by) REFERENCES users(id), INDEX(status,material_id,hub_id),
 CONSTRAINT batch_quantities CHECK (quantity_g >= 0 AND reserved_g >= 0 AND reserved_g <= quantity_g)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(30) NOT NULL UNIQUE,
 user_id BIGINT UNSIGNED NOT NULL, buyer_id BIGINT UNSIGNED NOT NULL, batch_id BIGINT UNSIGNED NOT NULL,
 quantity_g BIGINT NOT NULL, price_per_tonne BIGINT NOT NULL, subtotal BIGINT NOT NULL,
 shipping BIGINT NOT NULL, fee_bp INT NOT NULL, fee_flat BIGINT NOT NULL, service_fee BIGINT NOT NULL, total BIGINT NOT NULL,
 shipping_mode ENUM('hasad','buyer') NOT NULL, destination VARCHAR(500) NOT NULL, governorate VARCHAR(80) NOT NULL,
 status ENUM('quoted','payment_review','paid','dispatching','delivered','cancelled','refund_pending','refunded') NOT NULL DEFAULT 'quoted',
 expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(buyer_id) REFERENCES parties(id),
 FOREIGN KEY(batch_id) REFERENCES batches(id), INDEX(status,expires_at), INDEX(user_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id BIGINT UNSIGNED NOT NULL,
 kind VARCHAR(40) NOT NULL, quantity_delta_g BIGINT NOT NULL DEFAULT 0, reserved_delta_g BIGINT NOT NULL DEFAULT 0,
 reference VARCHAR(100) NOT NULL, created_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(batch_id) REFERENCES batches(id), FOREIGN KEY(created_by) REFERENCES users(id), INDEX(batch_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 method ENUM('instapay','wallet') NOT NULL, reference VARCHAR(100) NOT NULL,
 amount BIGINT NOT NULL, proof_path VARCHAR(100) NULL, proof_mime VARCHAR(40) NULL,
 status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
 bank_reference VARCHAR(100) NULL UNIQUE, reviewed_by BIGINT UNSIGNED NULL, review_note VARCHAR(500) NOT NULL DEFAULT '',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(reviewed_by) REFERENCES users(id),
 UNIQUE KEY payment_reference(method,reference), INDEX(status,created_at), INDEX(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS accounts (
 id INT UNSIGNED PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE,
 name_ar VARCHAR(160) NOT NULL, name_en VARCHAR(160) NOT NULL,
 type ENUM('asset','liability','equity','income','expense') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS journals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, source VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
 event VARCHAR(40) NOT NULL, description VARCHAR(500) NOT NULL, created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY journal_idempotency(source,source_id,event),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS journal_lines (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, journal_id BIGINT UNSIGNED NOT NULL,
 account_id INT UNSIGNED NOT NULL, debit BIGINT NOT NULL DEFAULT 0, credit BIGINT NOT NULL DEFAULT 0,
 party_id BIGINT UNSIGNED NULL,
 FOREIGN KEY(journal_id) REFERENCES journals(id), FOREIGN KEY(account_id) REFERENCES accounts(id),
 FOREIGN KEY(party_id) REFERENCES parties(id), INDEX(account_id), INDEX(party_id),
 CONSTRAINT single_sided CHECK (debit >= 0 AND credit >= 0 AND ((debit = 0) <> (credit = 0)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS shipments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NULL, request_id BIGINT UNSIGNED NULL,
 driver_name VARCHAR(160) NOT NULL, driver_phone VARCHAR(20) NOT NULL, vehicle VARCHAR(100) NOT NULL,
 scheduled_at DATETIME NOT NULL, status ENUM('planned','in_transit','completed','cancelled') NOT NULL DEFAULT 'planned',
 notes VARCHAR(500) NOT NULL DEFAULT '', created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(request_id) REFERENCES supply_requests(id),
 FOREIGN KEY(created_by) REFERENCES users(id), INDEX(status,scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS settlements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id BIGINT UNSIGNED NOT NULL,
 type ENUM('seller','shipping','refund') NOT NULL, amount BIGINT NOT NULL,
 method ENUM('instapay','wallet') NOT NULL, reference VARCHAR(100) NOT NULL UNIQUE,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(order_id) REFERENCES orders(id), FOREIGN KEY(created_by) REFERENCES users(id), INDEX(order_id,type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS procurement (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, supplier_id BIGINT UNSIGNED NOT NULL,
 description VARCHAR(500) NOT NULL, amount BIGINT NOT NULL, due_date DATE NOT NULL,
 status ENUM('draft','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
 payment_method ENUM('instapay','wallet') NULL, payment_reference VARCHAR(100) NULL UNIQUE,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(supplier_id) REFERENCES parties(id), FOREIGN KEY(created_by) REFERENCES users(id), INDEX(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS projects (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, budget BIGINT NOT NULL DEFAULT 0,
 status ENUM('planned','active','completed','on_hold') NOT NULL DEFAULT 'planned',
 due_date DATE NULL, notes VARCHAR(500) NOT NULL DEFAULT '', created_by BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS expenses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id BIGINT UNSIGNED NULL,
 description VARCHAR(500) NOT NULL, amount BIGINT NOT NULL,
 method ENUM('instapay','wallet') NOT NULL, reference VARCHAR(100) NOT NULL UNIQUE,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(project_id) REFERENCES projects(id), FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tasks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, project_id BIGINT UNSIGNED NULL, title VARCHAR(190) NOT NULL,
 assignee_id BIGINT UNSIGNED NULL, due_date DATE NULL,
 status ENUM('todo','doing','done') NOT NULL DEFAULT 'todo', priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(project_id) REFERENCES projects(id), FOREIGN KEY(assignee_id) REFERENCES users(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS attendance (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 work_date DATE NOT NULL, minutes INT NOT NULL, notes VARCHAR(500) NOT NULL DEFAULT '',
 UNIQUE KEY daily_attendance(user_id,work_date), FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS production_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, input_batch_id BIGINT UNSIGNED NOT NULL,
 output_batch_id BIGINT UNSIGNED NOT NULL, partner VARCHAR(190) NOT NULL, authorization VARCHAR(190) NOT NULL,
 input_g BIGINT NOT NULL, output_g BIGINT NOT NULL, loss_g BIGINT NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(input_batch_id) REFERENCES batches(id), FOREIGN KEY(output_batch_id) REFERENCES batches(id),
 FOREIGN KEY(created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS tickets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
 order_id BIGINT UNSIGNED NULL, contact_phone VARCHAR(20) NOT NULL, subject VARCHAR(190) NOT NULL,
 message TEXT NOT NULL, response TEXT NOT NULL, status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(order_id) REFERENCES orders(id), INDEX(status), INDEX(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL, entity VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
 details TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(user_id) REFERENCES users(id), INDEX(entity,entity_id), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO settings VALUES
 ('service_fee_bp','0'),('service_fee_flat','0'),('pickup_min_kg','1000'),
 ('shipping_local_per_tonne','35000'),('shipping_other_per_tonne','65000'),('shipping_minimum','50000'),
 ('order_hold_hours','48'),('instapay_url',''),('instapay_account',''),('wallet_number',''),
 ('contact_phone',''),('contact_name','Hasad'),('pilot_governorate','Sharqia'),
 ('launch_open','0'),('legal_name',''),('legal_address',''),('demo_data','0');
INSERT IGNORE INTO hubs (id,name_ar,name_en,governorate,address) VALUES
 (1,'مركز تجميع الشرقية — موقع تجريبي','Sharkia collection hub — pilot location','Sharqia','');
INSERT IGNORE INTO materials (id,name_ar,name_en,slug,description_ar,description_en,icon) VALUES
 (1,'قش الأرز','Rice straw','rice-straw','للتدوير والفرشة والتصنيع وفق متطلبات المشتري.','For recycling, bedding and manufacturing, subject to buyer requirements.','wheat'),
 (2,'تبن القمح','Wheat straw','wheat-straw','مخلفات حصاد القمح. الاستخدام النهائي حسب الفحص وموافقة المشتري.','Wheat harvest residues. End use depends on inspection and buyer approval.','wheat'),
 (3,'حطب الذرة','Corn stalks','corn-stalks','سيقان الذرة للتدوير والتصنيع.','Corn stalk residues for recycling and manufacturing.','sprout'),
 (4,'مخلفات النخيل','Palm residues','palm-residues','جريد ومخلفات تقليم النخيل.','Palm fronds and pruning residues.','leaf'),
 (5,'مصاصة القصب','Sugarcane bagasse','sugarcane-bagasse','ألياف القصب للتصنيع والتدوير.','Sugarcane fibers for manufacturing and recycling.','layers');
INSERT IGNORE INTO accounts VALUES
 (1010,'1010','البنك والتحويلات الإلكترونية','Bank and online transfers','asset'),
 (2010,'2010','مبالغ العملاء قيد التنفيذ','Buyer funds pending fulfillment','liability'),
 (2020,'2020','مستحقات البائعين','Seller payables','liability'),
 (2030,'2030','مستحقات النقل','Transport payables','liability'),
 (2040,'2040','مستحقات مشتريات التشغيل','Operating vendor payables','liability'),
 (3010,'3010','رأس المال','Capital','equity'),
 (4010,'4010','إيراد رسوم الخدمة','Service fee revenue','income'),
 (5010,'5010','مصروفات التشغيل','Operating expenses','expense');
CREATE TABLE IF NOT EXISTS transfer_register (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 reference VARCHAR(100) NOT NULL UNIQUE, method ENUM('instapay','wallet') NOT NULL,
 direction ENUM('incoming','outgoing') NOT NULL, amount BIGINT NOT NULL,
 source VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
 created_by BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id), INDEX(source,source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
