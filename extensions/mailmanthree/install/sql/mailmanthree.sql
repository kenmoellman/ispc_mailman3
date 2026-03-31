-- ISPConfig3 Mailman3 Module - Database Schema
-- Creates the list cache table and config table

CREATE TABLE IF NOT EXISTS `mailmanthree_list` (
    `mailmanthree_list_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sys_userid` INT UNSIGNED NOT NULL DEFAULT 0,
    `sys_groupid` INT UNSIGNED NOT NULL DEFAULT 0,
    `sys_perm_user` VARCHAR(5) NOT NULL DEFAULT 'riud',
    `sys_perm_group` VARCHAR(5) NOT NULL DEFAULT 'riud',
    `sys_perm_other` VARCHAR(5) NOT NULL DEFAULT '',
    `server_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `list_id` VARCHAR(255) NOT NULL DEFAULT '',
    `fqdn_listname` VARCHAR(255) NOT NULL DEFAULT '',
    `list_name` VARCHAR(255) NOT NULL DEFAULT '',
    `domain` VARCHAR(255) NOT NULL DEFAULT '',
    `display_name` VARCHAR(255) NOT NULL DEFAULT '',
    `description` TEXT NOT NULL,
    `owner_email` VARCHAR(255) NOT NULL DEFAULT '',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `member_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_synced` DATETIME NULL,
    `sync_error` TEXT NULL,
    PRIMARY KEY (`mailmanthree_list_id`),
    UNIQUE KEY `list_id` (`list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `mailmanthree_config` (
    `mailmanthree_config_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sys_userid` INT UNSIGNED NOT NULL DEFAULT 0,
    `sys_groupid` INT UNSIGNED NOT NULL DEFAULT 0,
    `sys_perm_user` VARCHAR(5) NOT NULL DEFAULT 'riud',
    `sys_perm_group` VARCHAR(5) NOT NULL DEFAULT '',
    `sys_perm_other` VARCHAR(5) NOT NULL DEFAULT '',
    `server_id` INT UNSIGNED NOT NULL DEFAULT 1,
    `api_url` VARCHAR(255) NOT NULL DEFAULT 'http://127.0.0.1:8001/3.1',
    `api_user` VARCHAR(255) NOT NULL DEFAULT 'restadmin',
    `api_pass` VARCHAR(255) NOT NULL DEFAULT '',
    `postorius_url` VARCHAR(255) NOT NULL DEFAULT '/postorius',
    `hyperkitty_url` VARCHAR(255) NOT NULL DEFAULT '/hyperkitty',
    PRIMARY KEY (`mailmanthree_config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
