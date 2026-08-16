-- Upgrade modWeCom 0.1.0 -> 0.2.0 (idempotent: safe to run twice)
-- 1) corp_id needs a default value (token cache insert no longer provides it on every path)
ALTER TABLE llx_wecom_config MODIFY corp_id VARCHAR(64) NOT NULL DEFAULT '';

-- 2) customer tags captured from follow_user[].tags
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'llx_wecom_contact_map' AND COLUMN_NAME = 'wecom_tags');
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE llx_wecom_contact_map ADD COLUMN wecom_tags VARCHAR(512) NULL AFTER wecom_corp_name',
  'SELECT ''wecom_tags already exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) drop unused credential columns (secrets live encrypted in llx_const)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'llx_wecom_config' AND COLUMN_NAME = 'secret_encrypted');
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE llx_wecom_config DROP COLUMN secret_encrypted, DROP COLUMN token_encrypted, DROP COLUMN encodingaeskey_encrypted',
  'SELECT ''credential columns already dropped''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
