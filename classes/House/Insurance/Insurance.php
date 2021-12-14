<?php

namespace HHK\House\Insurance;

use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLContainer;
use HHK\Tables\Insurance\InsuranceTypeRS;
use HHK\Tables\EditRS;
use HHK\Tables\Insurance\InsuranceRS;

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
            $stmt = $dbh->query("SELECT * FROM `insurance` WHERE `idInsuranceType` = '" . $idInsuranceType . "' Order by `Title`;");
            $this->Insurances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->insuranceType = $idInsuranceType;
        }else if($idInsuranceType == "all"){
            $stmt = $dbh->query("SELECT * FROM `insurance` Order by `Title`;");
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

        $hdrTr = HTMLTable::makeTh(count($this->Insurances) . ' Entries') . HTMLTable::makeTh('Use');

        $tbl->addHeaderTr($hdrTr);

        foreach($this->Insurances as $insurance){

            $cbattr = array("type"=>"checkbox","name"=>'insurances['  . $insurance['idInsurance'] . '][Use]');
            if($insurance["Status"] == "a"){
                $cbattr["checked"] = "checked";
            }

            $tbl->addBodyTr(
                $tbl->makeTd(HTMLInput::generateMarkup($insurance['Title'], array("name"=>'insurances['  . $insurance['idInsurance'] . '][Title]'))) .
                $tbl->makeTd(HTMLInput::generateMarkup('', $cbattr))
                );
        }

        $tbl->addBodyTr(
            $tbl->makeTd(HTMLInput::generateMarkup('', array("name"=>'insurances[new][Title]'))) .
            $tbl->makeTd("New" . HTMLInput::generateMarkup($this->insuranceType, array('type'=>'hidden','name'=>"idInsuranceType")))
            );

        $saveBtn = HTMLContainer::generateMarkup("div", HTMLInput::generateMarkup("Save", array("type"=>"submit","id"=>"btnInsSave", "class"=>"ui-button ui-corner-all")), array("style"=>"text-align:right; margin:10px;"));

        return HTMLContainer::generateMarkup("form", $tbl->generateMarkup() . $saveBtn, array('id'=>"formInss", 'method'=>"POST"));
    }

    public function save(\PDO $dbh, array $post){

        if(isset($post["insurances"])){
            foreach($post["insurances"] as $id=>$insurance){
                $insuranceRS = new InsuranceRS();
                $insuranceRS->idInsurance->setStoredVal($id);
                $rows = EditRS::select($dbh, $insuranceRS, array($insuranceRS->idInsurance));

                if(count($rows) == 1){
                    $insuranceRS->Title->setNewVal($insurance["Title"]);
                    $insuranceRS->Status->setNewVal((isset($insurance["Use"]) ? "a": "d"));
                    EditRS::update($dbh, $insuranceRS, array($insuranceRS->idInsurance));
                }else if($id == "new" && $insurance['Title'] !=''){
                    $insuranceRS = new InsuranceRS();
                    $insuranceRS->idInsuranceType->setNewVal($post['idInsuranceType']);
                    $insuranceRS->Title->setNewVal($insurance["Title"]);
                    $insuranceRS->Status->setNewVal('a');

                    EditRS::insert($dbh, $insuranceRS);
                }
            }
        }
    }

}
?>