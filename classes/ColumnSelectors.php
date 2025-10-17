<?php

namespace HHK;

use HHK\HTMLControls\{HTMLContainer, HTMLTable, HTMLSelector, HTMLInput};
use HHK\sec\SecurityComponent;
use HHK\AlertControl\AlertMessage;

/**
 * ColumnSelectors.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * ColumnSelectors tries to generalize the column selector tool
 * @package Hospitality HouseKeeper
 * @author Eric
 */

class ColumnSelectors {

    /**
     * Column selector array
     *
     * @var array
     */
    protected $cols;

    /**
     *
     * @var string
     */
    protected $controlName;

    protected $columnDefs;
    protected $dateTimecolumnDefs;
    protected $dayColumnDefs;

    /**
     * Filter Sets array
     *
     * @var array
     */
    protected $filterSets;
    protected $filterSetSelection;
    protected $useFilterSets;
    protected $alertMsg;

    /**
     *
     * @param array $cols
     * @param string $contrlName
     * @param array $filterSets - 0 = index, 1 = description, 2 = option group name.
     */
    public function __construct(array $cols, $contrlName, $useFilterSets = false, $filterSets = false, $filterSetSelection = false) {
        $this->cols = $cols;
        $this->controlName = $contrlName;
        $this->columnDefs = array();
        $this->dateTimecolumnDefs = array();
        $this->dayColumnDefs = array();
        $this->useFilterSets = $useFilterSets;
        $this->filterSets = $filterSets;
        $this->filterSetSelection = $filterSetSelection;
    }


    public function setColumnSelectors(array $post) {

        if (isset($post[$this->controlName])) {

            $flds = array_flip($post[$this->controlName]);

            // Find it in the colums array
            for ($n = 0; $n < count($this->cols); $n++) {

                if (is_array($this->cols[$n][1])) {
                    $field = $this->cols[$n][1][0];
                } else {
                    $field = $this->cols[$n][1];
                }

                if (isset($flds[$field]) || $this->cols[$n][3] == 'f') {
                    $this->cols[$n][2] = 's';
                } else {
                    $this->cols[$n][2] = '';
                }
            }
        }
    }

    public function makeDropdown() {
        // Make column selector

        $opts = '';
        $countr = 0;

        foreach ($this->cols as $c) {

            if (is_array($c[1]) && isset($c["selOptionTitle"])){
                $attrs = array('value'=>$c[1][0]);
                $val = $c["selOptionTitle"];
            }else if (is_array($c[1])) {
                $attrs = array('value'=>$c[1][0]);
                $val = $c[0][0];
            } else{
                $attrs = array('value'=>$c[1]);
                $val = $c[0];
            }

            if ($c[2] != '') {
                $attrs['selected'] = 'selected';
            }

            if ($c[3] != 'f') {
                $opts .= HTMLContainer::generateMarkup('option', $val, $attrs);
                $countr++;
            }
        }


        return HTMLSelector::generateMarkup($opts, array('name'=>$this->controlName . '[]', 'multiple'=>'multiple', 'size'=>$countr, 'style'=>'width: 100%'));
    }

    public function makeFilterSetSelector(){
        $size = count($this->filterSets) + 3;
        $size = ($size > 15 ? 15: $size);

        return HTMLSelector::generateMarkup(
            HTMLSelector::doOptionsMkup($this->filterSets, $this->filterSetSelection, TRUE)
        , ['style'=>'width: 100%;', 'name'=>'fieldset', 'size'=>$size]);
    }

