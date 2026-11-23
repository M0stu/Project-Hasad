-- HASSAD buyer-cycle reliability release: apply once after backing up database and receipt storage.
START TRANSACTION;
CREATE TABLE IF NOT EXISTS schema_migrations (
 version VARCHAR(80) PRIMARY KEY,
 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE orders
 ADD COLUMN instapay_account_snapshot VARCHAR(190) NULL AFTER governorate,
 ADD COLUMN wallet_number_snapshot VARCHAR(30) NULL AFTER instapay_account_snapshot;
INSERT INTO schema_migrations(version) VALUES('20261123_002_order_payment_snapshot');
COMMIT;
