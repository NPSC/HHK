<?php

namespace HHK\House\Insurance;

use HHK\HTMLControls\HTMLSelector;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\HTMLControls\HTMLContainer;
use HHK\Tables\Insurance\InsuranceTypeRS;
use HHK\Tables\EditRS;

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

class InsuranceType {

    private $InsuranceTypeRS;

    private $InsuranceTypes;

    public function loadInsuranceTypes(\PDO $dbh){
        $stmt = $dbh->query("SELECT * FROM `insurance_type` Order by `List_Order`;");

        $this->InsuranceTypes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function generateEditMarkup(){
        $tbl = new HTMLTable();

        $hdrTr = HTMLTable::makeTh(count($this->InsuranceTypes) . ' Entries') . HTMLTable::makeTh('Order') . HTMLTable::makeTh('Use');

        $tbl->addHeaderTr($hdrTr);

        foreach($this->InsuranceTypes as $type){

            $cbattr = array("type"=>"checkbox","name"=>'insuranceTypes['  . $type['idInsurance_type'] . '][Use]');
            if($type["Status"] == "a"){
                $cbattr["checked"] = "checked";
            }

            $tbl->addBodyTr(
                $tbl->makeTd(HTMLInput::generateMarkup($type['Title'], array("name"=>'insuranceTypes['  . $type['idInsurance_type'] . '][Title]'))) .
                $tbl->makeTd(HTMLInput::generateMarkup($type['List_Order'], array("name"=>'insuranceTypes['  . $type['idInsurance_type'] . '][List_Order]', 'style'=>"width: 4em", "type"=>"number"))) .
                $tbl->makeTd(HTMLInput::generateMarkup('', $cbattr))
            );
        }

        $saveBtn = HTMLContainer::generateMarkup("div", HTMLInput::generateMarkup("Save", array("type"=>"submit","id"=>"btnInsSave", "class"=>"ui-button ui-corner-all")), array("style"=>"text-align:right; margin:10px;"));

        return HTMLContainer::generateMarkup("form", $tbl->generateMarkup() . $saveBtn, array('id'=>"formIns", 'method'=>"POST"));
    }

    public function generateSelector(){
        $options = array();
        foreach($this->InsuranceTypes as $type){
            if($type['Status'] == 'a'){
                $options[] = [$type['idInsurance_type'], $type['Title']];
            }
        }
        $selInsTypes = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($options, ''), array(
            'name' => 'selInsLookup',
            'data-type' => 'insurance',
            'class' => 'hhk-selLookup'
        ));
        return $selInsTypes;
    }

    public function save(\PDO $dbh, array $post){

        if(isset($post["insuranceTypes"])){
            foreach($post["insuranceTypes"] as $id=>$type){
                $insuranceTypeRS = new InsuranceTypeRS();
                $insuranceTypeRS->idInsurance_type->setStoredVal($id);
                $rows = EditRS::select($dbh, $insuranceTypeRS, array($insuranceTypeRS->idInsurance_type));

                if(count($rows) == 1){
                    $insuranceTypeRS->Title->setNewVal($type["Title"]);
                    $insuranceTypeRS->List_Order->setNewVal($type["List_Order"]);
                    $insuranceTypeRS->Status->setNewVal((isset($type["Use"]) ? "a": "d"));
                    EditRS::update($dbh, $insuranceTypeRS, array($insuranceTypeRS->idInsurance_type));
                }
            }
        }
    }

}
?>