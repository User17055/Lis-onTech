-- Lis'onTech - WhatsApp chat tables
-- Run this file in the same MySQL database used by the site.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS chat_threads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phone VARCHAR(32) NOT NULL,
  display_name VARCHAR(180) NULL,
  last_message_preview VARCHAR(255) NULL,
  last_message_at DATETIME NULL,
  last_inbound_at DATETIME NULL,
  last_outbound_at DATETIME NULL,
  unread_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_threads_phone (phone),
  KEY idx_chat_threads_last_message (last_message_at),
  KEY idx_chat_threads_unread (unread_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  thread_id BIGINT UNSIGNED NOT NULL,
  phone VARCHAR(32) NOT NULL,
  direction VARCHAR(12) NOT NULL,
  message_type VARCHAR(40) NOT NULL DEFAULT 'text',
  body TEXT NULL,
  meta_message_id VARCHAR(191) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'accepted',
  status_at DATETIME NULL,
  sent_at DATETIME NULL,
  delivered_at DATETIME NULL,
  read_at DATETIME NULL,
  failed_at DATETIME NULL,
  error_text TEXT NULL,
  http_code INT NULL,
  source VARCHAR(60) NULL,
  source_ref VARCHAR(191) NULL,
  request_json MEDIUMTEXT NULL,
  response_json MEDIUMTEXT NULL,
  payload_json MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_chat_messages_meta_id (meta_message_id),
  KEY idx_chat_messages_thread_created (thread_id, created_at),
  KEY idx_chat_messages_phone_created (phone, created_at),
  KEY idx_chat_messages_status (direction, status),
  KEY idx_chat_messages_source_ref (source_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS lison_add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE lison_add_column_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD COLUMN `', p_column_name, '` ', p_column_definition);
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL lison_add_column_if_missing('chat_threads', 'display_name', 'VARCHAR(180) NULL');
CALL lison_add_column_if_missing('chat_threads', 'last_message_preview', 'VARCHAR(255) NULL');
CALL lison_add_column_if_missing('chat_threads', 'last_message_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_threads', 'last_inbound_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_threads', 'last_outbound_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_threads', 'unread_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL lison_add_column_if_missing('chat_threads', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL lison_add_column_if_missing('chat_threads', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CALL lison_add_column_if_missing('chat_messages', 'thread_id', 'BIGINT UNSIGNED NOT NULL DEFAULT 0');
CALL lison_add_column_if_missing('chat_messages', 'phone', 'VARCHAR(32) NOT NULL DEFAULT ''''');
CALL lison_add_column_if_missing('chat_messages', 'direction', 'VARCHAR(12) NOT NULL DEFAULT ''out''');
CALL lison_add_column_if_missing('chat_messages', 'message_type', 'VARCHAR(40) NOT NULL DEFAULT ''text''');
CALL lison_add_column_if_missing('chat_messages', 'body', 'TEXT NULL');
CALL lison_add_column_if_missing('chat_messages', 'meta_message_id', 'VARCHAR(191) NULL');
CALL lison_add_column_if_missing('chat_messages', 'status', 'VARCHAR(30) NOT NULL DEFAULT ''accepted''');
CALL lison_add_column_if_missing('chat_messages', 'status_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_messages', 'sent_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_messages', 'delivered_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_messages', 'read_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_messages', 'failed_at', 'DATETIME NULL');
CALL lison_add_column_if_missing('chat_messages', 'error_text', 'TEXT NULL');
CALL lison_add_column_if_missing('chat_messages', 'http_code', 'INT NULL');
CALL lison_add_column_if_missing('chat_messages', 'source', 'VARCHAR(60) NULL');
CALL lison_add_column_if_missing('chat_messages', 'source_ref', 'VARCHAR(191) NULL');
CALL lison_add_column_if_missing('chat_messages', 'request_json', 'MEDIUMTEXT NULL');
CALL lison_add_column_if_missing('chat_messages', 'response_json', 'MEDIUMTEXT NULL');
CALL lison_add_column_if_missing('chat_messages', 'payload_json', 'MEDIUMTEXT NULL');
CALL lison_add_column_if_missing('chat_messages', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL lison_add_column_if_missing('chat_messages', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

DROP PROCEDURE IF EXISTS lison_add_column_if_missing;

DROP PROCEDURE IF EXISTS lison_add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE lison_add_index_if_missing(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_alter_sql TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @sql = p_alter_sql;
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

CALL lison_add_index_if_missing(
  'chat_threads',
  'uq_chat_threads_phone',
  'ALTER TABLE chat_threads ADD UNIQUE KEY uq_chat_threads_phone (phone)'
);
CALL lison_add_index_if_missing(
  'chat_threads',
  'idx_chat_threads_last_message',
  'ALTER TABLE chat_threads ADD INDEX idx_chat_threads_last_message (last_message_at)'
);
CALL lison_add_index_if_missing(
  'chat_threads',
  'idx_chat_threads_unread',
  'ALTER TABLE chat_threads ADD INDEX idx_chat_threads_unread (unread_count)'
);
CALL lison_add_index_if_missing(
  'chat_messages',
  'uq_chat_messages_meta_id',
  'ALTER TABLE chat_messages ADD UNIQUE KEY uq_chat_messages_meta_id (meta_message_id)'
);
CALL lison_add_index_if_missing(
  'chat_messages',
  'idx_chat_messages_thread_created',
  'ALTER TABLE chat_messages ADD INDEX idx_chat_messages_thread_created (thread_id, created_at)'
);
CALL lison_add_index_if_missing(
  'chat_messages',
  'idx_chat_messages_phone_created',
  'ALTER TABLE chat_messages ADD INDEX idx_chat_messages_phone_created (phone, created_at)'
);
CALL lison_add_index_if_missing(
  'chat_messages',
  'idx_chat_messages_status',
  'ALTER TABLE chat_messages ADD INDEX idx_chat_messages_status (direction, status)'
);
CALL lison_add_index_if_missing(
  'chat_messages',
  'idx_chat_messages_source_ref',
  'ALTER TABLE chat_messages ADD INDEX idx_chat_messages_source_ref (source_ref)'
);

DROP PROCEDURE IF EXISTS lison_add_index_if_missing;
