UPDATE `sys_config` SET `Show` = 0 where `Key` in ("mode", "sId");
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Show`) VALUES ('useGLCodes', 'false', 'b', 'f', '0');
