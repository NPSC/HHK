<?php

namespace HHK\House\Insurance;

use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLContainer;
use HHK\Tables\Insurance\InsuranceTypeRS;
use HHK\Tables\EditRS;
use HHK\Tables\Insurance\InsuranceRS;
use HHK\Tables\Name\Name_InsuranceRS;

/**
 * InsuranceType.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2021 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * InsuranceType
 * @author Will
 */

class Insurance {

    private $InsuranceRS;

    private $Insurances;

    private $insuranceType;

    public function loadInsurances(\PDO $dbh, $idInsuranceType = 0){
        if($idInsuranceType > 0){
            $stmt = $dbh->query("SELECT * FROM `insurance` WHERE `idInsuranceType` = '" . $idInsuranceType . "' Order by `Order`,`Title`;");
            $this->Insurances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->insuranceType = $idInsuranceType;
        }else if($idInsuranceType == "all"){
            $stmt = $dbh->query("SELECT * FROM `insurance` Order by `Order`, `Title`;");
            $this->Insurances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }else{
            $this->Insurances = array();
        }
    }

    public function generateTblMarkup(){
        if($this->insuranceType == null){
            return '';
        }

        $tbl = new HTMLTable();

        $hdrTr = HTMLTable::makeTh("") . HTMLTable::makeTh(count($this->Insurances) . ' Entries') . HTMLTable::makeTh('Delete') . HTMLTable::makeTh('Replace with');

        $tbl->addHeaderTr($hdrTr);

        foreach($this->Insurances as $insurance){
            $tbl->addBodyTr(
                $tbl->makeTd(HTMLContainer::generateMarkup("span", "", array("class"=>"ui-icon ui-icon-arrowthick-2-n-s")) . HTMLInput::generateMarkup($insurance['Order'], array("name"=>'insurances['  . $insurance['idInsurance'] . '][Order]', 'style'=>"width: 4em", "type"=>"hidden")), array("class"=>"sort-handle")) .
                $tbl->makeTd(HTMLInput::generateMarkup($insurance['Title'], array("name"=>'insurances['  . $insurance['idInsurance'] . '][Title]'))) .
                $tbl->makeTd(HTMLInput::generateMarkup('', array("type"=>"checkbox","name"=>'insurances['  . $insurance['idInsurance'] . '][Delete]'))) .
                $tbl->makeTd($this->generateReplaceSelector($insurance['idInsurance']))
                );
        }

        $tbl->addBodyTr(
            $tbl->makeTd(HTMLInput::generateMarkup("0", array("name"=>'insurances[new][Order]', 'style'=>"width: 4em", "type"=>"hidden"))) .
            $tbl->makeTd(HTMLInput::generateMarkup('', array("name"=>'insurances[new][Title]'))) .
            $tbl->makeTd("New" . HTMLInput::generateMarkup($this->insuranceType, array('type'=>'hidden','name'=>"idInsuranceType")), array('colspan'=>'2'))
        ,array("class"=>"no-sort"));

        $saveBtn = HTMLContainer::generateMarkup("div", HTMLInput::generateMarkup("Save", array("type"=>"button","id"=>"btnInsSave", "class"=>"ui-button ui-corner-all")), array("style"=>"text-align:right; margin:10px;"));

        return HTMLContainer::generateMarkup("form", $tbl->generateMarkup(array("class"=>"sortable")) . $saveBtn, array('id'=>"formInss", 'method'=>"POST"));
    }

    public function generateReplaceSelector($id){
        $options = array();
        foreach($this->Insurances as $insurance){
            if($insurance['Status'] == 'a' && $insurance["idInsurance"] != $id){
                $options[] = [$insurance['idInsurance'], $insurance['Title']];
            }
        }
        return HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, ''), array("name"=>'insurances['  . $id . '][replaceWith]', "style"=>"width:100%"));
    }

    public function save(\PDO $dbh, array $post){

        if(isset($post["insurances"])){
            foreach($post["insurances"] as $id=>$insurance){
                $id = intval(filter_var($id, FILTER_SANITIZE_NUMBER_INT), 10);
                $insurance["Title"] = filter_var($insurance["Title"], FILTER_SANITIZE_STRING);
                $insurance["Order"] = intval(filter_var($insurance["Order"], FILTER_SANITIZE_NUMBER_INT), 10);
                $insurance["replaceWith"] = intval(filter_var($insurance["replaceWith"], FILTER_SANITIZE_NUMBER_INT), 10);

                $insuranceRS = new InsuranceRS();
                $insuranceRS->idInsurance->setStoredVal($id);
                $rows = EditRS::select($dbh, $insuranceRS, array($insuranceRS->idInsurance));

                if(count($rows) == 1){
                    EditRS::loadRow($rows[0], $insuranceRS);
                    if(isset($insurance["Delete"]) && $insurance["replaceWith"]){
                        //delete & replace
                        $old = $id;
                        $new = $insurance["replaceWith"];

                        $query = "update `name_insurance` set `Insurance_Id` = :newId where `Insurance_Id` = :oldId";
                        $stmt = $dbh->prepare($query);
                        $stmt->bindValue(":newId", $new);
                        $stmt->bindValue(":oldId", $old);
                        $stmt->execute();

                        $nameInsuranceRS = new Name_InsuranceRS();
                        $nameInsuranceRS->Insurance_Id->setStoredVal($old);

                        $rows = EditRS::select($dbh, $nameInsuranceRS, array($nameInsuranceRS->Insurance_Id));
                        if(count($rows) == 0){
                            EditRS::delete($dbh, $insuranceRS, array($insuranceRS->idInsurance));
                        }
                    }else{
                        $insuranceRS->Title->setNewVal($insurance["Title"]);
                        $insuranceRS->Order->setNewVal($insurance["Order"]);
                        EditRS::update($dbh, $insuranceRS, array($insuranceRS->idInsurance));
                    }
                }else if($id == "new" && $insurance['Title'] !=''){
                    $insuranceRS = new InsuranceRS();
                    $insuranceRS->idInsuranceType->setNewVal($post['idInsuranceType']);
                    $insuranceRS->Title->setNewVal($insurance["Title"]);
                    $insuranceRS->Order->setNewVal($insurance["Order"]);

                    EditRS::insert($dbh, $insuranceRS);
                }
            }
        }
    }

}
?>