    public function makeFilterSetButtons(){
        return HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("label", "Title:", ["for"=>"fieldsetName", "style"=>"margin-right: 5px;"]) .
                HTMLInput::generateMarkup("", ['name'=>'fieldsetName']) .
                $this->getAlertMsg(AlertMessage::Alert, 'Error')->createMarkup()
            , ["id"=>"fieldSetName", "style"=>"display: none;"]) .
            HTMLContainer::generateMarkup("button", "Save Changes", ['id'=>"saveSet", 'style'=>'margin-top: 1em; display: none', 'type'=>'button']) .
            HTMLContainer::generateMarkup("button", "Save as Personal Set", ['id'=>"saveNewSet", 'style'=>'margin-top: 1em; display: none', 'type'=>'button']) .
            (SecurityComponent::is_Admin() ? HTMLContainer::generateMarkup("button", "Save as House Set", ['id'=>"saveGlobalSet", 'style'=>'margin-top: 1em; display: none', 'type'=>'button']): '') .
            HTMLContainer::generateMarkup("button", "Delete Set", ['id'=>"delSet", 'style'=>'margin-top: 1em; display: none', 'type'=>'button'])
            ,['style'=>"display: flex; flex-flow: column", 'id'=>'filterSetBtns']);
    }

    public function makeSelectorTable($includeSetClear = TRUE) {

        $tbl = new HTMLTable();

        $tbl->addheaderTr(HTMLTable::makeTh('Include Fields', ['colspan'=>'2']));

        $bodyTr = '';
        $filterActionTr = false;

        //if using filterSets
        if($this->useFilterSets){
            $bodyTr .= HTMLTable::makeTd($this->makeFilterSetSelector(), ['style'=>'vertical-align: top; border-bottom: 0; width: 215px;', 'id'=>'filterSets']);
            $filterActionTr = HTMLTable::makeTd($this->makeFilterSetButtons(), ['style'=>'vertical-align: bottom; border-top: 0;']);
            $tbl->addHeaderTr(HTMLTable::makeTh('Saved Sets') . HTMLTable::makeTh(HTMLContainer::generateMarkup('span', '', ['id'=>'filterSetTitle']) .  ' Fields')); //add 2nd header
        }

        $fieldsTdContent = $this->makeDropdown();

        if ($includeSetClear) {
            $fieldsTdContent .= $this->getRanges();
        }

        $bodyTr .= HTMLTable::makeTd($fieldsTdContent, ['id'=>'fields', 'rowspan'=>'2']);

        $tbl->addBodyTr($bodyTr);

        if($filterActionTr){
            $tbl->addBodyTr($filterActionTr);
        }

        return $tbl;
    }

    public function getRanges() {

        return HTMLContainer::generateMarkup('div',
            HTMLInput::generateMarkup('Select All', array('type'=>'button', 'id'=>'cbColSelAll', 'style'=>'margin-right:10px;', 'class'=>"ui-button ui-corner-all ui-widget"))
          . HTMLInput::generateMarkup('Clear All', array('type'=>'button', 'id'=>'cbColClearAll', 'class'=>'ui-button ui-corner-all ui-widget'))
        , ['style'=>'margin-top: 1em']);
    }

    public function getFilteredTitles() {

        $titles = [];

        foreach ($this->cols as $c) {

            if ($c[2] != '') {

                if (!is_string($c[0])) {

                    foreach ($c[0] as $f) {
                        $titles[] = $f;
                    }

                } else {
                    $titles[] = $c[0];
                }
            }
        }

        return $titles;
    }

    public function getFilteredFields() {

        $titles = [];
        $colIndex = 0;

        foreach ($this->cols as $c) {

            if ($c[2] != '') {

                if (!is_string($c[1])) {

                    // Unwind hidden columns
                    for ($i = 0; $i < count($c[1]); $i++) {
                        $d = $c;
                        $d[1] = $c[1][$i];
                        $d[0] = $c[0][$i];
                        $titles[$d[1]] = $d;
                        if (isset($d[7]) && $d[7] == 'date') {
                            $this->columnDefs[] = $colIndex;
                        }else if (isset($d[7]) && $d[7] == 'datetime') {
                            $this->dateTimecolumnDefs[] = $colIndex;
                        }else if (isset($d[7]) && $d[7] == 'day') {
                            $this->dayColumnDefs[] = $colIndex;
                        }
                        $colIndex++;
                    }

                } else {
                    $titles[$c[1]] = $c;
                    if (isset($c[7]) && $c[7] == 'date') {
                        $this->columnDefs[] = $colIndex;
                    }else if (isset($c[7]) && $c[7] == 'datetime') {
                        $this->dateTimecolumnDefs[] = $colIndex;
                    }else if (isset($c[7]) && $c[7] == 'day') {
                        $this->dayColumnDefs[] = $colIndex;
                    }
                    $colIndex++;
                }
            }
        }

        return $titles;
    }

    public function getColumnDefs() {

        return $this->columnDefs;

    }

    public function getDateTimeColumnDefs() {

        return $this->dateTimecolumnDefs;

    }

    public function getDayColumnDefs() {

        return $this->dayColumnDefs;

    }

    public function getAlertMsg($type, $msg) {
        // Instantiate the alert message control
        $this->alertMsg = new AlertMessage("divFieldsetError");
        $this->alertMsg->set_DisplayAttr("none");
        $this->alertMsg->set_Context($type);
        $this->alertMsg->set_iconId("alrIcon");
        $this->alertMsg->set_styleId("alrResponse");
        $this->alertMsg->set_txtSpanId("alrMessage");
        $this->alertMsg->set_Text($msg);
        return $this->alertMsg;
    }

}


class SelectColumn {

    /**
     *
     * @var string
     */
    protected $title;

    /** returned column name
     *
     * @var string
     */
    protected $fieldName;

    /**
     *
     * @var bool
     */
    protected $isSelected;

    /**
     *
     * @var bool
     */
    protected $isFixed;

    /**
     *
     * @var string
     */
    protected $excelType;

    /**
     *
     * @var string
     */
    protected $excelStyle;

    /**
     *
     * @var string
     */
    protected $dataTablesType;

    public function __construct($title, $fieldName, $isSelected, $isFixed, $excelType, $excelStyle, $dataTablesType = '') {
        $this->title = $title;
        $this->fieldName = $fieldName;
        $this->isSelected = $isSelected;
        $this->isFixed = $isFixed;
        $this->excelType = $excelType;
        $this->excelStyle = $excelStyle;
        $this->dataTablesType = $dataTablesType;
    }

    public function addTitle(&$titles) {
        if ($this->getIsSelected() || $this->getIsFixed()) {
            $titles[] = $this->title;
        }
    }

    public function addFieldName(&$fields) {
        if ($this->getIsSelected() || $this->getIsFixed()) {
            $fields[] = $this->fieldName;
        }
    }

    public function getIsSelected() {
        return $this->isSelected;
    }

    public function getIsFixed() {
        return $this->isFixed;
    }

    public function getExcelType() {
        return $this->excelType;
    }

    public function getExcelStyle() {
        return $this->excelStyle;
    }

    public function getDataTablesType() {
        return $this->dataTablesType;
    }
}