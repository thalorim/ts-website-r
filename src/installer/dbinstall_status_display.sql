-- Database table for TeamSpeak channel status display feature
-- This table stores configurations mapping cldbid to channel IDs for status display

DROP TABLE IF EXISTS `DBPREFIXchannel_status_display`;
CREATE TABLE `DBPREFIXchannel_status_display` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cldbid` INT(11) NOT NULL COMMENT 'Client database ID to monitor',
  `channel_id` INT(11) NOT NULL COMMENT 'Channel ID to update description',
  `server_group_id` INT(11) DEFAULT NULL COMMENT 'Server group ID to display icon for',
  `enabled` TINYINT(1) NOT NULL DEFAULT '1' COMMENT 'Whether this configuration is active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cldbid_channel` (`cldbid`, `channel_id`),
  KEY `idx_cldbid` (`cldbid`),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example configuration (commented out - add via admin interface)
-- INSERT INTO `DBPREFIXchannel_status_display` (`cldbid`, `channel_id`, `server_group_id`, `enabled`) VALUES
-- (116527, 251746, 9, 1);
