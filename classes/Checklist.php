<?php

namespace HHK;

use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\House\ResourceBldr;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Labels;



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
            $cbAttr = ['name' => 'Item' . $r['Code'], 'type' => 'checkbox', 'data-code'=> $r['Code'], 'class' => 'hhk-checkboxlist'];

            $strDate = '';
            if ($r['Date'] !== '') {
                $strDate = new \DateTime($r['Date']);
                $strDate = $strDate->format('m, j, Y');
                $cbAttr['checked'] = 'checked';
                $bcdateAttr['style'] = 'display:table-cell;';
            }


            $checklistTbl->addBodyTr(
                HTMLTable::makeTh($label . HTMLInput::generateMarkup('', $cbAttr)
                . HTMLInput::generateMarkup('0', ['type'=>'hidden','name'=>'cbMarker' . $r['Code']]), ['class' => 'tdlabel'])

                . HTMLTable::makeTd(HTMLContainer::generateMarkup('div', 'Date: ' .
                    HTMLInput::generateMarkup($strDate, ['name' => 'date' . $r['Code'], 'class' => 'ckdate', 'readonly'=>'readonly', 'style' => 'margin-left:1em;']), $bcdateAttr)
                    , ['style'=>'min-width:200px;'])

            );

            $clName = $r['CkListName'];
        }

        return HTMLContainer::generateMarkup("fieldset", 
            HTMLContainer::generateMarkup("legend", $clName . ' Checklist', ['style'=>'font-weight: bold;']) .
            $checklistTbl->generateMarkup(['class' => 'checklistTbl'])
        , ['class'=>'hhk-panel']);
    }

    public static function saveChecklist(\PDO $dbh, $entityId, $checklistType) {

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

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        }

    }
}