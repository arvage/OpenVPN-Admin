-- Migration 10: admin roles, SMTP/notification settings
-- Add missing columns to admin table (not in schema-0)
ALTER TABLE `admin` ADD COLUMN `admin_mail` varchar(64) DEFAULT NULL;
ALTER TABLE `admin` ADD COLUMN `admin_phone` varchar(16) DEFAULT NULL;
ALTER TABLE `admin` ADD COLUMN `admin_enable` tinyint(1) NOT NULL DEFAULT 1;
ALTER TABLE `admin` ADD COLUMN `admin_role` enum('super-admin','read-only') NOT NULL DEFAULT 'super-admin';

-- SMTP configuration and notification toggles
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` INT NOT NULL DEFAULT 1,
  `smtp_host` varchar(255) NOT NULL DEFAULT '',
  `smtp_port` INT NOT NULL DEFAULT 587,
  `smtp_user` varchar(255) NOT NULL DEFAULT '',
  `smtp_pass` varchar(255) NOT NULL DEFAULT '',
  `smtp_from` varchar(255) NOT NULL DEFAULT '',
  `smtp_from_name` varchar(255) NOT NULL DEFAULT 'OpenVPN Admin',
  `smtp_secure` varchar(10) NOT NULL DEFAULT 'tls',
  `notify_connect` tinyint(1) NOT NULL DEFAULT 0,
  `notify_disconnect` tinyint(1) NOT NULL DEFAULT 0,
  `notify_expiry` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `smtp_settings` (`id`) VALUES (1);
