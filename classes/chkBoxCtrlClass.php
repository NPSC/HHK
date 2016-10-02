<?php
/**
 * chkBoxCtrlClass.php
 *
 * @category  site
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

class chkBoxCtrlClass {
    private $cbLabelArray;
    private $cbCodeArray;
    private $cbValueArray;
    private $genRcrds = array();
    private $htmlNameBase = "";
    private $title = "";
    private $rows = 0;
    protected $class = "";

    function __Construct($con, $genLkupTabelName, $title, $htmlNameBase, $defaultVal, $sort = "Code") {
        $this->genRcrds = readGenLookups($con, $genLkupTabelName, $sort);
        $this->rows = count($this->genRcrds);
        $this->htmlNameBase = $htmlNameBase;
        $this->title = $title;


        foreach ($this->genRcrds as $rcrd) {
            $this->cbLabelArray[$rcrd[0]] = $rcrd[1];
            $this->cbCodeArray[] = $rcrd[0];
            $this->cbValueArray[$rcrd[0]] = $defaultVal;
        }
     }

    function createMarkup() {
        $mkup = "<table><tr><th>$this->title</th></tr>";

        foreach ($this->cbLabelArray as $code => $val) {

            if ($this->cbValueArray[$code] === false)
                $checked = "";
            else
                $checked = "checked='checked'";

            if ($this->class != "")
                $classMkup = " class='".$this->class."' ";
            else
                $classMkup = "";

            $mkup .="<tr><td style='vertical-align:middle;'><input name='".$this->htmlNameBase."[$code]'
                id='".$this->htmlNameBase.$code."' type='checkbox' $checked $classMkup value='$code'/>
                <label for='".$this->htmlNameBase.$code."'>".$val."</label></td></tr>";
        }

        $mkup .= "</table>";
        return $mkup;
    }

    // code, value
    public function setReturnValues($rtnVal) {
        // pre-false-ify
        foreach ($this->cbCodeArray as $code) {
            $this->cbValueArray[$code] = false;
        }
        // true-ify only the ones returned.
        if (is_array($rtnVal)) {
            foreach ($rtnVal as $code => $val) {
                $this->cbValueArray[$code] = true;
            }
        }
    }

    public function setCsvLabel() {
        $includes = "";

        foreach ($this->get_cbCodeArray() as $code) {
            if ($this->get_cbValueArray($code)) {
                $includes .= $this->get_cbLabelArray($code).", ";
            }
        }

        $includes = substr($includes, 0, strlen($includes) - 2);
        return $includes;
    }

    public function setSqlString() {
        $dcollect = "";
        if (!$this->isAllSameState()) {
            foreach ($this->get_cbCodeArray() as $code) {
                if ($this->get_cbValueArray($code)) {
                    $dcollect .= "'$code', ";
                }
            }
            $dcollect = substr($dcollect, 0, strlen($dcollect) - 2);
        }
        return $dcollect;
    }

    function isAllSameState() {
        $unChecked = 0;
        $checked = 0;

        foreach ($this->cbValueArray as $val) {
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

    public function set_cbValueArray($val, $indx) {
        $this->cbValueArray[$indx] = $val;
    }
    public function get_cbValueArray($indx) {
        return $this->cbValueArray[$indx];
    }
    //***************************************************
    public function set_cbLabelArray($val, $indx) {
        $this->cbLabelArray[$indx] = $val;
    }
    public function get_cbLabelArray($indx) {
        return $this->cbLabelArray[$indx];
    }
    //***************************************************
    public function get_cbCodeArray() {
        return $this->cbCodeArray;
    }
    //***************************************************
    public function get_genRcrds() {
        return $this->genRcrds;
    }
    //***************************************************
    public function get_rows() {
        return $this->rows;
    }
    //***************************************************
    public function get_htmlNameBase() {
        return $this->htmlNameBase;
    }
    //***************************************************
    public function set_class($val) {
        $this->class = $val;
    }
    public function get_class() {
        return $this->class;
    }

}
?>
