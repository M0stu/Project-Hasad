-- HASSAD defense-demo release: apply once after backing up the database.
START TRANSACTION;
CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE batches ADD COLUMN subcategory ENUM('baled','loose','chopped','processed') NOT NULL DEFAULT 'loose' AFTER grade, ADD COLUMN listing_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER status, ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;
UPDATE materials SET active=0 WHERE slug='palm-residues';
UPDATE materials SET slug='corn-residues',name_ar='مخلفات الذرة',name_en='Corn residues',description_ar='مخلفات محصول الذرة للتدوير والتصنيع.',description_en='Corn crop residues for recycling and manufacturing.' WHERE slug='corn-stalks';
UPDATE materials SET name_ar='مصاصة قصب السكر' WHERE slug='sugarcane-bagasse';
INSERT INTO settings(setting_key,setting_value) VALUES('demo_seed_version','0') ON DUPLICATE KEY UPDATE setting_value=setting_value;
INSERT INTO schema_migrations(version) VALUES('20261119_001_defense_demo');
COMMIT;
