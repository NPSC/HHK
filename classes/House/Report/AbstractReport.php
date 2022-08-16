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
    public string $filterOptsMkup = "";
    protected $request;
    protected string $reportTitle = "";
    protected string $inputSetReportName = "";

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
        $this->colSelector = new ColumnSelectors($this->cFields, $report . '-selFld', true, $this->fieldSets, $fieldSetSelection);

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

        //register actions
        $this->actions($request);
    }

    /**
     * Builds entire filters markup + submit buttons + wrapper div
     *
     * @return string
     */
    public function generateFilterMarkup(){
        $this->makeFilterMkup();
        $this->makeFilterOptsMkup();
        $filterOptsMkup = "";

        if($this->filterOptsMkup != ""){
            $filterOptsMkup = HTMLContainer::generateMarkup("div","<strong>Filter Options:</strong>". $this->filterOptsMkup, array("class"=>"ui-widget-content ui-corner-all hhk-flex mr-5", "id"=>"filterOpts"));
        }

        $this->filterMkup = HTMLContainer::generateMarkup("div", $this->filterMkup, array("id"=>"filterSelectors", "class"=>"hhk-flex"));
        $btnMkup = HTMLContainer::generateMarkup("div",
            $filterOptsMkup .
            HTMLInput::generateMarkup("Run Here", array("type"=>"submit", "name"=>"btnHere", "id"=>"btnHere", "class"=>"ui-button ui-corner-all ui-widget")) .
            HTMLInput::generateMarkup("Download to Excel", array("type"=>"submit", "name"=>"btnExcel", "id"=>"btnExcel", "class"=>"ui-button ui-corner-all ui-widget"))
        , array("id"=>"filterBtns", "class"=>"mt-3"));

        $emDialog = $this->generateEmailDialog();

        //wrap in ui-widget + form
        return HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("form",
                    $this->filterMkup . $btnMkup . $emDialog
                , array("method"=>"POST", "action"=>htmlspecialchars($_SERVER["PHP_SELF"]), "id"=>$this->inputSetReportName . "RptFilterForm"))
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

    /**
     * Generate HTML markup
     *
     * @param string $outputType if sending email, set to "email" to format fields for email
     * @return string
     */
    public function generateMarkup(string $outputType = "") {

        if(count($this->resultSet) == 0){
            $this->getResultSet();
        }

        $tbl = new HTMLTable();
        $th = '';

        foreach ($this->filteredTitles as $t) {
            $th .= HTMLTable::makeTh($t);
        }
        $tbl->addHeaderTr($th);

        foreach($this->resultSet as $r){
            $tr = '';
            foreach ($this->filteredFields as $f) {
                if($outputType == "email" && isset($f[7]) && $f[7] == "date"){
                    $fieldDT = new \DateTime($r[$f[1]]);
                    $r[$f[1]] = $fieldDT->format("M j, Y");
                }
                $tr .= HTMLTable::makeTd($r[$f[1]]);
            }

            $tbl->addBodyTr($tr);
        }

        return HTMLContainer::generateMarkup("div", $this->generateSummaryMkup() . $tbl->generateMarkup(array('id'=>'tbl' . $this->inputSetReportName . 'rpt', 'class'=>'display')), array('class'=>"ui-widget ui-widget-content ui-corner-all hhk-tdbox", 'id'=>'hhk-reportWrapper'));
    }

    public function generateSummaryMkup():string {

        $uS = Session::getInstance();
        $summaryMkup = $this->makeSummaryMkup();

        $titleMkup = HTMLContainer::generateMarkup('h3', $this->reportTitle, array('class'=>'mt-2'));
        $bodyMkup = HTMLContainer::generateMarkup("div", HTMLContainer::generateMarkup("div", $summaryMkup, array('class'=>'ml-2')) . HTMLContainer::generateMarkup("img", "", array('src'=> $uS->resourceURL . "conf/receiptlogo.png")), array('id'=>'repSummary', 'class'=>'hhk-flex mb-3', 'style'=>'justify-content: space-between'));
        return $titleMkup . $bodyMkup;

    }

    public function generateReportScript(){
        $jsonColumnDefs = json_encode($this->colSelector->getColumnDefs());
        $uS = Session::getInstance();

        return '
        $("#tbl' . $this->inputSetReportName . 'rpt").dataTable({
            "columnDefs": [
            {"targets": ' . $jsonColumnDefs . ',
            "type": "date",
            "render": function ( data, type, row ) {return dateRender(data, type, dateFormat);}
            }
            ],
            "displayLength": 50,
            "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
            "dom": "<\"top ui-toolbar ui-helper-clearfix\"Bilf>rt<\"bottom ui-toolbar ui-helper-clearfix\"lp>",
            "buttons": [
            {
                extend: "print",
                className: "ui-corner-all",
                autoPrint: true,
                title: function(){
                    return "' . $this->reportTitle . '";
                },
                messageTop: function(){
                    return document.getElementById("repSummary").outerHTML;
                },
                messageBottom: function(){
                    var url = "' . $uS->resourceURL . '";
                    url = url.replace(/^(https\:\/\/)/,"");
                    url = url.replace(/(\/)$/,"");
                    var now = moment().format("MMM d, YYYY h:mm a");

                    return "<div style=\"padding-top: 10px; position: fixed; bottom: 0; right: 0\">Generated by <strong>' . $uS->username . '</strong> on " + now + " from Hospitality Housekeeper - " + url + "</div>";
                }
            },
            {
                text: "Email",
                className: "ui-corner-all",
                action: function (e) {
                    $("#em' . $this->inputSetReportName . 'RptDialog").dialog("open");
                }
            },
            ],
        });

        $("#em' . $this->inputSetReportName . 'RptDialog").dialog({
            autoOpen:false,
            modal:true,
            title: "Email ' . $this->reportTitle . '",
            width: "auto",
            buttons: {
                "Send":function(){
                    var data = $("#' . $this->inputSetReportName . 'RptFilterForm").serializeArray();
                        data.push({"name":"btn' . $this->inputSetReportName . 'Email", "value":"true"});
                        data.push({"name":"txtSubject", "value":$(this).find("#txtSubject").val()});
                        data.push({"name":"txtEmail", "value":$(this).find("#txtEmail").val()});
                    $.ajax({
                        type:"post",
                        data:data,
                        dataType: "json",
                        success: function(data){
                            if(data.success){
                                flagAlertMessage(data.success,false);
                                $("#em' . $this->inputSetReportName . 'RptDialog").dialog("close");
                            }else if(data.error){
                                flagAlertMessage(data.error, true);
                            }else{
                                flagAlertMessage("An unknown error occurred", true);
                            }
                        }
                    });
                }
            }
        });
';
    }

    public function generateEmailStyles():string {
        return '
<style>
    p{
        margin:0;
        padding:0;
    }

    #hhk-reportWrapper {
        width: max-content;
    }

    #repSummary{
        margin-bottom: 1rem;
    }

    #repSummary>div {
        display:inline-block;
    }

    #repSummary>img {
        float:right;
    }

    table#tbl' . $this->inputSetReportName . 'rpt {
        border-collapse: collapse;
    }

    table#tbl' . $this->inputSetReportName . 'rpt td {
        border:1px solid #c1c1c1;
        padding: 10px;
    }

    table#tbl' . $this->inputSetReportName . 'rpt thead th {
        padding:10px;
        border-bottom: 2px solid #111
    }

