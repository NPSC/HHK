
ALTER TABLE `payment` 
    ADD COLUMN `External_Id` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Notes`;

ALTER TABLE `neon_type_map` 
    ADD COLUMN `Neon_Type_Name` VARCHAR(45) NOT NULL DEFAULT '' AFTER `Neon_Type_Code`;


Replace INTO `neon_lists` (`Method`, `List_Name`, `List_Item`, `HHK_Lookup`) VALUES 
    ('account/listIndividualTypes', 'individualTypes', 'individualType', 'Vol_Type'),
    ('donation/listFunds', 'funds', 'fund', 'Fund'),
    ('common/listTenders', 'tenders', 'tender', 'Pay_Type'),
    ('common/listCreditCardTypes', 'creditCardTypes', 'creditCardType', 'Charge_Cards');

UPDATE `gen_lookups` SET `Substitute`='VISA' WHERE `Table_Name`='Charge_Cards' and`Code`='1';
UPDATE `gen_lookups` SET `Substitute`='M/C' WHERE `Table_Name`='Charge_Cards' and`Code`='2';
UPDATE `gen_lookups` SET `Substitute`='DCVR' WHERE `Table_Name`='Charge_Cards' and`Code`='3';
UPDATE `gen_lookups` SET `Substitute`='AMEX' WHERE `Table_Name`='Charge_Cards' and`Code`='4';

Replace into `gen_lookups` (`Table_Name`, `Code`, `Description`, `Substitute`, `Type`, `Order`) VALUES ('Fund', '1', 'External Donation', '', 'u', 0);

update gen_lookups set `Type` = 'h' where `Table_Name` = 'Patient_Rel_Type';

update gen_lookups set Substitute = 'Guests' where `Table_Name` = 'Patient_Rel_Type' and Code != 'slf';