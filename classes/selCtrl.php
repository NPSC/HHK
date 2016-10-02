<?php
/**
 * selCtrl.php
 *
 * @category  Site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Selector control
 */
class selCtrl {

    protected $labelArray;
    protected $codeArray;
    protected $valueArray;
    protected $htmlNameBase = "";
    protected $rows = 0;
    protected $class = "";
    protected $title = "";
    protected $multiple;

    function __Construct($dbcon, $genLkupTabelName, $defaultVal, $htmlNameBase, $emptyOption, $title = "", $orderBy = "Description" ) {

        $genRcrds = readGenLookups($dbcon, $genLkupTabelName, $orderBy);

        if ($emptyOption) {
            $this->rows = count($genRcrds) + 1;
            $this->labelArray[""] = "";
            $this->codeArray[] = "";
            $this->valueArray[""] = !$defaultVal;
            $myDefValue = $defaultVal;
        }
        else {
            $this->rows = count($genRcrds);
            $myDefValue = !$defaultVal;
        }

        $this->htmlNameBase = $htmlNameBase;
        $this->title = $title;

        foreach ($genRcrds as $rcrd) {
            $this->labelArray[$rcrd[0]] = $rcrd[1];
            $this->codeArray[] = $rcrd[0];
            $this->valueArray[$rcrd[0]] = $myDefValue;
            $myDefValue = $defaultVal;
        }

    }

    public function createMarkup($size=1, $multiple=false) {
        if ($multiple)
            $multMarkup = "[]' multiple='multiple' ";
        else
            $multMarkup = "'";

        if ($size > 1)
            $sizeMarkup = " size='$size' ";
        else
            $sizeMarkup = "";

        if ($this->class != "")
            $classMkup = " class='".$this->class."' ";
        else
            $classMkup = "";

        $mkup = "<select id='".$this->htmlNameBase."' name='". $this->htmlNameBase . $multMarkup . $sizeMarkup . $classMkup.">";

        foreach ($this->labelArray as $code => $val) {

            if ($this->valueArray[$code] === false)
                $checked = "";
            else
                $checked = "selected='selected'";

            $mkup .= "<option value='$code' $checked>$val</option>";

        }
        $mkup .= "</select>";
        return $mkup;
    }

        // Set posted values into control.
    // code, value
    public function setReturnValues($rtnVal) {
        // pre-false-ify
        foreach ($this->codeArray as $code) {
            $this->valueArray[$code] = false;
        }

        if (!is_null($rtnVal)) {
            if (is_array($rtnVal))  {
                // true-ify only the ones returned.
                foreach ($rtnVal as $val) {
                    $this->valueArray[$val] = true;
                }
            }
            else {
                $this->valueArray[$rtnVal] = true;
            }

        }
    }

    public function getCsvLabel() {
        $includes = "";
        foreach ($this->get_codeArray() as $code) {
            if ($this->get_value($code) && $this->get_label($code) != "") {
                $includes .= $this->get_label($code).", ";
            }
        }
        $includes = substr($includes, 0, strlen($includes) - 2);
        return $includes;
    }

    public function getCvsCode() {
        $dcollect = "";
        if (!$this->isAllSameState()) {
            foreach ($this->get_codeArray() as $code) {
                if ($code != "" && $this->get_value($code)) {
                    $dcollect .= "'$code', ";
                }
            }
            $dcollect = substr($dcollect, 0, strlen($dcollect) - 2);
        }
        return $dcollect;
    }

    protected function isAllSameState() {
        $unChecked = 0;
        $checked = 0;

        foreach ($this->valueArray as $val) {
           if ($val === false)
                $unChecked++;
            else
                $checked++;
        }

        if ($checked == 0 || $unChecked == 0)
            return true;
        else
            return false;
    }

    public function set_value($val, $indx) {
        $this->valueArray[$indx] = $val;
    }
    public function get_value($indx) {
        return $this->valueArray[$indx];
    }
    //***************************************************
    public function set_label($val, $indx) {
        $this->labelArray[$indx] = $val;
    }
    public function get_label($indx) {
        return $this->labelArray[$indx];
    }
    //***************************************************
    public function set_code($val) {
        $this->codeArray[] = $val;
    }
    //***************************************************
    public function set_class($val) {
        $this->class = $val;
    }
    public function get_class() {
        return $this->class;
    }
    //***************************************************
    public function get_codeArray() {
        return $this->codeArray;
    }
    //***************************************************
    public function get_rows() {
        return count($this->codeArray);
    }
    //***************************************************
    public function get_title() {
        return $this->title;
    }
    //***************************************************
    public function get_htmlNameBase() {
        return $this->htmlNameBase;
    }

}
?>