</style

';
    }

    public function downloadExcel(string $fileName = "HHKReport"):void {

        $uS = Session::getInstance();
        $writer = new ExcelHelper($fileName);
        $writer->setAuthor($uS->username);
        $writer->setTitle($this->reportTitle);

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

    public function generateEmailDialog(){
        $emTbl = new HTMLTable();
        $emTbl->addBodyTr(HTMLTable::makeTd('Subject: ') . HTMLTable::makeTd(HTMLInput::generateMarkup($this->reportTitle, array('name' => 'txtSubject', "style"=>"width: 100%"))));
        $emTbl->addBodyTr(HTMLTable::makeTd('Email: ') . HTMLTable::makeTd(HTMLInput::generateMarkup('', array('name' => 'txtEmail', 'style'=>'width: 100%')) . HTMLInput::generateMarkup('', array('type'=>"hidden", "name"=>"btnEmail"))));

        return HTMLContainer::generateMarkup("div", $emTbl->generateMarkup(), array("id"=>"em" . $this->inputSetReportName . "RptDialog", "class"=>"emRptDialog", "style"=>"display:none;"));
    }

    public function sendEmail(string $emailAddress = "", string $subject = "", bool $cronDryRun = false){
        $uS = Session::getInstance();

        $errors = array();

        $body = "<html><head>" . $this->generateEmailStyles() . "</head><body>" . $this->generateMarkup("email") . "</body></html>";

        if ($emailAddress == ''){
            $errors[] = "Email Address is required";
        }else{
            $addresses = explode(",", $emailAddress);

            foreach($addresses as $k=>$address){
                $addresses[$k] = trim($address);
                if(!filter_var($addresses[$k], FILTER_VALIDATE_EMAIL)){
                    $errors[] = "Email Address " . $addresses[$k] . " is not a valid Email Address";
                }
            }
            $emailAddress = implode(", ", $addresses);
        }

        if($subject == ''){
            $errors[] = "Subject is required";
        }

        if(count($errors) > 0){
            return array("error"=>implode("<br>", $errors));
        }

        if(count($errors) == 0 && $body !=''){

            try{
                $mail = prepareEmail();

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = $uS->siteName;

                foreach ($addresses as $t) {
                    $mail->addAddress($t);
                }

                $mail->isHTML(true);

                $mail->Subject = $subject;

                $mail->msgHTML($body);
                if($cronDryRun == false){
                    $mail->send();
                    return array("success"=>"Email sent to " . $emailAddress . " successfully");
                }else{
                    return array("success"=>"Email would be sent to " . $emailAddress);
                }

            }catch(\Exception $e){
                return array("error"=>"Email failed!  " . $mail->ErrorInfo);
            }

        }
    }

    protected function actions(array $request):void{
        $result = array();

        if (isset($this->request['btn' . $this->inputSetReportName . 'Email']) && $this->request['btn' . $this->inputSetReportName . 'Email'] == 'true') {

            $emailAddress = '';
            $subject = '';

            if (isset($this->request['txtEmail'])) {
                $emailAddress = filter_var($_POST['txtEmail'], FILTER_SANITIZE_STRING);
            }

            if (isset($this->request['txtSubject'])) {
                $subject = filter_var($_POST['txtSubject'], FILTER_SANITIZE_STRING);
            }

            $result = $this->sendEmail($emailAddress, $subject);
        }

        if(count($result) > 0){
            echo json_encode($result);
            exit();
        }

    }

    public function getDefaultFields(){
        return $this->defaultFields;
    }

    public function makeFilterOptsMkup(){

    }

    public function getInputSetReportName(){
        return $this->inputSetReportName;
    }

}
?>