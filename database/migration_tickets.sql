-- Migration: Ticketing System (tbl_tickets)
-- Key Business Rule: 
-- Customer ticket: customer_id is required.
-- Maintenance ticket: customer_id must be NULL and not accepted.

CREATE TABLE IF NOT EXISTS `tbl_tickets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(32) NOT NULL,
  `type` ENUM('customer', 'maintenance') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `customer_id` INT NULL DEFAULT NULL,
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `created_by` INT UNSIGNED NOT NULL,
  `assigned_to` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `opened_at` DATETIME DEFAULT NULL,
  `closed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ticket_number` (`ticket_number`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_assigned_to` (`assigned_to`),
  CONSTRAINT `chk_ticket_customer` CHECK (
    (`type` = 'customer' AND `customer_id` IS NOT NULL) OR
    (`type` = 'maintenance' AND `customer_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
