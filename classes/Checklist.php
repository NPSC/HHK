<?php

namespace HHK;

use HHK\HTMLControls\{HTMLTable, HTMLContainer, HTMLInput};
use HHK\House\Report\ResourceBldr;
use HHK\HTMLControls\HTMLSelector;
use HHK\sec\Labels;



/**
 * Notes.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2024 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Notes
 *
 * @author Eric
 */

class Checklist
{

    protected $checklistType;

    public function __construct(\PDO $dbh, $checklistType) {

        $this->checklistType = $checklistType;

        readGenLookupsPDO($dbh, 'Checklist', 'Order');
    }

    public static function createChecklists(\PDO $dbh, Labels $labels, $checklistRootTablename = 'Checklist') {

        $tbl = ResourceBldr::getSelections($dbh, $checklistRootTablename, 'm', $labels);

        return $tbl->generateMarkup(["class" => "sortable"]);
    }

    public static function createChecklistList(\PDO $dbh) {

        // Chceklist category selectors
        $stmt = $dbh->query("SELECT DISTINCT
            `g`.`Table_Name`, g2.Description
        FROM
            `gen_lookups` `g`
                JOIN
            `gen_lookups` `g2` ON `g`.`Table_Name` = `g2`.`Code`
                AND `g2`.`Table_Name` = 'Checklist'
                AND `g2`.`Substitute` = 'y'
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
     * Summary of createChecklist
     * @param mixed $entityId Id for the checklist type.
     * @return string
     */
    public function createChecklist($entityId) {

        // PSG checkboxes

        $tableName = 'Checklist_PSG';
        $checklistTbl = new HTMLTable();
        $items = [];

        foreach ($items as $cbsRow) {

            if (strtolower($cbsRow['Substitute']) == 'y') {
                // Got a live one
                $label = HTMLContainer::generateMarkup('label', $cbsRow['Description'] . ': ', array('for' => $tableName . 'Item' . $cbsRow['Code']));

                $cbAttr = ['name' => $tableName . 'Item' . $cbsRow['Code'], 'type' => 'checkbox'];


                $checklistTbl->addBodyTr(HTMLTable::makeTd($label . HTMLInput::generateMarkup('', $cbAttr), ['class' => 'tdlabel'])
                    . HTMLTable::makeTd('Date: ' . HTMLInput::generateMarkup('', ['name' => $tableName . 'Date' . $cbsRow['Code'], 'class' => 'ckbdate', 'style' => 'margin-left:1em;'])));
            }
        }

        return $checklistTbl->generateMarkup();
    }



}