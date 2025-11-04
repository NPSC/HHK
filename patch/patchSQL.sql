ALTER TABLE `reservation`
ADD COLUMN IF NOT EXISTS `No_Vehicle` TINYINT NOT NULL DEFAULT 0 AFTER `Number_Guests`;

-- add mandatory reservation toggles
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("InsistResvDiag", false, "b", "h", "Insist the user fills the diagnosis field on reservation", 1);
INSERT IGNORE INTO `sys_config` (`Key`, `Value`, `Type`, `Category`, `Description`, `Show`) VALUES ("InsistResvUnit", false, "b", "h", "Insist the user fills the location/unit field on reservation", 1);
INSERT IGNORE INTO `gen_lookups` (
    `Table_Name`,
    `Code`,
    `Description`,
    `Substitute`,
    `Type`,
    `Order`
)
VALUES
    ('labels_category', 'in', 'Insurance', '', '', 35);

INSERT IGNORE INTO `labels` (
    `Key`,
    `Value`,
    `Type`,
    `Category`,
    `Header`,
    `Description`
)
VALUES
    (
        'groupNumber',
        'Group Number',
        's',
        'in',
        '',
        'Default: Group Number'
    ),
    (
        'memberNumber',
        'Member Number',
        's',
        'in',
        '',
        'Default: Member Number'
    );
