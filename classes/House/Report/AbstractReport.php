<?php

namespace HHK\House\Report;

use HHK\HTMLControls\HTMLContainer;
use HHK\ColumnSelectors;
use HHK\HTMLControls\HTMLInput;
use HHK\sec\Session;
use HHK\HTMLControls\HTMLTable;
use HHK\ExcelHelper;

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

    /**
     * @param \PDO $dbh
     * @param string $report - used to build fieldset list (ReportFieldSet::listFieldSets())
     * @param array $cFields
     * @param array $request
     */
    public function __construct(\PDO $dbh, string $report = "", array $request = []){
        $uS= Session::getInstance();

        $this->dbh = $dbh;
        $this->request = $request;
        $this->filter = new ReportFilter();
        $this->filter->createTimePeriod(date('Y'), '19', $uS->fy_diff_Months);
        $this->filter->createHospitals();

        $this->cFields = $this->makeCFields();

        $this->fieldSets = ReportFieldSet::listFieldSets($this->dbh, $report, true);
        $fieldSetSelection = (isset($request['fieldset']) ? $request['fieldset']: '');
        $this->colSelector = new ColumnSelectors($this->cFields, 'selFld', true, $this->fieldSets, $fieldSetSelection);

        // set the selected filters
        $this->colSelector->setColumnSelectors($request);
        $this->filter->loadSelectedTimePeriod();
        $this->filter->loadSelectedHospitals();
        $this->filteredTitles = $this->colSelector->getFilteredTitles();
        $this->filteredFields = $this->colSelector->getFilteredFields();

        //default fields
        foreach($this->cFields as $field){
            if($field[2] == 'checked'){
                $this->defaultFields[] = $field[1];
            }
        }
    }

    /**
     * Builds entire filters markup + submit buttons + wrapper div
     *
     * @return string
     */
    public function generateFilterMarkup(){
        $this->makeFilterMkup();

        $this->filterMkup = HTMLContainer::generateMarkup("div", $this->filterMkup, array("id"=>"filterSelectors", "class"=>"hhk-flex"));
        $btnMkup = HTMLContainer::generateMarkup("div",
            HTMLInput::generateMarkup("Run Here", array("type"=>"submit", "name"=>"btnHere", "id"=>"btnHere", "class"=>"ui-button ui-corner-all ui-widget")) .
            HTMLInput::generateMarkup("Download to Excel", array("type"=>"submit", "name"=>"btnExcel", "id"=>"btnExcel", "class"=>"ui-button ui-corner-all ui-widget"))
        , array("id"=>"filterBtns"));

        //wrap in ui-widget + form
        return HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("form",
                    $this->filterMkup . $btnMkup
                , array("method"=>"POST", "action"=>htmlspecialchars($_SERVER["PHP_SELF"])))
            , array("class"=>"ui-widget ui-widget-content ui-corner-all hhk-tdbox hhk-visitdialog filterWrapper"));
    }

    /**
     * Run the report query and return result set based on selected fields
     *
     * @return array $resultSet
     */
    public function getResultSet():array {
        $this->makeQuery();
        if($this->query != ''){
            $stmt = $this->dbh->query($this->query);

            $this->resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }else{
            $this->resultSet = [];
        }
        return $this->resultSet;
    }

    public function generateMarkup() {

        $this->getResultSet();

        $tbl = new HTMLTable();
        $th = '';

        foreach ($this->filteredTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

        foreach($this->resultSet as $r){
            $tr = '';
            foreach ($this->filteredFields as $f) {
                $tr .= HTMLTable::makeTd($r[$f[1]]);
            }

            $tbl->addBodyTr($tr);
        }

        return HTMLContainer::generateMarkup("div", $this->generateSummaryMkup() . $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display')), array('class'=>"ui-widget ui-widget-content ui-corner-all hhk-tdbox", 'id'=>'hhk-reportWrapper'));
    }

    public function generateSummaryMkup():string {

        $summaryMkupAr = $this->makeSummaryMkup();

        $titleMkup = HTMLContainer::generateMarkup('h3', $summaryMkupAr['reportTitle'], array('class'=>'mt-2'));
        $bodyMkup = HTMLContainer::generateMarkup("div", $summaryMkupAr['content'], array('id'=>'repSummary', 'class'=>'ml-2'));
        return $titleMkup . $bodyMkup;

    }

    public function downloadExcel(string $fileName = "HHKReport", string $reportTitle = ""):void {

        $uS = Session::getInstance();
        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle($reportTitle);

        // build header
        $hdr = array();
        $flds = array();
        $colWidths = array();


        foreach($this->filteredFields as $field){
            $hdr[$field[0]] = $field[4]; //set column header name and type;
            $colWidths[] = $field[5]; //set column width
        }

        $hdrStyle = $writer->getHdrStyle($colWidths);
        $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);

        $this->getResultSet();

        foreach($this->resultSet as $r){

            $flds = array();

            foreach ($this->filteredFields as $f) {
                $flds[] = $r[$f[1]];
            }

            $row = $writer->convertStrings($hdr, $flds);
            $writer->writeSheetRow("Sheet1", $row);
        }

        $writer->download();
    }

    public function getDefaultFields(){
        return $this->defaultFields;
    }

}
?>