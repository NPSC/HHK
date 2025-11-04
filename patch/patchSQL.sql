ALTER TABLE `reservation`
ADD COLUMN IF NOT EXISTS `No_Vehicle` TINYINT NOT NULL DEFAULT 0 AFTER `Number_Guests`;

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