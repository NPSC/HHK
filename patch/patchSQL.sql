
Alter Table payment_method
	Add Column `Gl_Code` VARCHAR(45) NOT NULL DEFAULT '' After Method_Name;

delete from payment_method where idPayment_method = 4;

// Put room category settings on the Resource BUilder.
UPDATE `gen_lookups` SET `Type` = 'u' WHERE `Table_Name`='Room_Category';
UPDATE `gen_lookups` SET `Type` = 'u' WHERE `Table_Name`='Room_Rpt_Cat'
DELETE from `page_securitygroup` WHERE `idPage` in (SELECT DISTINCT `idPage` FROM `page` WHERE `File_Name` = 'checkDateReport.php');
DELETE FROM `page` WHERE `File_Name` = 'checkDateReport.php';
