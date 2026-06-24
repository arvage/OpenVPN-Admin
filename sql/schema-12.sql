-- Migration 12: per-type toggles for in-app admin notifications
ALTER TABLE `smtp_settings` ADD COLUMN `notify_admin_user_add`    TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE `smtp_settings` ADD COLUMN `notify_admin_user_edit`   TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `smtp_settings` ADD COLUMN `notify_admin_user_delete` TINYINT(1) NOT NULL DEFAULT 1;
