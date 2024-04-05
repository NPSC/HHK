<?php

namespace HHK;

use DateTime;
use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\House\ResourceBldr;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Labels;
use HHK\sec\Session;



/**
 * Checklist.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Checklist
 *
 * @author Eric
 */

class Checklist
{
    const ChecklistRootTablename = 'Checklist';


    public function __construct(\PDO $dbh, $checklistType) {

        $this->checklistType = $checklistType;

        readGenLookupsPDO($dbh, self::ChecklistRootTablename, 'Order');
    }

    /**
     * Creates a list of checklist categories with the USE column.
     * @param \PDO $dbh
     * @param \HHK\sec\Labels $labels
     * @return string
     */
    public static function createChecklistCategories(\PDO $dbh, Labels $labels) {

        $tbl = ResourceBldr::getSelections($dbh, self::ChecklistRootTablename, 'm', $labels);

        return $tbl->generateMarkup(["class" => "sortable"]);
    }

    /**
     * Creates the types for editing the item content.
     * @param \PDO $dbh
     * @return string
     */
    public static function createChecklistTypes(\PDO $dbh) {

        // Chceklist category selectors
        $stmt = $dbh->query("SELECT
            `g`.`Code` as `Table_Name`, g.Description
        FROM
            `gen_lookups` `g`
        WHERE
            `g`.`Table_Name` = '" . self::ChecklistRootTablename . "' and `g`.`Substitute` = 'y';");

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if(count($rows) == 0){
            return "";
        }

        $selChecklists = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows, ''),
            [
                'name' => 'selChecklistLookup',
                'data-type' => 'd',
                'class' => 'hhk-selLookup'
            ]
        );

