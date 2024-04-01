<?php

namespace HHK;

use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\House\Report\ResourceBldr;
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
        $stmt = $dbh->query("SELECT DISTINCT
            `g`.`Table_Name`, g2.Description
        FROM
            `gen_lookups` `g`
                JOIN
            `gen_lookups` `g2` ON `g`.`Table_Name` = `g2`.`Code`
                AND `g2`.`Table_Name` = '" . self::ChecklistRootTablename .
                "' AND `g2`.`Substitute` = 'y'
        WHERE
            `g`.`Type` = 'd';");

        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        $selChecklists = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($rows, ''),
            [
                'name' => 'selChecklistLookup',
                'data-type' => 'd',
                'class' => 'hhk-selLookup'
            ]
        );

        return $selChecklists;
    }

    /**
     * Create a checklist for a user page
     * @param mixed $entityId Id for the checklist type.
     * @return null
     */
    public static function createChecklist(\PDO $dbh, $entityId, $checklistType, HTMLTable &$checklistTbl) {

        $clName = '';
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
                HTMLTable::makeTd($label . HTMLInput::generateMarkup('', $cbAttr)
                . HTMLInput::generateMarkup('0', ['type'=>'hidden','name'=>'cbMarker' . $r['Code']]), ['class' => 'tdlabel'])

                . HTMLTable::makeTd(HTMLContainer::generateMarkup('div', 'Date: ' .
                    HTMLInput::generateMarkup($strDate, ['name' => 'date' . $r['Code'], 'class' => 'ckdate', 'readonly'=>'readonly', 'style' => 'margin-left:1em;']), $bcdateAttr)
                    , ['style'=>'min-width:200px;'])

            );

            $clName = $r['CkListName'];
        }

        return $clName;
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