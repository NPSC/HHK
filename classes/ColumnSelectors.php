<?php
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

    /**
     *
     * @param array $cols
     * @param string $contrlName
     */
    public function __construct(array $cols, $contrlName) {
        $this->cols = $cols;
        $this->controlName = $contrlName;
        $this->columnDefs = array();
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

            if (is_array($c[1])) {
                $attrs = array('value'=>$c[1][0]);
                $val = $c[0][0];
            } else {
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


        return HTMLSelector::generateMarkup($opts, array('name'=>$this->controlName . '[]', 'multiple'=>'multiple', 'size'=>$countr));
    }



    public function makeSelectorTable($includeSetClear = TRUE) {

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Include Fields'));

        $tbl->addBodyTr(HTMLTable::makeTd($this->makeDropdown()));

        if ($includeSetClear) {
            $tbl->addBodyTr(HTMLTable::makeTd(
                $this->getRanges()));
        }

        return $tbl;
    }

    public function getRanges() {

        return HTMLInput::generateMarkup('Select All', array('type'=>'button', 'id'=>'cbColSelAll', 'style'=>'margin-right:10px;'))
               . HTMLInput::generateMarkup('Clear All', array('type'=>'button', 'id'=>'cbColClearAll'));
    }

    public function getFilteredTitles() {

        $titles = array();

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

        $titles = array();
        $colIndex = 0;

        foreach ($this->cols as $c) {

            if ($c[2] != '') {

                if (!is_string($c[1])) {

                    for ($i = 0; $i < count($c[1]); $i++) {
                        $d = $c;
                        $d[1] = $c[1][$i];
                        $d[0] = $c[0][$i];
                        $titles[] = $d;
                        if (isset($d[7]) && $d[7] == 'date') {
                            $this->columnDefs[] = $colIndex;
                        }
                        $colIndex++;
                    }

                } else {
                    $titles[] = $c;
                    if (isset($c[7]) && $c[7] == 'date') {
                        $this->columnDefs[] = $colIndex;
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