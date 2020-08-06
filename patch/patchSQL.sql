
-- remove checkDateReport.php from pages
DELETE from `page_securitygroup` WHERE `idPage` in (SELECT DISTINCT `idPage` FROM `page` WHERE `File_Name` = 'checkDateReport.php');
DELETE FROM `page` WHERE `File_Name` = 'checkDateReport.php';