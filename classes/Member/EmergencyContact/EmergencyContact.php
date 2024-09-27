<?php

namespace HHK\Member\EmergencyContact;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLSelector, HTMLTable};
use HHK\SysConst\RelLinkType;
use HHK\Tables\EditRS;
use HHK\Tables\Name\EmergContactRS;
use HHK\sec\Labels;

/**
 * EmergencyContact.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of EmergencyContact
 *
 */
class EmergencyContact implements EmergencyContactInterface {

    /**
     * Summary of ecRS
     * @var
     */
    protected $ecRS;

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param mixed $id
     */
    public function __construct(\PDO $dbh, $id) {

        $this->ecRS = $this->loadDbRecord($dbh, $id);

    }

    /**
     * Summary of loadDbRecord
     * @param \PDO $dbh
     * @param mixed $id
     * @return EmergContactRS
     */
    public static function loadDbRecord(\PDO $dbh, $id) {

        $ecRS = new EmergContactRs();

        if ($id > 0) {
            $ecRS->idName->setStoredVal($id);
            $rows = EditRS::select($dbh, $ecRS, array($ecRS->idName));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $ecRS);
            }
        }

        return $ecRS;
    }

    /**
     * Summary of save
     * @param \PDO $dbh
     * @param mixed $id
     * @param mixed $pData
     * @param mixed $uname
     * @param mixed $idPrefix
     * @return string
     */
    public function save(\PDO $dbh, $id, $pData, $uname, $idPrefix = '') {
        // Emergency Contact
        $rtnMsg = "";

        if ($id > 0) {

            // Set These values for any update or insert
            $this->ecRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $this->ecRS->Updated_By->setNewVal($uname);

            $this->extractMarkup($pData, $idPrefix);


            if ($this->ecRS->idEmergency_contact->getStoredVal() == 0) {

                if ($this->ecRS->Name_Last->getNewVal() != '' || $this->ecRS->Name_First->getNewVal() != '') {

                    // Insert
                    $this->ecRS->idName->setNewVal($id);

                    $n = EditRS::insert($dbh, $this->ecRS);
                    if ($n > 0) {
                        $rtnMsg .= "Emergency Contact Saved.  ";
                    }
                }

            } else {
                //update
                $n = EditRS::update($dbh, $this->ecRS, array($this->ecRS->idName));
                if ($n > 0) {
                    $rtnMsg .= "Emergency Contact Updated.  ";
                }
            }

            EditRS::updateStoredVals($this->ecRS);
        }
        return $rtnMsg;
    }

    /**
     * Summary of extractMarkup
     * @param mixed $pData
     * @param mixed $idPrefix
     * @return void
     */
    protected function extractMarkup($pData, $idPrefix = "") {

        if (isset($pData[$idPrefix.'txtEmrgFirst'])) {
            $this->ecRS->Name_First->setNewVal(ucfirst(filter_var($pData[$idPrefix.'txtEmrgFirst'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
        }
        if (isset($pData[$idPrefix.'txtEmrgLast'])) {
            $this->ecRS->Name_Last->setNewVal(ucfirst(filter_var($pData[$idPrefix.'txtEmrgLast'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)));
        }
        if (isset($pData[$idPrefix.'txtEmrgPhn'])) {
            $this->ecRS->Phone_Home->setNewVal(filter_var($pData[$idPrefix.'txtEmrgPhn'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($pData[$idPrefix.'txtEmrgAlt'])) {
            $this->ecRS->Phone_Alternate->setNewVal(filter_var($pData[$idPrefix.'txtEmrgAlt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }
        if (isset($pData[$idPrefix.'selEmrgRel'])) {

            $val = filter_var($pData[$idPrefix.'selEmrgRel'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if ($val == RelLinkType::Self) {
                $val = '';
            }

            $this->ecRS->Relationship->setNewVal($val);
        }

    }

    /**
     * Summary of createMarkup
     * @param mixed $relOptions
     * @param mixed $idPrefix
     * @param mixed $checkLater
     * @param mixed $emergUserData
     * @return string
     */
    public function createMarkup($relOptions, $idPrefix = "", $checkLater = FALSE, $emergUserData = []) {

        if (isset($relOptions[RelLinkType::Self])) {
            unset($relOptions[RelLinkType::Self]);
        }

        $markup = new HTMLTable();

        // First Name
        $markup->addBodyTr(HTMLTable::makeTd('First Name', array('class'=>'tdlabel')) . HTMLTable::makeTd(
            HTMLInput::generateMarkup((isset($emergUserData['firstName']) && $emergUserData['firstName'] != '' ? $emergUserData['firstName'] : $this->getEcNameFirst()), array('name'=>$idPrefix.'txtEmrgFirst', 'size'=>'14'))));

        // Last Name
        $markup->addBodyTr(HTMLTable::makeTd('Last Name', array('class'=>'tdlabel')) . HTMLTable::makeTd
            (HTMLInput::generateMarkup((isset($emergUserData['lastName']) && $emergUserData['lastName'] != '' ? $emergUserData['lastName'] : $this->getEcNameLast()), array('name'=>$idPrefix.'txtEmrgLast', 'size'=>'14'))));

        // Phone
        $markup->addBodyTr(HTMLTable::makeTd('Phone', array('class'=>'tdlabel')) . HTMLTable::makeTd(
            HTMLInput::generateMarkup((isset($emergUserData['phone']) && $emergUserData['phone'] != '' ? $emergUserData['phone'] : $this->getEcPhone()), array('name'=>$idPrefix.'txtEmrgPhn', 'class'=>'hhk-phoneInput', 'size'=>'14'))));

        // alt phone
        $markup->addBodyTr(HTMLTable::makeTd('Alternate', array('class'=>'tdlabel')) . HTMLTable::makeTd(
            HTMLInput::generateMarkup((isset($emergUserData['altphone']) && $emergUserData['altphone'] != '' ? $emergUserData['altphone'] : $this->getEcAltPhone()), array('name'=>$idPrefix.'txtEmrgAlt', 'class'=>'hhk-phoneInput', 'size'=>'14'))));

        // Relationship
        $markup->addBodyTr(HTMLTable::makeTd('Relationship to ' . Labels::getString('MemberType', 'visitor', 'Guest'), array('class'=>'tdlabel')) . HTMLTable::makeTd(
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($relOptions), (isset($emergUserData['relation']) && $emergUserData['relation'] !='' ? $emergUserData['relation'] : $this->getEcRelationship())), array('name'=>$idPrefix."selEmrgRel"))));

        $attr = array('type'=>'checkbox', 'name'=>$idPrefix.'cbEmrgLater', 'data-prefix'=>$idPrefix, 'class'=>'hhk-EmergCb');
        if ($checkLater) {
            $attr['checked'] = 'checked';
        }

        $later = HTMLContainer::generateMarkup('div', HTMLInput::generateMarkup('', $attr) . HTMLContainer::generateMarkup('label', ' Skip for now', array('for'=>$idPrefix.'cbEmrgLater')), array('style'=>'margin-top:10px; margin-left:40px;'));
        return $markup->generateMarkup() . $later;
    }

    /**
     * Summary of getEcNameFirst
     * @return mixed
     */
    public function getEcNameFirst() {
        return $this->ecRS->Name_First->getStoredVal();
    }

    /**
     * Summary of getEcNameLast
     * @return mixed
     */
    public function getEcNameLast() {
        return $this->ecRS->Name_Last->getStoredVal();
    }

    /**
     * Summary of getEcPhone
     * @return mixed
     */
    public function getEcPhone() {
        return $this->ecRS->Phone_Home->getStoredVal();
    }

    /**
     * Summary of getEcAltPhone
     * @return mixed
     */
    public function getEcAltPhone() {
        return $this->ecRS->Phone_Alternate->getStoredVal();
    }

    /**
     * Summary of getEcRelationship
     * @return mixed
     */
    public function getEcRelationship() {
        return $this->ecRS->Relationship->getStoredVal();
    }

}