        return $selChecklists;
    }

    public static function createEditMarkup(\PDO $dbh){

        $labels = new Labels();
        $cblistSelections = self::createChecklistCategories($dbh, $labels);
        $selChecklistItems = self::createChecklistTypes($dbh);

        //Checklist categories
        $cblistMkup = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "Checklists") . 
            HTMLContainer::generateMarkup("form",
                HTMLContainer::generateMarkup("div", $cblistSelections, ['class'=>'lookupTbl']) .
                HTMLContainer::generateMarkup("div",
                    HTMLInput::generateMarkup("Save", ['type'=>'button', 'id'=>'btncblistSave', 'class'=>'hhk-saveccblist', 'data-type'=>'h'])
                ,['class'=>'hhk-flex justify-content-end mt-2'])
            ,['id'=>'formcblist'])
        ,['class'=>'m-2']);

        //checklist items
        if($selChecklistItems !== ""){
            $tbl = new HTMLTable();
            $tbl->addBodyTr(HTMLTable::makeTh("Checklist") . HTMLTable::makeTd($selChecklistItems));

            $cblistMkup .= HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Checklist Items") . 
                HTMLContainer::generateMarkup("form",
                    $tbl->generateMarkup() .
                    HTMLContainer::generateMarkup("div", "", ['class'=>'lookupDetailTbl', 'id'=>'divchecklistCat']) .
                    HTMLContainer::generateMarkup("div",
                        HTMLInput::generateMarkup("Save", ['type'=>'button', 'id'=>'btncblistSaveCat', 'class'=>'hhk-saveLookup', 'data-type'=>'d'])
                    ,['class'=>'hhk-flex justify-content-end mt-2'])
                ,['id'=>'formcbCat'])
            ,['class'=>'m-2']);
        }

        return $cblistMkup;

    }

    /**
     * Create a checklist for a user page
     * @param mixed $entityId Id for the checklist type.
     * @return string
     */
    public static function createChecklistMkup(\PDO $dbh, $entityId, $checklistType) {

        $clName = '';
        $checklistTbl = new HTMLTable();

        $query = "
SELECT
    g.`Table_Name`,
    g.`Code`,
    g.`Description`,
    g2.`Description` AS `CkListName`,
    IFNULL(cl.`Value`, '') AS `Value`,
    IFNULL(cl.`Value_Date`, '') AS `Date`
FROM
    `gen_lookups` g
        JOIN
    `gen_lookups` `g2` ON `g`.`Table_Name` = `g2`.`Code`
        AND `g2`.`Table_Name` = '" . self::ChecklistRootTablename . "'
        AND `g2`.`Substitute` = 'y'
        LEFT JOIN
    checklist_item cl ON g.Table_Name = cl.GL_TableName
        AND cl.`GL_Code` = g.Code
        AND cl.`Entity_Id` = :entityId
WHERE g.Table_Name = :tblName
ORDER BY g.`Order`;";

        $stmt = $dbh->prepare($query);
        $stmt->execute([':entityId' => $entityId, ':tblName' => $checklistType]);

        if($stmt->rowCount() == 0){
            return "";
        }

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Got a live one
            $label = HTMLContainer::generateMarkup('label', $r['Description'] . ': ', array('for' => 'Item' . $r['Code']));

            $bcdateAttr = array('style' => 'display:none;', 'id' => 'disp' . $r['Code']);
            $cbAttr = ['name' => 'cbChecklistItem[' . $checklistType . '][' . $r['Code'] . ']', 'id'=>'Item' . $r['Code'], 'type' => 'checkbox', 'data-code'=> $r['Code'], 'class' => 'hhk-checkboxlist'];

            $strDate = '';
            if ($r['Date'] !== '') {
                $strDate = new DateTime($r['Date']);
                $strDate = $strDate->format('M j, Y');
                $cbAttr['checked'] = 'checked';
                $bcdateAttr['style'] = '';
            }


            $checklistTbl->addBodyTr(
                HTMLTable::makeTh($label . HTMLInput::generateMarkup('', $cbAttr), ['class' => 'tdlabel'])

                . HTMLTable::makeTd(HTMLContainer::generateMarkup('div', 'Date: ' .
                    HTMLInput::generateMarkup($strDate, ['name' => 'checklistDate[' . $checklistType . '][' . $r['Code'] .']', 'class' => 'ckdate ml-1', 'readonly'=>'readonly']), $bcdateAttr)
                    , ['class'=>'tdChecklistDate'])

            );

            $clName = $r['CkListName'];
        }

        return HTMLContainer::generateMarkup("fieldset", 
            HTMLContainer::generateMarkup("legend", $clName . ' Checklist', ['style'=>'font-weight: bold;']) .
            $checklistTbl->generateMarkup(['class' => 'checklistTbl'])
        , ['class'=>'hhk-panel']);
    }

    /**
     * Save Checklist items
     * @param \PDO $dbh
     * @param int $entityId
     * @param string $checklistType
     * @return int number of affected items
     */
    public static function saveChecklist(\PDO $dbh, int $entityId, string $checklistType) {

        $checklistDates = filter_input(INPUT_POST, "checklistDate", FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FORCE_ARRAY);
        $cbChecklistItems = filter_input(INPUT_POST, "cbChecklistItem", FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FORCE_ARRAY);

        $affectedRows = 0;

        $uS = Session::getInstance();

         $query = "
SELECT
    g.`Table_Name`,
    g.`Code`,
    g.`Description`,
    g2.`Description` AS `CkListName`,
    IFNULL(cl.`Value`, '') AS `Value`,
    IFNULL(DATE(cl.`Value_Date`), '') AS `Date`
FROM
    `gen_lookups` g
        JOIN
    `gen_lookups` `g2` ON `g`.`Table_Name` = `g2`.`Code`
        AND `g2`.`Table_Name` = '" . self::ChecklistRootTablename . "'
        AND `g2`.`Substitute` = 'y'
        LEFT JOIN
    checklist_item cl ON g.Table_Name = cl.GL_TableName
        AND cl.`GL_Code` = g.Code
        AND cl.`Entity_Id` = :entityId
WHERE g.Table_Name = :tblName
ORDER BY g.`Order`;";

        $stmt = $dbh->prepare($query);
        $stmt->execute([':entityId' => $entityId, ':tblName' => $checklistType]);

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if(isset($cbChecklistItems[$checklistType][$r["Code"]]) && strtolower($cbChecklistItems[$checklistType][$r["Code"]]) == "on"){ //if item is checked
                if(isset($checklistDates[$checklistType][$r["Code"]]) && $checklistDates[$checklistType][$r["Code"]] != ""){ //date is filled
                    $date = new DateTime($checklistDates[$checklistType][$r["Code"]]);

                    if($r["Date"] != $date->format("Y-m-d")){
                        //upsert
                        $stmt = $dbh->prepare("insert into checklist_item (`Entity_Id`, `GL_tableName`,`GL_Code`,`Status`,`Value`,`Value_Date`) values 
                        (:entityId, :tblName, :code, :status, :value, :date) ON DUPLICATE KEY 
                        UPDATE `Value_Date` = :date2, `Updated_By` = :updatedBy, `Last_Updated` = :lastUpdated;");

                        $stmt->execute([
                            ':entityId' => $entityId,
                            ':tblName' => $checklistType,
                            ':code' => $r["Code"],
                            ':status' => "a",
                            ':value' => true,
                            ':date' => $date->format("Y-m-d"),
                            ':date2' => $date->format("Y-m-d"),
                            ':updatedBy' => $uS->username,
                            ':lastUpdated' => (new DateTime())->format("Y-m-d H:i:s")
                        ]);

                        $affectedRows++;
                    }

                }
            }else if ($r["Value"] == "1"){ //if not checked but item in DB - delete
                //Clear checklist item
                $stmt = $dbh->prepare("delete from checklist_item where Entity_Id = :entityId and GL_TableName = :tblName and GL_Code = :code;");
                $stmt->execute([
                    ':entityId' => $entityId,
                    ':tblName' => $checklistType,
                    ':code' => $r["Code"]
                ]);
                $affectedRows++;
            }

        }
        return $affectedRows;
    }
}