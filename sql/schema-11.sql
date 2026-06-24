-- Migration 11: in-app notifications for user CRUD events
CREATE TABLE IF NOT EXISTS `notification` (
  `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
  `notification_type` VARCHAR(32) NOT NULL,
  `notification_user_id` VARCHAR(255) NOT NULL,
  `notification_detail` VARCHAR(255) DEFAULT NULL,
  `notification_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notification_read_by` TEXT DEFAULT NULL,
  PRIMARY KEY (`notification_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
