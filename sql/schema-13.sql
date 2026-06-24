-- Migration 13: ban/unban notification toggles
ALTER TABLE `smtp_settings` ADD COLUMN `notify_admin_ban`   TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `smtp_settings` ADD COLUMN `notify_admin_unban` TINYINT(1) NOT NULL DEFAULT 1;
