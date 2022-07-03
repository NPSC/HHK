<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\ColumnSelectors;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;

/**
 * AbtractReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AbtractReport
 *
 * @author Will
 */
abstract class AbstractReport {

    protected \PDO $dbh;
    public ReportFilter $filter;
    public array $filteredFields;
    public array $filteredTitles;
    public ColumnSelectors $colSelector;
    protected $defaultFields;
    protected array $cFields;
    public array $fieldSets;
    public array $resultSet = [];
    protected string $query = "";
    public string $filterMkup = "";
    protected $request;

    public function __construct(\PDO $dbh, string $report = "", array $cFields = [], array $request = []){
        $uS= Session::getInstance();

        $this->dbh = $dbh;
        $this->request = $request;
        $this->filter = new ReportFilter();
        $this->filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
        $this->filter->createHospitals();

        $this->cFields = $this->makeCFields();

        $this->fieldSets = ReportFieldSet::listFieldSets($this->dbh, $report, true);
        $fieldSetSelection = (isset($_REQUEST['fieldset']) ? $_REQUEST['fieldset']: '');
        $this->colSelector = new ColumnSelectors($this->cFields, 'selFld', true, $this->fieldSets, $fieldSetSelection);

        //default fields
        foreach($this->cFields as $field){
            if($field[2] == 'checked'){
                $this->defaultFields[] = $field[1];
            }
        }

        $this->setFilterMkup();
    }

    public function getFilterMarkup(){
        $this->filterMkup = HTMLContainer::generateMarkup("div", $this->filterMkup, array("id"=>"filterSelectors", "class"=>"hhk-flex"));
        $btnMkup = HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("Run Here", array("type"=>"submit", "name"=>"btnHere", "id"=>"btnHere")) .
            HTMLInput::generateMarkup("Download to Excel", array("type"=>"submit", "name"=>"btnExcel", "id"=>"btnExcel"))
        , array("id"=>"filterBtns"));

        //wrap in ui-widget + form
        return HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("form",
                    $this->filterMkup . $btnMkup
                , array("method"=>"POST", "action"=>htmlspecialchars($_SERVER["PHP_SELF"])))
            , array("class"=>"ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog filterWrapper"));
    }

    public function getResultSet():array {
        $this->buildQuery();
        if($this->query != ''){
            $stmt = $this->dbh->query($this->query);

            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

                $g = array();
                foreach ($this->filteredFields as $f) {
                    if(isset($f[7]) && $f[7] == "date"){
                        $g[$f[0]] = date('c', strtotime($r[$f[1]]));
                    }else{
                        $g[$f[0]] = $r[$f[1]];
                    }
                }
                $this->resultSet[] = $g;
            }
        }else{
            $this->resultSet = [];
        }
        return $this->resultSet;
    }

    public function generateMarkup():string {
        $mkup = "";

        return $mkup;
    }

    public function downloadExcel():void {

    }

    public function getDefaultFields(){
        return $this->defaultFields;
    }

}
?>