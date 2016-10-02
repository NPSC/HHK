<?php
/**
 * ColumnSelectors.php
 *
 *
 * @category  house
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2015 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
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

    /**
     *
     * @param array $cols
     * @param string $contrlName
     */
    public function __construct(array $cols, $contrlName) {
        $this->cols = $cols;
        $this->controlName = $contrlName;
    }


    public function setColumnSelectors(array $post) {

        if (isset($post[$this->controlName])) {

            $flds = array_flip($post[$this->controlName]);

            // Find it in the colums array
            for ($n = 0; $n < count($this->cols); $n++) {

                if (isset($flds[$this->cols[$n][1]]) || $this->cols[$n][3] == 'f') {
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

            $attrs = array('value'=>$c[1]);

            if ($c[2] != '') {
                $attrs['selected'] = 'selected';
            }

            if ($c[3] != 'f') {
                $opts .= HTMLContainer::generateMarkup('option', $c[0], $attrs);
                $countr++;
            }
        }


        return HTMLSelector::generateMarkup($opts, array('name'=>$this->controlName . '[]', 'multiple'=>'multiple', 'size'=>$countr));
    }



    public function makeSelectorTable($includeSetClear = TRUE) {

        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Include Fields'));
//        $tbl->addBodyTr($this->makeColumnSelectors());

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
                $titles[] = $c[0];
            }
        }

        return $titles;
    }

    public function getFilteredFields() {

        $titles = array();

        foreach ($this->cols as $c) {

            if ($c[2] != '') {
                $titles[] = $c;
            }
        }

        return $titles;
    }

}

