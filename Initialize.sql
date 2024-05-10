CREATE TABLE `peers` (
  `info_hash` CHAR(40) NOT NULL,
  `peer_id` CHAR(40) NOT NULL,
  `ipv4` VARCHAR(15),
  `port` INT NOT NULL,
  `ipv6` VARCHAR(45),
  `downloaded` BIGINT UNSIGNED DEFAULT 0,
  `left` BIGINT UNSIGNED DEFAULT 0,
  `uploaded` BIGINT UNSIGNED DEFAULT 0,
  `supportcrypto` TINYINT DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`info_hash`, `peer_id`),
  INDEX `idx_ipv4` (`ipv4`),
  INDEX `idx_ipv6` (`ipv6`)
);