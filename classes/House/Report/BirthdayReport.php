<?php

namespace HHK\House\Report;

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLContainer;
use HHK\sec\Session;
use HHK\sec\Labels;
use HHK\ColumnSelectors;

/**
 * GuestVehiclesReport.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GuestVehiclesReport
 *
 * @author Will
 * @author Eric
 */
class BirthdayReport {

    protected \PDO $dbh;
    public ReportFilter $filter;
    public ColumnSelectors $colSelector;
    public $defaultFields;

    public function __construct(\PDO $dbh){
        $uS = Session::getInstance();
        $this->dbh = $dbh;
    }

    public function setupFields(){

        $uS = Session::getInstance();
        $this->filter = new ReportFilter();


        return $this->colSelector->makeSelectorTable(TRUE)->generateMarkup(array('style'=>'margin-bottom:0.5em', 'id'=>'includeFields'));
    }

    public function getBirthdayMkup(){
        $guests = array();
        $this->colSelector->setColumnSelectors($_POST);
        $fltrdTitles = $this->colSelector->getFilteredTitles();
        $fltrdFields = $this->colSelector->getFilteredFields();

        $stmt = $this->dbh->query("select * from vguest_view");


        return $this->title . $guestTable;
    }
}
?>